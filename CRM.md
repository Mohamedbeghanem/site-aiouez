# Cabinet Aiouez CRM

The CRM is served from `/admin/`. Public contact requests are accepted by
`/api/contact.php` and become relational leads immediately.

## Storage

By default the CRM uses SQLite at:

`private/cms/crm.sqlite`

That directory is outside the public web root. Uploaded documents are stored in
`private/cms/documents/`. The original JSON form submissions remain in
`private/cms/submissions/` as migration-period compatibility copies.

SQLite uses foreign keys, WAL journaling, a five-second busy timeout, automatic
schema migration, and hourly reminder/retention maintenance.

MySQL is also supported. Add this block to `private/cms/config.php`:

```php
'database' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'database_name',
    'username' => 'database_user',
    'password' => 'database_password',
],
```

## Roles

- Administrator: all records, users, configuration, security, and exports.
- Manager: records, archive, reports, exports, documents, and automations.
- Collaborator: create and update operational records and documents.
- Read only: dashboards, records, and reports without mutations.

## Included workflows

- Website and manual lead capture
- Lead qualification and conversion
- Contact and company records
- Opportunity pipeline and weighted forecast
- Tasks, due-date reminders, recurrence metadata, and activities
- Authenticated private document uploads/downloads
- Notifications and an audit trail
- CSV lead import and CSV data exports
- Email templates, tags, and event-based automation rules
- Configurable retention for soft-deleted documents, read notifications, and
  audit history

## Operations

Before a deployment, copy `private/cms/` and the current `public_html` release
to the domain backup directory. Never place the database, configuration, or
uploaded documents under `public_html`.

Run local checks with:

```bash
php tests/crm-integration.php
php tests/admin-render.php dashboard
npm run build
```
