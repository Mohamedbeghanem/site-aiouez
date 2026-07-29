<?php
declare(strict_types=1);

require __DIR__ . '/_cms.php';

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
$company = cms_clean_text($payload['company'] ?? '', 160);
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

$submission = [
    'id' => bin2hex(random_bytes(12)),
    'created_at' => gmdate('c'),
    'updated_at' => gmdate('c'),
    'status' => 'new',
    'name' => $name,
    'company' => $company,
    'email' => $email,
    'phone' => $phone,
    'need' => $need,
    'message' => $message,
    'notes' => '',
    'source' => 'aiouez.com',
];

try {
    cms_save_submission($submission);
} catch (Throwable $error) {
    error_log('[Aiouez CMS] ' . $error->getMessage());
    cms_json_response([
        'ok' => false,
        'message' => 'La demande n’a pas pu être enregistrée. Contactez-nous par téléphone.',
    ], 500);
}

$config = cms_config();
$notify = (string)($config['notify_email'] ?? '');
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $subject = 'Nouvelle demande — ' . $need;
    $mailBody = "Nouvelle demande reçue sur aiouez.com\n\n"
        . "Nom : $name\nEntreprise : $company\nEmail : $email\nTéléphone : $phone\n"
        . "Besoin : $need\n\nMessage :\n$message\n\n"
        . 'Consulter : https://aiouez.com/admin/?id=' . $submission['id'];
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
