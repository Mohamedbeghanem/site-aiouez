<?php
declare(strict_types=1);

require dirname(__DIR__) . '/public/api/_cms.php';

function contact_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$valid = cms_validate_public_contact([
    'name' => 'Mohamed Lambedja',
    'company' => 'Grege',
    'email' => 'gfrge@gfmsf.com',
    'phone' => '+21379870654',
    'need' => 'Commissariat aux comptes',
    'message' => 'bonjour',
]);
contact_assert($valid['errors'] === [], 'A short optional message must not block a valid request.');
contact_assert($valid['data']['message'] === 'bonjour', 'The optional message must be preserved.');

$invalid = cms_validate_public_contact([
    'name' => 'M',
    'email' => 'not-an-email',
    'need' => 'Unknown service',
]);
contact_assert(isset($invalid['errors']['name']), 'The name error is missing.');
contact_assert(isset($invalid['errors']['email']), 'The email error is missing.');
contact_assert(isset($invalid['errors']['need']), 'The service error is missing.');

echo "Contact validation test passed\n";
