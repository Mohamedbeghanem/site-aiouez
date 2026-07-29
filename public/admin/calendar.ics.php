<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_crm.php';

crm_db();
$provided = (string)($_GET['token'] ?? '');
$stored = (string)crm_setting('calendar_feed_token', '');
if (
    crm_setting('calendar_feed_enabled', '0') !== '1'
    || $stored === ''
    || $provided === ''
    || !hash_equals($stored, $provided)
) {
    http_response_code(404);
    exit;
}

function ics_text(string $value): string
{
    return str_replace(
        ["\\", ",", ";", "\r\n", "\r", "\n"],
        ["\\\\", "\\,", "\\;", "\\n", "\\n", "\\n"],
        $value
    );
}

function ics_time(?string $value): string
{
    if (!$value) {
        return gmdate('Ymd\THis\Z');
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    } catch (Throwable) {
        return gmdate('Ymd\THis\Z');
    }
}

$events = [];
$cutoff = gmdate('Y-m-d H:i:s', time() - 90 * 86400);
foreach (crm_fetch_all(
    'SELECT t.uid,t.title,t.description,t.due_at,t.updated_at,u.full_name
     FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to
     WHERE t.deleted_at IS NULL AND t.status NOT IN ("cancelled") AND t.due_at IS NOT NULL AND t.due_at>=?
     ORDER BY t.due_at LIMIT 1000',
    [$cutoff]
) as $task) {
    $events[] = [
        'uid' => $task['uid'], 'summary' => $task['title'],
        'description' => trim((string)$task['description'] . "\nResponsable : " . ($task['full_name'] ?: 'Non attribué')),
        'start' => $task['due_at'], 'updated' => $task['updated_at'], 'duration' => 'PT30M',
        'url' => 'https://aiouez.com/admin/?view=tasks',
    ];
}
foreach (crm_fetch_all(
    'SELECT a.uid,a.subject,a.body,a.activity_at,a.updated_at
     FROM activities a WHERE a.deleted_at IS NULL AND a.type="meeting" AND a.activity_at>=?
     ORDER BY a.activity_at LIMIT 1000',
    [$cutoff]
) as $activity) {
    $events[] = [
        'uid' => $activity['uid'], 'summary' => $activity['subject'], 'description' => $activity['body'],
        'start' => $activity['activity_at'], 'updated' => $activity['updated_at'], 'duration' => 'PT1H',
        'url' => 'https://aiouez.com/admin/?view=activities',
    ];
}

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="aiouez-crm.ics"');
header('Cache-Control: private, max-age=300');
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Cabinet Aiouez//AIOUEZ CRM//FR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:AIOUEZ CRM\r\n";
foreach ($events as $event) {
    echo "BEGIN:VEVENT\r\n";
    echo 'UID:' . ics_text((string)$event['uid']) . "@aiouez.com\r\n";
    echo 'DTSTAMP:' . ics_time((string)$event['updated']) . "\r\n";
    echo 'DTSTART:' . ics_time((string)$event['start']) . "\r\n";
    echo 'DURATION:' . $event['duration'] . "\r\n";
    echo 'SUMMARY:' . ics_text((string)$event['summary']) . "\r\n";
    echo 'DESCRIPTION:' . ics_text((string)$event['description']) . "\r\n";
    echo 'URL:' . $event['url'] . "\r\n";
    echo "END:VEVENT\r\n";
}
echo "END:VCALENDAR\r\n";
