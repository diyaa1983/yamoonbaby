<?php
function raffle_root() {
    return dirname(__DIR__);
}

function raffle_config_file() {
    $root = raffle_root();
    foreach ([
        $root . '/db-config.php',
        $root . '/includes/db-config.php',
    ] as $file) {
        if (is_readable($file)) {
            return $file;
        }
    }
    return '';
}

function raffle_pdo() {
    $configFile = raffle_config_file();
    if ($configFile === '') {
        throw new RuntimeException('missing-config');
    }
    $raw = (string) file_get_contents($configFile);
    if (strpos($raw, '<?php') === false || !preg_match('/\]\s*;/', $raw)) {
        throw new RuntimeException('bad-config');
    }
    $config = include $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('bad-config');
    }
    $name = trim(str_replace(['`', "\0"], '', (string) ($config['name'] ?? '')));
    $user = trim((string) ($config['user'] ?? ''));
    $pass = (string) ($config['pass'] ?? '');
    $pass = str_replace("\0", '', $pass);
    $pass = trim($pass, " \t\n\r\0\x0B\"'");
    if ($name === '' || $user === '') {
        throw new RuntimeException('bad-config');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $dsns = [
        'mysql:host=localhost;charset=utf8mb4',
        'mysql:unix_socket=/var/lib/mysql/mysql.sock;charset=utf8mb4',
        'mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4',
        'mysql:host=localhost;port=3306;charset=utf8mb4',
    ];
    $host = (string) ($config['host'] ?? 'localhost');
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        array_unshift($dsns, 'mysql:host=' . $host . ';charset=utf8mb4');
    }
    $last = null;
    foreach (array_unique($dsns) as $dsn) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec('USE `' . $name . '`');
            return $pdo;
        } catch (PDOException $e) {
            $last = $e;
        }
    }
    $driver = (int) ($last && isset($last->errorInfo[1]) ? $last->errorInfo[1] : 0);
    throw new RuntimeException('pdo-' . $driver, 0, $last);
}

function raffle_coupon_used(PDO $pdo, $coupon) {
    $stmt = $pdo->prepare('SELECT 1 FROM raffle_entries WHERE coupon = ? LIMIT 1');
    $stmt->execute([$coupon]);
    return (bool) $stmt->fetchColumn();
}

function raffle_ensure_settings(PDO $pdo) {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS panel_settings (
            setting_key VARCHAR(64) NOT NULL,
            setting_value VARCHAR(512) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    try {
        $pdo->exec('ALTER TABLE panel_settings MODIFY setting_value VARCHAR(512) NOT NULL');
    } catch (Exception $e) {
        // already migrated
    }
}

function raffle_setting(PDO $pdo, $key, $default = '') {
    raffle_ensure_settings($pdo);
    $stmt = $pdo->prepare('SELECT setting_value FROM panel_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([(string) $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function raffle_set_setting(PDO $pdo, $key, $value) {
    raffle_ensure_settings($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO panel_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([(string) $key, (string) $value]);
}

function raffle_registration_open(PDO $pdo) {
    return raffle_setting($pdo, 'registration_open', '0') === '1';
}

function raffle_ensure_entries_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $columns = [
        'product_rating' => 'TINYINT UNSIGNED NULL',
        'attend_ceremony' => 'TINYINT(1) NULL',
    ];
    foreach ($columns as $name => $definition) {
        try {
            $pdo->exec('ALTER TABLE raffle_entries ADD COLUMN ' . $name . ' ' . $definition);
        } catch (Exception $e) {
            // already migrated
        }
    }
    $done = true;
}

function raffle_attend_label($value) {
    if ($value === null || $value === '') {
        return '—';
    }
    return ((int) $value) === 1 ? 'نعم' : 'لا';
}

function panel_ensure_schema(PDO $pdo) {
    raffle_ensure_entries_schema($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS panel_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(40) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM(\'admin\',\'user\') NOT NULL DEFAULT \'user\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS raffle_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor VARCHAR(40) NOT NULL,
            action VARCHAR(40) NOT NULL,
            coupon CHAR(6) DEFAULT NULL,
            entry_id INT UNSIGNED DEFAULT NULL,
            details VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    raffle_ensure_settings($pdo);
}

function panel_audit(PDO $pdo, $actor, $action, $coupon = null, $entryId = null, $details = '') {
    $stmt = $pdo->prepare(
        'INSERT INTO raffle_audit (actor, action, coupon, entry_id, details) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$actor, $action, $coupon, $entryId, $details]);
}

function raffle_entry_image($coupon, $phone) {
    $pattern = raffle_root() . DIRECTORY_SEPARATOR . 'Archive' . DIRECTORY_SEPARATOR . $coupon . '_' . $phone . '.*';
    $files = glob($pattern) ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return '';
}
