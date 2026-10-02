<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Creates the tables. Kept in sync with schema.sql (used for phpMyAdmin imports). */
final class Schema
{
    public static function create(PDO $db): void
    {
        $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $fk = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id $id,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_login_at DATETIME NULL
        )$suffix");

        $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id $id,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(64) NOT NULL,
            attempted_at DATETIME NOT NULL
        )$suffix");

        $db->exec("CREATE TABLE IF NOT EXISTS jobs (
            id $id,
            user_id $fk NOT NULL,
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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )$suffix");

        $db->exec("CREATE TABLE IF NOT EXISTS job_assets (
            id $id,
            job_id $fk NOT NULL,
            kind VARCHAR(8) NOT NULL,
            path VARCHAR(255) NOT NULL,
            mime VARCHAR(64) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            size_bytes INT NOT NULL,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
        )$suffix");

        if ($sqlite) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        foreach ([
            'CREATE INDEX idx_jobs_status ON jobs(status)',
            'CREATE INDEX idx_jobs_user ON jobs(user_id)',
            'CREATE INDEX idx_attempts_lookup ON login_attempts(attempted_at)',
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (\PDOException) {
                // Index already exists.
            }
        }
    }
}
