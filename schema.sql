-- Same tables install.php creates. Only needed if you prefer importing through phpMyAdmin;
-- then create the admin with `php cli/install.php` or install.php (it skips existing tables).
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_attempts_lookup (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  prompt TEXT NOT NULL,
  width INT NOT NULL,
  height INT NOT NULL,
  aspect_ratio VARCHAR(16) NOT NULL,
  duration INT NOT NULL,
  status VARCHAR(16) NOT NULL,
  provider VARCHAR(32) NOT NULL,
  provider_job_id VARCHAR(191) NULL,
  attempts INT NOT NULL DEFAULT 0,
  error TEXT NULL,
  output_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  completed_at DATETIME NULL,
  INDEX idx_jobs_status (status),
  INDEX idx_jobs_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_assets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  kind VARCHAR(8) NOT NULL,
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(64) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  size_bytes INT NOT NULL,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
