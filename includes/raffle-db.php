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
    $name = str_replace(['`', "\0"], '', (string) ($config['name'] ?? ''));
    $user = (string) ($config['user'] ?? '');
    $pass = (string) ($config['pass'] ?? '');
    if ($name === '' || $user === '') {
        throw new RuntimeException('bad-config');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $dsns = [
        'mysql:host=localhost;charset=utf8mb4',
        'mysql:host=localhost;port=3306;charset=utf8mb4',
        'mysql:unix_socket=/var/lib/mysql/mysql.sock;charset=utf8mb4',
        'mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4',
        'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
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

function panel_ensure_schema(PDO $pdo) {
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
