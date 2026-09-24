<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

require_once dirname(__DIR__) . '/auth.php';

const WIKI_PDF_APP_MAX_BYTES = 20971520;

function wiki_pdf_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function wiki_pdf_fail(string $message): void
{
    http_response_code(500);
    $safe = wiki_pdf_e($message);
    exit('<!doctype html><html lang="de"><meta charset="utf-8"><title>Fehler</title><p style="font-family:Arial,sans-serif">' . $safe . '</p></html>');
}

function wiki_pdf_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf';
}

function wiki_pdf_ensure_directory(): ?string
{
    $dir = wiki_pdf_directory();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return 'Der Ordner uploads/pdf konnte nicht angelegt werden.';
    }
    if (!is_writable($dir)) {
        return 'Der Ordner uploads/pdf ist nicht beschreibbar. Unter XAMPP braucht Apache Schreibrechte auf diesen Ordner.';
    }

    return null;
}

function wiki_pdf_path_is_inside(string $path, string $directory): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $directory = rtrim(str_replace('\\', '/', $directory), '/');
    if (str_contains($path, '/../') || str_ends_with($path, '/..') || str_contains($directory, '/../') || str_ends_with($directory, '/..')) {
        return false;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $directory = strtolower($directory);
    }

    return $path === $directory || str_starts_with($path, $directory . '/');
}

function wiki_pdf_is_stored_name(string $storedName): bool
{
    return preg_match('/^[a-f0-9]{32}\.pdf$/', $storedName) === 1;
}

function wiki_pdf_delete_stored_file(string $storedName): bool
{
    if (!wiki_pdf_is_stored_name($storedName)) {
        return false;
    }

    $dir = realpath(wiki_pdf_directory());
    if ($dir === false) {
        return false;
    }

    $path = $dir . DIRECTORY_SEPARATOR . $storedName;
    if (!file_exists($path) && !is_link($path)) {
        return true;
    }

    $parent = realpath(dirname($path));
    if ($parent === false || !wiki_pdf_path_is_inside($parent, $dir)) {
        return false;
    }

    return unlink($path);
}

function wiki_pdf_ini_bytes(string $value): int
{
    $value = trim($value);
    if (!preg_match('/^([\d.]+)\s*([kmg])?/i', $value, $matches)) {
        return 0;
    }

    $number = (float)$matches[1];
    $unit = strtolower($matches[2] ?? '');
    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }

    return max(0, (int)$number);
}

function wiki_pdf_max_bytes(): int
{
    $limit = WIKI_PDF_APP_MAX_BYTES;
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $serverLimit = wiki_pdf_ini_bytes((string)ini_get($key));
        if ($serverLimit > 0 && $serverLimit < $limit) {
            $limit = $serverLimit;
        }
    }

    return $limit;
}

function wiki_pdf_format_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

function wiki_pdf_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = dirname(__DIR__) . '/config.php';
    if (!is_file($configFile)) {
        wiki_pdf_fail('config.php fehlt. Kopiere config.example.php nach config.php und trage die lokalen Datenbankdaten ein.');
    }

    /** @var array{host:string,port:int,database:string,username:string,password:string,charset:string} $config */
    $config = require $configFile;

    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            ),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        wiki_pdf_ensure_table($pdo);
    } catch (PDOException $e) {
        wiki_pdf_fail('Datenbankverbindung fehlgeschlagen. Prüfe config.php, ob MySQL in XAMPP läuft, und importiere bei Bedarf database-pdf.sql in die Datenbank medizininformatik.');
    }

    return $pdo;
}

function wiki_pdf_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS wiki_pdfs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
            file_size INT UNSIGNED NOT NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wiki_pdfs_stored_name (stored_name),
            KEY idx_wiki_pdfs_title (title),
            KEY idx_wiki_pdfs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function wiki_pdf_flash(string $type, string $text): void
{
    start_secure_session();
    $_SESSION['pdf_flash'] = [
        'type' => $type === 'success' ? 'success' : 'error',
        'text' => $text,
    ];
}

function wiki_pdf_take_flash(): ?array
{
    start_secure_session();
    if (empty($_SESSION['pdf_flash']) || !is_array($_SESSION['pdf_flash'])) {
        return null;
    }

    $flash = $_SESSION['pdf_flash'];
    unset($_SESSION['pdf_flash']);

    $type = (string)($flash['type'] ?? 'error');
    $text = (string)($flash['text'] ?? '');
    if ($text === '') {
        return null;
    }

    return [
        'type' => $type === 'success' ? 'success' : 'error',
        'text' => $text,
    ];
}

function wiki_pdf_csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['pdf_csrf']) || !is_string($_SESSION['pdf_csrf'])) {
        $_SESSION['pdf_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pdf_csrf'];
}

function wiki_pdf_require_csrf(): void
{
    start_secure_session();
    $sent = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['pdf_csrf'] ?? '');
    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        wiki_pdf_flash('error', 'Ungültiges Formular. Bitte die Seite neu laden und erneut versuchen.');
        header('Location: index.php', true, 303);
        exit;
    }
}

function wiki_pdf_plain(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return trim($value);
}

function wiki_pdf_safe_original_name(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = trim($name);
    if (mb_strlen($name) > 255) {
        $name = mb_substr($name, -255);
    }

    return $name;
}

function wiki_pdf_has_signature(string $tmpPath): bool
{
    $handle = fopen($tmpPath, 'rb');
    if ($handle === false) {
        return false;
    }
    $chunk = fread($handle, 1024);
    fclose($handle);

    return is_string($chunk) && str_contains($chunk, '%PDF-');
}

/**
 * @param array<string, mixed> $file
 * @return array{title:string,description:?string,original_name:string,tmp_name:string,size:int}|string
 */
function wiki_pdf_validate_upload(string $title, string $description, array $file, int $maxBytes): array|string
{
    $title = wiki_pdf_plain($title, 255);
    $description = wiki_pdf_plain($description, 2000);

    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return 'Bitte eine PDF-Datei auswählen.';
    }
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        return 'Die Datei überschreitet das Upload-Limit des Servers (' . wiki_pdf_format_size($maxBytes) . ').';
    }
    if ($errorCode === UPLOAD_ERR_PARTIAL) {
        return 'Die Datei wurde nur teilweise hochgeladen. Bitte erneut versuchen.';
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        return 'Die Datei konnte nicht hochgeladen werden.';
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return 'Die Datei konnte nicht hochgeladen werden.';
    }

    $original = wiki_pdf_safe_original_name((string)($file['name'] ?? ''));
    if ($original === '' || strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf') {
        return 'Nur Dateien mit der Endung .pdf sind erlaubt.';
    }

    $size = filesize($tmpName);
    if ($size === false || $size < 5) {
        return 'Die Datei ist leer oder kein gültiges PDF.';
    }
    if ($size > $maxBytes) {
        return 'Die Datei ist größer als ' . wiki_pdf_format_size($maxBytes) . '.';
    }

    $mime = 'application/octet-stream';
    if (class_exists(finfo::class)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpName);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }

    $allowed = ['application/pdf', 'application/x-pdf', 'application/octet-stream'];
    if (!in_array($mime, $allowed, true) || !wiki_pdf_has_signature($tmpName)) {
        return 'Die Datei ist kein gültiges PDF.';
    }

    if ($title === '') {
        $title = wiki_pdf_plain((string)pathinfo($original, PATHINFO_FILENAME), 255);
    }
    if ($title === '') {
        return 'Bitte einen Titel angeben.';
    }

    return [
        'title' => $title,
        'description' => $description === '' ? null : $description,
        'original_name' => $original,
        'tmp_name' => $tmpName,
        'size' => $size,
    ];
}

function wiki_pdf_sync_search(PDO $pdo, string $title, ?string $description, string $originalName, string $storedName): void
{
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'wiki_pages'");
        if ($check === false || $check->fetch() === false) {
            return;
        }

        $params = [
            'title' => mb_substr($title, 0, 255),
            'category' => 'PDF-Dokumente',
            'url' => 'uploads/pdf/' . $storedName,
            'content' => trim($title . "\n" . $originalName . "\n" . (string)$description),
        ];
        $insert = $pdo->prepare(
            'INSERT INTO wiki_pages (title, category, url, content)
             VALUES (:title, :category, :url, :content)'
        );
        try {
            $insert->execute($params);
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                return;
            }
            $update = $pdo->prepare(
                'UPDATE wiki_pages
                 SET title = :title, category = :category, content = :content
                 WHERE url = :url'
            );
            $update->execute($params);
        }
    } catch (Throwable $e) {
        return;
    }
}

function wiki_pdf_unsync_search(PDO $pdo, string $storedName): void
{
    if (!wiki_pdf_is_stored_name($storedName)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM wiki_pages WHERE url = :url AND category = :category'
        );
        $stmt->execute([
            'url' => 'uploads/pdf/' . $storedName,
            'category' => 'PDF-Dokumente',
        ]);
    } catch (Throwable $e) {
        return;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function wiki_pdf_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, title, description, original_name, stored_name, file_size, created_at
         FROM wiki_pdfs
         ORDER BY created_at DESC, id DESC'
    );

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function wiki_pdf_public_url(string $storedName): string
{
    return 'uploads/pdf/' . rawurlencode($storedName);
}
