<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_crm.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_redirect(string $query = ''): never
{
    header('Location: /admin/' . ($query === '' ? '' : '?' . ltrim($query, '?')));
    exit;
}

function admin_date(?string $date, string $format = 'd/m/Y · H:i'): string
{
    if (!$date) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string)crm_setting('timezone', 'Africa/Algiers')))
            ->format($format);
    } catch (Throwable) {
        return $date;
    }
}

function admin_money(mixed $value, string $currency = 'DZD'): string
{
    return number_format((float)$value, 0, ',', ' ') . ' ' . e($currency);
}

function admin_input(string $key, int $max = 500): string
{
    return cms_clean_text($_POST[$key] ?? '', $max);
}

function admin_nullable_id(string $key): ?int
{
    $value = (int)($_POST[$key] ?? 0);
    return $value > 0 ? $value : null;
}

function admin_require_editor(): array
{
    $user = crm_current_user();
    if ($user === null) {
        admin_redirect();
    }
    crm_require_permission('records.edit');
    return $user;
}

function admin_record_link(string $type, ?int $id): string
{
    if (!$id) {
        return 'dashboard';
    }
    $views = [
        'lead' => 'leads',
        'contact' => 'contacts',
        'company' => 'companies',
        'opportunity' => 'pipeline',
        'task' => 'tasks',
    ];
    return ($views[$type] ?? 'dashboard') . '&id=' . $id;
}

function admin_handle_actions(): void
{
    $action = (string)($_POST['action'] ?? '');
    if ($action === '') {
        return;
    }

    if ($action === 'login') {
        cms_verify_csrf();
        if (!cms_rate_limit('admin-login', 8, 900)) {
            cms_flash('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
            admin_redirect();
        }
        $user = crm_authenticate(admin_input('username', 100), (string)($_POST['password'] ?? ''));
        if ($user === null) {
            cms_flash('error', 'Identifiant ou mot de passe incorrect.');
            admin_redirect();
        }
        crm_login_user($user);
        crm_audit('login', 'user', (int)$user['id'], 'Connexion au CRM', [], (int)$user['id']);
        cms_flash('success', 'Connexion réussie.');
        admin_redirect('view=dashboard');
    }

    if ($action === 'logout') {
        cms_verify_csrf();
        $user = crm_current_user();
        if ($user !== null) {
            crm_audit('logout', 'user', (int)$user['id'], 'Déconnexion du CRM', [], (int)$user['id']);
        }
        $_SESSION = [];
        session_destroy();
        admin_redirect();
    }

    $user = crm_current_user();
    if ($user === null) {
        admin_redirect();
    }
    cms_verify_csrf();
    $userId = (int)$user['id'];

    try {
        if (in_array($action, [
            'save_lead', 'convert_lead', 'save_contact', 'save_company', 'save_opportunity',
            'move_opportunity', 'save_task', 'save_activity', 'save_communication', 'assign_tags',
            'toggle_onboarding_item', 'save_onboarding_item',
        ], true)) {
            crm_require_permission('records.edit');
        }

        if ($action === 'save_lead') {
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                admin_input('name', 160), admin_input('company_name', 180),
                mb_strtolower(admin_input('email', 190)), admin_input('phone', 60),
                admin_input('service', 160), admin_input('message', 5000),
                array_key_exists(admin_input('status', 30), CRM_LEAD_STATUSES) ? admin_input('status', 30) : 'new',
                array_key_exists(admin_input('priority', 20), CRM_PRIORITIES) ? admin_input('priority', 20) : 'normal',
                admin_input('source', 80) ?: 'manual',
                (float)str_replace(',', '.', admin_input('estimated_value', 30)),
                admin_nullable_id('owner_id') ?? $userId,
            ];
            if ($data[0] === '') {
                throw new RuntimeException('Le nom du lead est obligatoire.');
            }
            if ($id > 0) {
                crm_execute(
                    'UPDATE leads SET name=?, company_name=?, email=?, phone=?, service=?, message=?, status=?,
                     priority=?, source=?, estimated_value=?, owner_id=?, updated_at=? WHERE id=? AND deleted_at IS NULL',
                    [...$data, crm_now(), $id]
                );
                crm_audit('update', 'lead', $id, 'Lead modifié', [], $userId);
            } else {
                $id = crm_create_lead([
                    'name' => $data[0], 'company_name' => $data[1], 'email' => $data[2], 'phone' => $data[3],
                    'service' => $data[4], 'message' => $data[5], 'status' => $data[6], 'priority' => $data[7],
                    'source' => $data[8], 'estimated_value' => $data[9], 'owner_id' => $data[10],
                ], $userId);
            }
            cms_flash('success', 'Le lead a été enregistré.');
            admin_redirect('view=leads&id=' . $id);
        }

        if ($action === 'convert_lead') {
            $id = (int)($_POST['id'] ?? 0);
            crm_convert_lead($id, [
                'create_opportunity' => isset($_POST['create_opportunity']),
                'opportunity_name' => admin_input('opportunity_name', 180),
                'value' => (float)str_replace(',', '.', admin_input('value', 30)),
                'expected_close_date' => admin_input('expected_close_date', 20) ?: null,
                'next_action' => admin_input('next_action', 220),
            ], $userId);
            cms_flash('success', 'Le lead est maintenant un contact et une opportunité.');
            admin_redirect('view=leads&id=' . $id);
        }

        if ($action === 'save_contact') {
            $id = (int)($_POST['id'] ?? 0);
            $fields = [
                admin_input('first_name', 100), admin_input('last_name', 100), mb_strtolower(admin_input('email', 190)),
                admin_input('phone', 60), admin_input('mobile', 60), admin_input('job_title', 140),
                admin_input('preferred_language', 10) ?: 'fr', admin_input('address', 500), admin_input('city', 100),
                admin_input('country', 100) ?: 'Algérie', admin_input('source', 80) ?: 'manual',
                admin_input('status', 30) ?: 'prospect', admin_nullable_id('owner_id') ?? $userId,
                admin_nullable_id('company_id'), admin_input('notes', 5000),
            ];
            if ($fields[0] === '') {
                throw new RuntimeException('Le prénom est obligatoire.');
            }
            if ($id > 0) {
                crm_execute(
                    'UPDATE contacts SET first_name=?,last_name=?,email=?,phone=?,mobile=?,job_title=?,preferred_language=?,
                     address=?,city=?,country=?,source=?,status=?,owner_id=?,company_id=?,notes=?,updated_at=? WHERE id=? AND deleted_at IS NULL',
                    [...$fields, crm_now(), $id]
                );
                crm_audit('update', 'contact', $id, 'Contact modifié', [], $userId);
            } else {
                $id = crm_create_contact([
                    'first_name' => $fields[0], 'last_name' => $fields[1], 'email' => $fields[2], 'phone' => $fields[3],
                    'mobile' => $fields[4], 'job_title' => $fields[5], 'preferred_language' => $fields[6],
                    'address' => $fields[7], 'city' => $fields[8], 'country' => $fields[9], 'source' => $fields[10],
                    'status' => $fields[11], 'owner_id' => $fields[12], 'company_id' => $fields[13], 'notes' => $fields[14],
                ], $userId);
            }
            cms_flash('success', 'Le contact a été enregistré.');
            admin_redirect('view=contacts&id=' . $id);
        }

        if ($action === 'save_company') {
            $id = (int)($_POST['id'] ?? 0);
            $fields = [
                admin_input('name', 180), admin_input('legal_name', 220), admin_input('industry', 120),
                admin_input('website', 220), mb_strtolower(admin_input('email', 190)), admin_input('phone', 60),
                admin_input('address', 500), admin_input('city', 100), admin_input('country', 100) ?: 'Algérie',
                admin_input('tax_id', 100), admin_input('registration_number', 100),
                admin_input('status', 30) ?: 'prospect', admin_nullable_id('owner_id') ?? $userId,
                admin_input('notes', 5000),
            ];
            if ($fields[0] === '') {
                throw new RuntimeException('Le nom de l’entreprise est obligatoire.');
            }
            if ($id > 0) {
                crm_execute(
                    'UPDATE companies SET name=?,legal_name=?,industry=?,website=?,email=?,phone=?,address=?,city=?,
                     country=?,tax_id=?,registration_number=?,status=?,owner_id=?,notes=?,updated_at=? WHERE id=? AND deleted_at IS NULL',
                    [...$fields, crm_now(), $id]
                );
                crm_audit('update', 'company', $id, 'Entreprise modifiée', [], $userId);
            } else {
                $id = crm_create_company([
                    'name' => $fields[0], 'legal_name' => $fields[1], 'industry' => $fields[2], 'website' => $fields[3],
                    'email' => $fields[4], 'phone' => $fields[5], 'address' => $fields[6], 'city' => $fields[7],
                    'country' => $fields[8], 'tax_id' => $fields[9], 'registration_number' => $fields[10],
                    'status' => $fields[11], 'owner_id' => $fields[12], 'notes' => $fields[13],
                ], $userId);
            }
            cms_flash('success', 'L’entreprise a été enregistrée.');
            admin_redirect('view=companies&id=' . $id);
        }

        if ($action === 'save_opportunity') {
            $id = (int)($_POST['id'] ?? 0);
            $wasWon = false;
            if ($id > 0) {
                $previousStage = crm_fetch_one(
                    'SELECT s.is_won FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id
                     WHERE o.id=? AND o.deleted_at IS NULL',
                    [$id]
                );
                $wasWon = (int)($previousStage['is_won'] ?? 0) === 1;
            }
            $stageId = admin_nullable_id('stage_id') ?? (int)(crm_fetch_one('SELECT id FROM pipeline_stages WHERE is_active=1 ORDER BY position LIMIT 1')['id'] ?? 0);
            $stage = crm_fetch_one('SELECT probability,is_won,is_lost FROM pipeline_stages WHERE id=?', [$stageId]);
            if ($stage === null) {
                throw new RuntimeException('Étape du pipeline invalide.');
            }
            $fields = [
                admin_input('name', 180), admin_input('service', 160), admin_input('description', 5000),
                (float)str_replace(',', '.', admin_input('value', 30)), admin_input('currency', 10) ?: 'DZD',
                max(0, min(100, (int)($_POST['probability'] ?? $stage['probability']))),
                admin_input('expected_close_date', 20) ?: null, admin_input('next_action', 220),
                admin_input('source', 80) ?: 'manual', $stageId, admin_nullable_id('owner_id') ?? $userId,
                admin_nullable_id('contact_id'), admin_nullable_id('company_id'),
            ];
            if ($fields[0] === '') {
                throw new RuntimeException('Le nom de l’opportunité est obligatoire.');
            }
            $closedAt = ($stage['is_won'] || $stage['is_lost']) ? crm_now() : null;
            if ($id > 0) {
                crm_execute(
                    'UPDATE opportunities SET name=?,service=?,description=?,value=?,currency=?,probability=?,
                     expected_close_date=?,next_action=?,source=?,stage_id=?,owner_id=?,contact_id=?,company_id=?,
                     closed_at=?,updated_at=? WHERE id=? AND deleted_at IS NULL',
                    [...$fields, $closedAt, crm_now(), $id]
                );
                crm_audit('update', 'opportunity', $id, 'Opportunité modifiée', ['stage_id' => $stageId], $userId);
            } else {
                crm_execute(
                    'INSERT INTO opportunities(uid,name,service,description,value,currency,probability,expected_close_date,
                     next_action,source,stage_id,owner_id,contact_id,company_id,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [crm_uid(), ...$fields, crm_now(), crm_now()]
                );
                $id = crm_last_id();
                crm_audit('create', 'opportunity', $id, 'Opportunité créée', [], $userId);
            }
            if (!$wasWon && (int)$stage['is_won'] === 1) {
                crm_run_automations('opportunity.won', [
                    'opportunity_id' => $id, 'name' => $fields[0], 'owner_id' => $fields[10],
                    'contact_id' => $fields[11], 'company_id' => $fields[12],
                ]);
            }
            cms_flash('success', 'L’opportunité a été enregistrée.');
            admin_redirect('view=pipeline&id=' . $id);
        }

        if ($action === 'move_opportunity') {
            $id = (int)($_POST['id'] ?? 0);
            $stageId = (int)($_POST['stage_id'] ?? 0);
            $previousStage = crm_fetch_one(
                'SELECT s.is_won FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id
                 WHERE o.id=? AND o.deleted_at IS NULL',
                [$id]
            );
            $wasWon = (int)($previousStage['is_won'] ?? 0) === 1;
            $stage = crm_fetch_one('SELECT * FROM pipeline_stages WHERE id=? AND is_active=1', [$stageId]);
            if ($stage === null) {
                throw new RuntimeException('Étape invalide.');
            }
            crm_execute(
                'UPDATE opportunities SET stage_id=?,probability=?,closed_at=?,updated_at=? WHERE id=? AND deleted_at IS NULL',
                [$stageId, $stage['probability'], ($stage['is_won'] || $stage['is_lost']) ? crm_now() : null, crm_now(), $id]
            );
            crm_audit('stage', 'opportunity', $id, 'Étape modifiée : ' . $stage['name'], ['stage_id' => $stageId], $userId);
            if (!$wasWon && (int)$stage['is_won'] === 1) {
                $opportunity = crm_fetch_one('SELECT name,owner_id,contact_id,company_id FROM opportunities WHERE id=?', [$id]);
                if ($opportunity !== null) {
                    crm_run_automations('opportunity.won', [
                        'opportunity_id' => $id, 'name' => $opportunity['name'],
                        'owner_id' => $opportunity['owner_id'], 'contact_id' => $opportunity['contact_id'],
                        'company_id' => $opportunity['company_id'],
                    ]);
                }
            }
            cms_flash('success', 'L’opportunité a changé d’étape.');
            admin_redirect('view=pipeline&id=' . $id);
        }

        if ($action === 'save_task') {
            $id = (int)($_POST['id'] ?? 0);
            $status = array_key_exists(admin_input('status', 30), CRM_TASK_STATUSES) ? admin_input('status', 30) : 'open';
            $fields = [
                admin_input('title', 220), admin_input('description', 3000), $status,
                array_key_exists(admin_input('priority', 20), CRM_PRIORITIES) ? admin_input('priority', 20) : 'normal',
                admin_input('due_at', 30) ?: null, admin_input('recurrence', 60),
                admin_nullable_id('assigned_to') ?? $userId, admin_nullable_id('contact_id'),
                admin_nullable_id('company_id'), admin_nullable_id('lead_id'), admin_nullable_id('opportunity_id'),
            ];
            if ($fields[0] === '') {
                throw new RuntimeException('Le titre de la tâche est obligatoire.');
            }
            $completedAt = $status === 'completed' ? crm_now() : null;
            if ($id > 0) {
                crm_execute(
                    'UPDATE tasks SET title=?,description=?,status=?,priority=?,due_at=?,recurrence=?,assigned_to=?,
                     contact_id=?,company_id=?,lead_id=?,opportunity_id=?,completed_at=?,updated_at=? WHERE id=? AND deleted_at IS NULL',
                    [...$fields, $completedAt, crm_now(), $id]
                );
                crm_audit('update', 'task', $id, 'Tâche modifiée', [], $userId);
            } else {
                crm_execute(
                    'INSERT INTO tasks(uid,title,description,status,priority,due_at,recurrence,assigned_to,contact_id,
                     company_id,lead_id,opportunity_id,completed_at,created_by,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [crm_uid(), ...$fields, $completedAt, $userId, crm_now(), crm_now()]
                );
                $id = crm_last_id();
                crm_audit('create', 'task', $id, 'Tâche créée', [], $userId);
            }
            cms_flash('success', 'La tâche a été enregistrée.');
            admin_redirect('view=tasks&id=' . $id);
        }

        if ($action === 'save_activity') {
            $type = array_key_exists(admin_input('type', 30), CRM_ACTIVITY_TYPES) ? admin_input('type', 30) : 'note';
            $subject = admin_input('subject', 220);
            if ($subject === '') {
                throw new RuntimeException('L’objet de l’activité est obligatoire.');
            }
            crm_execute(
                'INSERT INTO activities(uid,type,subject,body,activity_at,due_at,created_by,assigned_to,contact_id,
                 company_id,lead_id,opportunity_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    crm_uid(), $type, $subject, admin_input('body', 5000),
                    admin_input('activity_at', 30) ?: crm_now(), admin_input('due_at', 30) ?: null,
                    $userId, admin_nullable_id('assigned_to'), admin_nullable_id('contact_id'),
                    admin_nullable_id('company_id'), admin_nullable_id('lead_id'), admin_nullable_id('opportunity_id'),
                    crm_now(), crm_now(),
                ]
            );
            crm_audit('create', 'activity', crm_last_id(), 'Activité ajoutée : ' . $subject, [], $userId);
            cms_flash('success', 'L’activité a été ajoutée.');
            admin_redirect('view=' . admin_record_link(admin_input('return_type', 30), admin_nullable_id('return_id')));
        }

        if ($action === 'save_communication') {
            $recipient = mb_strtolower(admin_input('recipient', 320));
            $subject = admin_input('subject', 220);
            $body = admin_input('body', 12000);
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Indiquez une adresse email destinataire valide.');
            }
            if ($subject === '' || $body === '') {
                throw new RuntimeException('L’objet et le message sont obligatoires.');
            }
            $fromEmail = mb_strtolower((string)crm_setting('email_from', ''));
            $fromName = trim((string)crm_setting('email_from_name', 'Cabinet Aiouez'));
            $deliveryRequested = isset($_POST['send_now']);
            $deliveryEnabled = crm_setting('email_delivery_enabled', '0') === '1';
            $deliveryStatus = 'logged';
            if ($deliveryRequested) {
                if (!$deliveryEnabled || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Configurez et activez l’envoi email dans Réglages → Intégrations.');
                }
                $safeName = str_replace(["\r", "\n"], '', $fromName);
                $safeFrom = str_replace(["\r", "\n"], '', $fromEmail);
                $headers = [
                    'From: ' . $safeName . ' <' . $safeFrom . '>',
                    'Reply-To: ' . $safeFrom,
                    'Content-Type: text/plain; charset=UTF-8',
                    'MIME-Version: 1.0',
                ];
                $sent = function_exists('mail') && @mail(
                    $recipient,
                    mb_encode_mimeheader($subject, 'UTF-8'),
                    $body,
                    implode("\r\n", $headers)
                );
                $deliveryStatus = $sent ? 'sent' : 'failed';
            }
            $communicationId = crm_create_communication([
                'channel' => 'email', 'direction' => 'outbound', 'subject' => $subject, 'body' => $body,
                'sender' => $fromEmail, 'recipients' => $recipient, 'delivery_status' => $deliveryStatus,
                'contact_id' => admin_nullable_id('contact_id'), 'company_id' => admin_nullable_id('company_id'),
                'lead_id' => admin_nullable_id('lead_id'), 'opportunity_id' => admin_nullable_id('opportunity_id'),
            ], $userId);
            if ($deliveryStatus === 'failed') {
                cms_flash('error', 'L’email n’a pas pu être remis. Il reste enregistré dans la chronologie.');
            } else {
                cms_flash('success', $deliveryStatus === 'sent' ? 'Email envoyé et enregistré.' : 'Email enregistré dans la chronologie.');
            }
            crm_audit('email', 'communication', $communicationId, 'Email sortant traité', ['status' => $deliveryStatus], $userId);
            admin_redirect('view=' . admin_record_link(admin_input('return_type', 30), admin_nullable_id('return_id')));
        }

        if ($action === 'toggle_onboarding_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $item = crm_fetch_one(
                'SELECT i.*,c.company_id FROM onboarding_items i JOIN onboarding_checklists c ON c.id=i.checklist_id WHERE i.id=?',
                [$itemId]
            );
            if ($item === null) {
                throw new RuntimeException('Élément d’onboarding introuvable.');
            }
            $completed = (int)$item['is_completed'] === 1 ? 0 : 1;
            crm_execute(
                'UPDATE onboarding_items SET is_completed=?,completed_at=?,updated_at=? WHERE id=?',
                [$completed, $completed ? crm_now() : null, crm_now(), $itemId]
            );
            $remaining = (int)(crm_fetch_one(
                'SELECT COUNT(*) AS total FROM onboarding_items WHERE checklist_id=? AND is_required=1 AND is_completed=0',
                [$item['checklist_id']]
            )['total'] ?? 0);
            crm_execute(
                'UPDATE onboarding_checklists SET status=?,updated_at=? WHERE id=?',
                [$remaining === 0 ? 'completed' : 'active', crm_now(), $item['checklist_id']]
            );
            crm_execute(
                'INSERT INTO activities(uid,type,subject,body,activity_at,created_by,company_id,created_at,updated_at)
                 VALUES(?,"system",?,?,?,?,?,?,?)',
                [
                    crm_uid(), ($completed ? 'Onboarding terminé · ' : 'Onboarding rouvert · ') . $item['title'],
                    $completed ? 'Élément marqué comme terminé.' : 'Élément remis à faire.',
                    crm_now(), $userId, $item['company_id'], crm_now(), crm_now(),
                ]
            );
            crm_audit('onboarding', 'company', (int)$item['company_id'], 'Checklist mise à jour', ['item_id' => $itemId, 'completed' => $completed], $userId);
            admin_redirect('view=companies&id=' . (int)$item['company_id']);
        }

        if ($action === 'save_onboarding_item') {
            $companyId = (int)($_POST['company_id'] ?? 0);
            $title = admin_input('title', 220);
            if ($companyId <= 0 || $title === '') {
                throw new RuntimeException('Le titre de l’étape est obligatoire.');
            }
            $checklistId = crm_ensure_company_onboarding($companyId, $userId);
            $position = (int)(crm_fetch_one('SELECT COALESCE(MAX(position),0) AS value FROM onboarding_items WHERE checklist_id=?', [$checklistId])['value'] ?? 0) + 1;
            crm_execute(
                'INSERT INTO onboarding_items(uid,checklist_id,title,category,position,is_required,is_completed,due_at,assigned_to,created_by,created_at,updated_at)
                 VALUES(?,?,?,?,?,1,0,?,?,?,?,?)',
                [
                    crm_uid(), $checklistId, $title, admin_input('category', 80) ?: 'administratif',
                    $position, admin_input('due_at', 20) ?: null, admin_nullable_id('assigned_to') ?? $userId,
                    $userId, crm_now(), crm_now(),
                ]
            );
            cms_flash('success', 'L’étape d’onboarding a été ajoutée.');
            admin_redirect('view=companies&id=' . $companyId);
        }

        if ($action === 'assign_tags') {
            $type = admin_input('record_type', 30);
            $recordId = (int)($_POST['record_id'] ?? 0);
            if (!in_array($type, ['lead', 'contact', 'company', 'opportunity'], true) || $recordId <= 0) {
                throw new RuntimeException('Fiche invalide pour les étiquettes.');
            }
            $tagIds = array_values(array_unique(array_filter(array_map(
                'intval',
                is_array($_POST['tag_ids'] ?? null) ? $_POST['tag_ids'] : []
            ))));
            crm_execute('DELETE FROM record_tags WHERE record_type=? AND record_id=?', [$type, $recordId]);
            foreach ($tagIds as $tagId) {
                if (crm_fetch_one('SELECT id FROM tags WHERE id=?', [$tagId]) !== null) {
                    crm_execute(
                        'INSERT INTO record_tags(tag_id,record_type,record_id,created_at) VALUES(?,?,?,?)',
                        [$tagId, $type, $recordId, crm_now()]
                    );
                }
            }
            crm_audit('tag', $type, $recordId, 'Étiquettes mises à jour', ['tag_ids' => $tagIds], $userId);
            cms_flash('success', 'Les étiquettes ont été mises à jour.');
            admin_redirect('view=' . admin_record_link($type, $recordId));
        }

        if ($action === 'upload_document') {
            crm_require_permission('documents.manage');
            $id = crm_store_document($_FILES['document'] ?? [], [
                'category' => admin_input('category', 50),
                'contact_id' => admin_nullable_id('contact_id'), 'company_id' => admin_nullable_id('company_id'),
                'lead_id' => admin_nullable_id('lead_id'), 'opportunity_id' => admin_nullable_id('opportunity_id'),
            ], $userId);
            cms_flash('success', 'Le document a été ajouté.');
            admin_redirect('view=documents&id=' . $id);
        }

        if ($action === 'archive_record') {
            crm_require_permission('records.archive');
            $type = admin_input('record_type', 30);
            $tables = ['lead' => 'leads', 'contact' => 'contacts', 'company' => 'companies', 'opportunity' => 'opportunities', 'task' => 'tasks'];
            if (!isset($tables[$type])) {
                throw new RuntimeException('Type de fiche invalide.');
            }
            $id = (int)($_POST['id'] ?? 0);
            crm_execute('UPDATE ' . $tables[$type] . ' SET deleted_at=?,updated_at=? WHERE id=?', [crm_now(), crm_now(), $id]);
            crm_audit('archive', $type, $id, 'Fiche archivée', [], $userId);
            cms_flash('success', 'La fiche a été archivée.');
            admin_redirect('view=' . ($type === 'opportunity' ? 'pipeline' : $type . 's'));
        }

        if ($action === 'save_settings') {
            if (($user['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Seul un administrateur peut modifier les réglages.');
            }
            foreach (['crm_name', 'currency', 'timezone', 'retention_days', 'lead_assignment', 'email_notifications'] as $key) {
                crm_set_setting($key, admin_input($key, 180), $userId);
            }
            crm_audit('update', 'settings', null, 'Paramètres CRM modifiés', [], $userId);
            cms_flash('success', 'Les paramètres ont été enregistrés.');
            admin_redirect('view=settings');
        }

        if ($action === 'save_integrations') {
            if (($user['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Seul un administrateur peut configurer les intégrations.');
            }
            $fromEmail = mb_strtolower(admin_input('email_from', 190));
            if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('L’adresse d’expédition n’est pas valide.');
            }
            crm_set_setting('email_from_name', admin_input('email_from_name', 160) ?: 'Cabinet Aiouez', $userId);
            crm_set_setting('email_from', $fromEmail, $userId);
            crm_set_setting('email_delivery_enabled', isset($_POST['email_delivery_enabled']) ? '1' : '0', $userId);
            crm_set_setting('calendar_feed_enabled', isset($_POST['calendar_feed_enabled']) ? '1' : '0', $userId);
            crm_audit('update', 'settings', null, 'Intégrations mises à jour', [], $userId);
            cms_flash('success', 'Les intégrations ont été enregistrées.');
            admin_redirect('view=settings&section=integrations');
        }

        if ($action === 'regenerate_calendar_feed') {
            if (($user['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Seul un administrateur peut renouveler le lien calendrier.');
            }
            crm_set_setting('calendar_feed_token', bin2hex(random_bytes(24)), $userId);
            crm_set_setting('calendar_feed_enabled', '1', $userId);
            crm_audit('security', 'settings', null, 'Lien calendrier renouvelé', [], $userId);
            cms_flash('success', 'Un nouveau lien calendrier privé a été généré.');
            admin_redirect('view=settings&section=integrations');
        }

        if ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $next = (string)($_POST['new_password'] ?? '');
            if (!password_verify($current, (string)$user['password_hash'])) {
                throw new RuntimeException('Le mot de passe actuel est incorrect.');
            }
            if (strlen($next) < 12 || !hash_equals($next, (string)($_POST['confirm_password'] ?? ''))) {
                throw new RuntimeException('Utilisez au moins 12 caractères et confirmez le nouveau mot de passe.');
            }
            $hash = password_hash($next, PASSWORD_DEFAULT);
            crm_execute('UPDATE users SET password_hash=?,updated_at=? WHERE id=?', [$hash, crm_now(), $userId]);
            file_put_contents(cms_private_dir() . '/password.hash', $hash, LOCK_EX);
            @chmod(cms_private_dir() . '/password.hash', 0640);
            crm_audit('password', 'user', $userId, 'Mot de passe modifié', [], $userId);
            cms_flash('success', 'Votre mot de passe a été modifié.');
            admin_redirect('view=settings');
        }

        if ($action === 'save_user') {
            if (($user['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Seul un administrateur peut gérer les utilisateurs.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $role = array_key_exists(admin_input('role', 30), CRM_ROLES) ? admin_input('role', 30) : 'viewer';
            if ($id > 0) {
                crm_execute(
                    'UPDATE users SET username=?,full_name=?,email=?,role=?,is_active=?,updated_at=? WHERE id=?',
                    [admin_input('username', 100), admin_input('full_name', 160), admin_input('email', 190), $role, isset($_POST['is_active']) ? 1 : 0, crm_now(), $id]
                );
            } else {
                $password = (string)($_POST['password'] ?? '');
                if (strlen($password) < 12) {
                    throw new RuntimeException('Le mot de passe doit contenir au moins 12 caractères.');
                }
                crm_execute(
                    'INSERT INTO users(uid,username,full_name,email,password_hash,role,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)',
                    [crm_uid(), admin_input('username', 100), admin_input('full_name', 160), admin_input('email', 190), password_hash($password, PASSWORD_DEFAULT), $role, 1, crm_now(), crm_now()]
                );
                $id = crm_last_id();
            }
            crm_audit('save', 'user', $id, 'Utilisateur enregistré', [], $userId);
            cms_flash('success', 'L’utilisateur a été enregistré.');
            admin_redirect('view=settings&section=users');
        }

        if ($action === 'save_tag') {
            if (($user['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Seul un administrateur peut gérer les étiquettes.');
            }
            $name = admin_input('name', 80);
            if ($name === '') {
                throw new RuntimeException('Le nom de l’étiquette est obligatoire.');
            }
            crm_execute('INSERT INTO tags(uid,name,color,created_at) VALUES(?,?,?,?)', [crm_uid(), $name, admin_input('color', 20) ?: '#0f7fa6', crm_now()]);
            cms_flash('success', 'L’étiquette a été créée.');
            admin_redirect('view=settings&section=tags');
        }

        if ($action === 'save_template') {
            crm_require_permission('automations.manage');
            crm_execute(
                'INSERT INTO email_templates(uid,name,subject,body,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',
                [crm_uid(), admin_input('name', 160), admin_input('subject', 220), admin_input('body', 8000), $userId, crm_now(), crm_now()]
            );
            cms_flash('success', 'Le modèle a été créé.');
            admin_redirect('view=settings&section=templates');
        }

        if ($action === 'save_automation') {
            crm_require_permission('automations.manage');
            crm_execute(
                'INSERT INTO automation_rules(uid,name,trigger_event,conditions_json,actions_json,is_active,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?)',
                [
                    crm_uid(), admin_input('name', 160), admin_input('trigger_event', 80), '{}',
                    json_encode([
                        'action' => admin_input('rule_action', 80),
                        'delay_days' => max(0, min(365, (int)($_POST['delay_days'] ?? 2))),
                        'task_title' => admin_input('task_title', 220) ?: 'Relancer · {{name}}',
                        'priority' => in_array(admin_input('priority', 20), ['low','normal','high','urgent'], true)
                            ? admin_input('priority', 20) : 'normal',
                    ], JSON_THROW_ON_ERROR),
                    1, crm_now(), crm_now(),
                ]
            );
            cms_flash('success', 'La règle d’automatisation a été créée.');
            admin_redirect('view=settings&section=automation');
        }

        if ($action === 'toggle_automation') {
            crm_require_permission('automations.manage');
            $id = (int)($_POST['id'] ?? 0);
            crm_execute('UPDATE automation_rules SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?', [crm_now(), $id]);
            crm_audit('toggle', 'automation_rule', $id, 'Automatisation activée/désactivée', [], $userId);
            cms_flash('success', 'Le statut de l’automatisation a été modifié.');
            admin_redirect('view=settings&section=automation');
        }

        if ($action === 'mark_notifications') {
            crm_execute('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL', [crm_now(), $userId]);
            admin_redirect('view=notifications');
        }

        if ($action === 'import_csv') {
            crm_require_permission('records.create');
            $file = $_FILES['csv'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('Choisissez un fichier CSV valide de moins de 5 Mo.');
            }
            $handle = fopen((string)$file['tmp_name'], 'rb');
            $header = fgetcsv($handle, 0, ';') ?: [];
            $header = array_map(static fn ($value): string => mb_strtolower(trim((string)$value)), $header);
            $count = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false && $count < 5000) {
                $item = array_combine($header, array_pad($row, count($header), ''));
                if (!is_array($item) || trim((string)($item['nom'] ?? '')) === '') {
                    continue;
                }
                crm_create_lead([
                    'name' => $item['nom'], 'company_name' => $item['entreprise'] ?? '', 'email' => $item['email'] ?? '',
                    'phone' => $item['téléphone'] ?? ($item['telephone'] ?? ''), 'service' => $item['service'] ?? '',
                    'source' => 'import', 'owner_id' => $userId,
                ], $userId);
                $count++;
            }
            fclose($handle);
            cms_flash('success', $count . ' lead(s) importé(s).');
            admin_redirect('view=leads');
        }
    } catch (Throwable $error) {
        error_log('[Aiouez CRM admin] ' . $error->getMessage());
        cms_flash('error', $error->getMessage());
        admin_redirect('view=' . admin_input('return_view', 30));
    }
}

function admin_handle_downloads(array $user): void
{
    if (isset($_GET['document'])) {
        crm_require_permission('records.view');
        $document = crm_fetch_one('SELECT * FROM documents WHERE id=? AND deleted_at IS NULL', [(int)$_GET['document']]);
        if ($document === null) {
            http_response_code(404);
            exit('Document introuvable.');
        }
        $path = crm_documents_dir() . '/' . basename((string)$document['stored_name']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string)$document['original_name']) . '"');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    $export = (string)($_GET['export'] ?? '');
    if ($export === '') {
        return;
    }
    crm_require_permission('exports.run');
    $queries = [
        'leads' => ['SELECT name,company_name,email,phone,service,status,priority,source,estimated_value,currency,created_at FROM leads WHERE deleted_at IS NULL ORDER BY created_at DESC', ['Nom','Entreprise','Email','Téléphone','Service','Statut','Priorité','Source','Valeur','Devise','Créé le']],
        'contacts' => ['SELECT first_name,last_name,email,phone,mobile,job_title,status,source,created_at FROM contacts WHERE deleted_at IS NULL ORDER BY created_at DESC', ['Prénom','Nom','Email','Téléphone','Mobile','Fonction','Statut','Source','Créé le']],
        'companies' => ['SELECT name,legal_name,industry,email,phone,city,country,status,created_at FROM companies WHERE deleted_at IS NULL ORDER BY created_at DESC', ['Nom','Raison sociale','Secteur','Email','Téléphone','Ville','Pays','Statut','Créé le']],
        'opportunities' => ['SELECT o.name,o.service,o.value,o.currency,o.probability,s.name AS stage,o.expected_close_date,o.created_at FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.deleted_at IS NULL ORDER BY o.created_at DESC', ['Nom','Service','Valeur','Devise','Probabilité','Étape','Clôture prévue','Créé le']],
    ];
    if (!isset($queries[$export])) {
        return;
    }
    [$sql, $headers] = $queries[$export];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aiouez-' . $export . '-' . gmdate('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    foreach (crm_fetch_all($sql) as $row) {
        fputcsv($output, array_values($row), ';');
    }
    fclose($output);
    crm_audit('export', $export, null, 'Export CSV', [], (int)$user['id']);
    exit;
}
