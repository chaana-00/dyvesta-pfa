-- =====================================================================
--  DYVESTA — Personal File Audit (PFA)
--  AUTHOR: Chanaka Sanjaya Bandara
--  Database schema
--  Charset: utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `dyvesta_pfa` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dyvesta_pfa`;

-- ---------------------------------------------------------------------
-- Settings (key/value store — active audit cycle name, app name, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (`key`, `value`) VALUES
  ('active_cycle', 'Personal File Audit — 2026'),
  ('app_name', 'DYVESTA')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ---------------------------------------------------------------------
-- Users (admins + auditors log in here)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  emp_no        VARCHAR(20)  NOT NULL UNIQUE,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','auditor') NOT NULL DEFAULT 'auditor',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Employees (Employee Master)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  emp_no        VARCHAR(20)  NOT NULL UNIQUE,
  name          VARCHAR(150) NOT NULL,
  designation   VARCHAR(150) DEFAULT NULL,
  supervisor_id INT DEFAULT NULL,           -- FK -> users.id (the auditor)
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_emp_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Audit Checklist Master (the standardized checklist)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS checklist_items (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  code               VARCHAR(20)  DEFAULT NULL,
  short_name         VARCHAR(80)  NOT NULL,
  audit_item         VARCHAR(255) NOT NULL,
  category           VARCHAR(100) DEFAULT NULL,
  answer_type        ENUM('yes_no_na','yes_no') NOT NULL DEFAULT 'yes_no_na',
  remark_required_on VARCHAR(20) NOT NULL DEFAULT 'No',
  sort_order         INT NOT NULL DEFAULT 0,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_audit_item (audit_item)
) ENGINE=InnoDB;

-- Default 6-point checklist (structural configuration shipped with the app —
-- no personal / employee data, just the audit template itself)
INSERT INTO checklist_items (code, short_name, audit_item, category, answer_type, remark_required_on, sort_order) VALUES
 ('CHK-001','Personal File','Availability of the personal file','Documentation','yes_no_na','No',1),
 ('CHK-002','LOA','Availability of the Letter of Appointment (LOA)','Documentation','yes_no_na','No',2),
 ('CHK-003','JD','Availability of the JD','Documentation','yes_no_na','No',3),
 ('CHK-004','Designation Accuracy','Accuracy of the designation in the HRIS, based on the latest correspondence','HRIS Accuracy','yes_no_na','No',4),
 ('CHK-005','Name & ID Accuracy','Accuracy of the employee''s name and ID details in the HRIS, as per the NIC','HRIS Accuracy','yes_no_na','No',5),
 ('CHK-006','Employment Type Accuracy','Accuracy of the employment type, based on the latest correspondence','HRIS Accuracy','yes_no_na','No',6)
ON DUPLICATE KEY UPDATE short_name = VALUES(short_name);

-- ---------------------------------------------------------------------
-- Allocations — "who audits whom", one row per employee per cycle
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS allocations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  auditor_id  INT NOT NULL,
  cycle       VARCHAR(100) NOT NULL,
  status      ENUM('not_started','in_progress','completed','issues_found') NOT NULL DEFAULT 'not_started',
  progress    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_employee_cycle (employee_id, cycle),
  CONSTRAINT fk_alloc_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_alloc_auditor  FOREIGN KEY (auditor_id)  REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Audit Answers — one row per allocation per checklist item
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_answers (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  allocation_id      INT NOT NULL,
  checklist_item_id  INT NOT NULL,
  answer             ENUM('yes','no','na') DEFAULT NULL,
  remark             TEXT DEFAULT NULL,
  answered_at        TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uniq_alloc_item (allocation_id, checklist_item_id),
  CONSTRAINT fk_ans_allocation FOREIGN KEY (allocation_id)     REFERENCES allocations(id)     ON DELETE CASCADE,
  CONSTRAINT fk_ans_item       FOREIGN KEY (checklist_item_id) REFERENCES checklist_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTE: No admin user is seeded here on purpose (a bcrypt hash written
-- by hand can't be guaranteed to match a password). The very first time
-- the app is opened with an empty `users` table it will redirect to
-- setup.php, a one-time "Create the first admin account" screen that
-- calls PHP's own password_hash() on your server — pre-filled with the
-- name "Chanaka". Use that to create the login. See README.md.
-- ---------------------------------------------------------------------
