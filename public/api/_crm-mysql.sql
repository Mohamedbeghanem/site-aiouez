CREATE TABLE IF NOT EXISTS schema_migrations (
  version INT PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  username VARCHAR(120) NOT NULL UNIQUE,
  full_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'collaborator',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notification_email TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  legal_name VARCHAR(255) NULL,
  industry VARCHAR(160) NULL,
  website VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(80) NULL,
  address TEXT NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(120) NOT NULL DEFAULT 'Algérie',
  tax_id VARCHAR(120) NULL,
  registration_number VARCHAR(120) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'prospect',
  owner_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_companies_name(name),
  CONSTRAINT fk_companies_owner FOREIGN KEY(owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL DEFAULT '',
  email VARCHAR(190) NULL,
  phone VARCHAR(80) NULL,
  mobile VARCHAR(80) NULL,
  job_title VARCHAR(160) NULL,
  preferred_language VARCHAR(10) NOT NULL DEFAULT 'fr',
  address TEXT NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(120) NOT NULL DEFAULT 'Algérie',
  source VARCHAR(80) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'prospect',
  owner_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_contacts_email(email),
  INDEX idx_contacts_company(company_id),
  CONSTRAINT fk_contacts_owner FOREIGN KEY(owner_id) REFERENCES users(id),
  CONSTRAINT fk_contacts_company FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pipeline_stages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  position INT NOT NULL,
  probability INT NOT NULL DEFAULT 0,
  color VARCHAR(20) NOT NULL DEFAULT '#0f7fa6',
  is_won TINYINT(1) NOT NULL DEFAULT 0,
  is_lost TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  legacy_id VARCHAR(64) NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  company_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(80) NULL,
  service VARCHAR(160) NULL,
  message TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'new',
  priority VARCHAR(40) NOT NULL DEFAULT 'normal',
  source VARCHAR(80) NOT NULL DEFAULT 'manual',
  estimated_value DECIMAL(18,2) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'DZD',
  owner_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  converted_opportunity_id BIGINT UNSIGNED NULL,
  lost_reason TEXT NULL,
  converted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_leads_status(status),
  INDEX idx_leads_email(email),
  INDEX idx_leads_owner(owner_id),
  CONSTRAINT fk_leads_owner FOREIGN KEY(owner_id) REFERENCES users(id),
  CONSTRAINT fk_leads_contact FOREIGN KEY(contact_id) REFERENCES contacts(id),
  CONSTRAINT fk_leads_company FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  service VARCHAR(160) NULL,
  description TEXT NULL,
  value DECIMAL(18,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'DZD',
  probability INT NOT NULL DEFAULT 10,
  expected_close_date DATE NULL,
  next_action TEXT NULL,
  source VARCHAR(80) NULL,
  lost_reason TEXT NULL,
  stage_id BIGINT UNSIGNED NOT NULL,
  owner_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  lead_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_opportunities_stage(stage_id),
  INDEX idx_opportunities_owner(owner_id),
  CONSTRAINT fk_opportunities_stage FOREIGN KEY(stage_id) REFERENCES pipeline_stages(id),
  CONSTRAINT fk_opportunities_owner FOREIGN KEY(owner_id) REFERENCES users(id),
  CONSTRAINT fk_opportunities_contact FOREIGN KEY(contact_id) REFERENCES contacts(id),
  CONSTRAINT fk_opportunities_company FOREIGN KEY(company_id) REFERENCES companies(id),
  CONSTRAINT fk_opportunities_lead FOREIGN KEY(lead_id) REFERENCES leads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'open',
  priority VARCHAR(40) NOT NULL DEFAULT 'normal',
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  recurrence VARCHAR(80) NULL,
  assigned_to BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  lead_id BIGINT UNSIGNED NULL,
  opportunity_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_tasks_due(due_at, status),
  CONSTRAINT fk_tasks_assignee FOREIGN KEY(assigned_to) REFERENCES users(id),
  CONSTRAINT fk_tasks_creator FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_tasks_contact FOREIGN KEY(contact_id) REFERENCES contacts(id),
  CONSTRAINT fk_tasks_company FOREIGN KEY(company_id) REFERENCES companies(id),
  CONSTRAINT fk_tasks_lead FOREIGN KEY(lead_id) REFERENCES leads(id),
  CONSTRAINT fk_tasks_opportunity FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  type VARCHAR(40) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NULL,
  activity_at DATETIME NOT NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  assigned_to BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  lead_id BIGINT UNSIGNED NULL,
  opportunity_id BIGINT UNSIGNED NULL,
  task_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_activities_links(contact_id, company_id, lead_id, opportunity_id),
  CONSTRAINT fk_activities_creator FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_activities_assignee FOREIGN KEY(assigned_to) REFERENCES users(id),
  CONSTRAINT fk_activities_contact FOREIGN KEY(contact_id) REFERENCES contacts(id),
  CONSTRAINT fk_activities_company FOREIGN KEY(company_id) REFERENCES companies(id),
  CONSTRAINT fk_activities_lead FOREIGN KEY(lead_id) REFERENCES leads(id),
  CONSTRAINT fk_activities_opportunity FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
  CONSTRAINT fk_activities_task FOREIGN KEY(task_id) REFERENCES tasks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL UNIQUE,
  mime_type VARCHAR(160) NOT NULL,
  size_bytes BIGINT NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'other',
  uploaded_by BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  lead_id BIGINT UNSIGNED NULL,
  opportunity_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_documents_uploader FOREIGN KEY(uploaded_by) REFERENCES users(id),
  CONSTRAINT fk_documents_contact FOREIGN KEY(contact_id) REFERENCES contacts(id),
  CONSTRAINT fk_documents_company FOREIGN KEY(company_id) REFERENCES companies(id),
  CONSTRAINT fk_documents_lead FOREIGN KEY(lead_id) REFERENCES leads(id),
  CONSTRAINT fk_documents_opportunity FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT '#0f7fa6',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_tags (
  tag_id BIGINT UNSIGNED NOT NULL,
  record_type VARCHAR(40) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY(tag_id, record_type, record_id),
  CONSTRAINT fk_record_tags_tag FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_templates_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  link VARCHAR(500) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  trigger_event VARCHAR(120) NOT NULL,
  conditions_json JSON NOT NULL,
  actions_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(160) PRIMARY KEY,
  value TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_settings_user FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(24) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  record_type VARCHAR(80) NULL,
  record_id BIGINT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL,
  changes_json JSON NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_created(created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version, applied_at) VALUES(1, UTC_TIMESTAMP());
