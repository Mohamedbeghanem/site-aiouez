<?php
declare(strict_types=1);

require __DIR__ . '/_crm.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    cms_json_response(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !in_array($origin, ['https://aiouez.com', 'https://www.aiouez.com'], true)) {
    cms_json_response(['ok' => false, 'message' => 'Origine non autorisée.'], 403);
}

if (!cms_rate_limit('contact', 5, 3600)) {
    cms_json_response([
        'ok' => false,
        'message' => 'Trop de demandes ont été envoyées. Veuillez réessayer plus tard.',
    ], 429);
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if (str_contains($contentType, 'application/json')) {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : [];
} else {
    $payload = $_POST;
}

if (cms_clean_text($payload['website'] ?? '', 200) !== '') {
    cms_json_response(['ok' => true, 'message' => 'Votre demande a bien été reçue.']);
}

$name = cms_clean_text($payload['name'] ?? '', 120);
$companyName = cms_clean_text($payload['company'] ?? '', 160);
$email = mb_strtolower(cms_clean_text($payload['email'] ?? '', 190));
$phone = cms_clean_text($payload['phone'] ?? '', 50);
$need = cms_clean_text($payload['need'] ?? '', 120);
$message = cms_clean_text($payload['message'] ?? '', 3000);

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['name'] = 'Indiquez votre nom et prénom.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Indiquez une adresse email valide.';
}
$allowedNeeds = [
    'Commissariat aux comptes',
    'Expertise comptable',
    'Fiscalité',
    'Conseil en gestion',
    'Autre demande',
];
if (!in_array($need, $allowedNeeds, true)) {
    $errors['need'] = 'Sélectionnez une expertise.';
}
if ($message !== '' && mb_strlen($message) < 10) {
    $errors['message'] = 'Ajoutez un peu plus de détails ou laissez ce champ vide.';
}

if ($errors !== []) {
    cms_json_response([
        'ok' => false,
        'message' => 'Vérifiez les champs indiqués.',
        'errors' => $errors,
    ], 422);
}

$publicId = bin2hex(random_bytes(12));
$submission = [
    'id' => $publicId,
    'created_at' => gmdate('c'),
    'updated_at' => gmdate('c'),
    'status' => 'new',
    'name' => $name,
    'company' => $companyName,
    'email' => $email,
    'phone' => $phone,
    'need' => $need,
    'message' => $message,
    'notes' => '',
    'source' => 'aiouez.com',
];

try {
    $owner = crm_fetch_one(
        'SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY CASE role WHEN "admin" THEN 0 WHEN "manager" THEN 1 ELSE 2 END, id LIMIT 1'
    );
    $contact = crm_find_contact_by_identity($email, $phone);
    $matchedCompany = crm_find_company_by_name($companyName);
    $leadId = crm_create_lead([
        'legacy_id' => $publicId,
        'name' => $name,
        'company_name' => $matchedCompany['name'] ?? $companyName,
        'email' => $email,
        'phone' => $phone,
        'service' => $need,
        'message' => $message,
        'status' => 'new',
        'priority' => 'normal',
        'source' => 'website',
        'owner_id' => $owner['id'] ?? null,
        'contact_id' => $contact['id'] ?? null,
        'company_id' => $matchedCompany['id'] ?? null,
    ]);
    crm_notify_users(
        'lead.new',
        'Nouvelle demande',
        $name . ' · ' . $need,
        '/admin/?view=leads&id=' . $leadId
    );
    crm_run_automations('lead.created', [
        'lead_id' => $leadId,
        'owner_id' => $owner['id'] ?? null,
        'name' => $name,
    ]);
} catch (Throwable $error) {
    error_log('[Aiouez CMS] ' . $error->getMessage());
    cms_json_response([
        'ok' => false,
        'message' => 'La demande n’a pas pu être enregistrée. Contactez-nous par téléphone.',
    ], 500);
}

// Keep an immutable legacy copy during the relational migration window. The CRM
// remains the system of record if this compatibility write ever fails.
try {
    cms_save_submission($submission);
} catch (Throwable $error) {
    error_log('[Aiouez CMS legacy copy] ' . $error->getMessage());
}

$config = cms_config();
$notify = (string)($config['notify_email'] ?? '');
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $subject = 'Nouvelle demande — ' . $need;
    $mailBody = "Nouvelle demande reçue sur aiouez.com\n\n"
        . "Nom : $name\nEntreprise : $companyName\nEmail : $email\nTéléphone : $phone\n"
        . "Besoin : $need\n\nMessage :\n$message\n\n"
        . 'Consulter : https://aiouez.com/admin/?view=leads&id=' . $leadId;
    $headers = [
        'From: Cabinet Aiouez <no-reply@aiouez.com>',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
    ];
    @mail($notify, '=?UTF-8?B?' . base64_encode($subject) . '?=', $mailBody, implode("\r\n", $headers));
}

cms_json_response([
    'ok' => true,
    'message' => 'Merci. Votre demande a bien été transmise au cabinet.',
    'reference' => strtoupper(substr((string)$submission['id'], -6)),
], 201);
