<?php
/**
 * DYVESTA — Personal File Audit
 * AUTHOR: Chanaka Sanjaya Bandara
 * Database connection (PDO / MySQL)
 *
 * Edit the four constants below to match your environment, or set them
 * as real environment variables on your server. Defaults match a typical
 * local XAMPP / WAMP / MAMP install.
 */

define('DB_HOST', getenv('DYVESTA_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DYVESTA_DB_NAME') ?: 'dyvesta_pfa');
define('DB_USER', getenv('DYVESTA_DB_USER') ?: 'root');
define('DB_PASS', getenv('DYVESTA_DB_PASS') ?: '1234');
define('DB_PORT', getenv('DYVESTA_DB_PORT') ?: '3306');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;color:#e8e6f0;background:#0d0b14">'
                . '<h2>Database connection failed</h2>'
                . '<p>Could not connect to MySQL. Please check <code>config/db.php</code> '
                . '(host, database, username, password) and make sure you have run '
                . '<code>database/schema.sql</code>.</p>'
                . '<p style="color:#9891ac">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }

    return $pdo;
}
