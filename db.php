<?php
/**
 * 数据库初始化与连接管理
 * Expiration Reminder - 到期提醒系统
 */

define('DB_PATH', __DIR__ . '/data/database.sqlite');

function getDb(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        initDatabase($pdo);
    }
    return $pdo;
}

function initDatabase(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK(type IN ('server','certificate','icp','birthday','other')),
            name TEXT NOT NULL,
            details TEXT DEFAULT '{}',
            expiry_date TEXT NOT NULL,
            reminder_days TEXT NOT NULL DEFAULT '[30,15,7,1]',
            notify_email INTEGER DEFAULT 1,
            notes TEXT DEFAULT '',
            enabled INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now','localtime')),
            updated_at TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS email_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            reminder_day INTEGER NOT NULL,
            sent_date TEXT NOT NULL DEFAULT (date('now','localtime')),
            status TEXT DEFAULT 'success',
            message TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );

        CREATE INDEX IF NOT EXISTS idx_items_type ON items(type);
        CREATE INDEX IF NOT EXISTS idx_items_expiry ON items(expiry_date);
        CREATE INDEX IF NOT EXISTS idx_email_logs_item ON email_logs(item_id);
        CREATE INDEX IF NOT EXISTS idx_email_logs_sent ON email_logs(sent_date);
    ");
}

// 自动初始化
getDb();
