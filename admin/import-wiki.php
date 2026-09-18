<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_role('admin');

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    exit('config.php fehlt.');
}

$config = require $configFile;

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

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS wiki_pages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(150) NOT NULL DEFAULT '',
        url VARCHAR(500) NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wiki_pages_url (url),
        KEY idx_wiki_pages_title (title),
        KEY idx_wiki_pages_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$message = '';
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $root = dirname(__DIR__);

    $upsert = $pdo->prepare(
        'INSERT INTO wiki_pages (title, category, url, content)
         VALUES (:title, :category, :url, :content)
         ON DUPLICATE KEY UPDATE
             title = VALUES(title),
             category = VALUES(category),
             content = VALUES(content),
             updated_at = CURRENT_TIMESTAMP'
    );

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
            continue;
        }

        $path = $file->getPathname();
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        if (str_starts_with($relative, 'admin/')
            || str_starts_with($relative, 'api/')
            || str_starts_with($relative, 'assets/')) {
            continue;
        }

        $html = @file_get_contents($path);
        if ($html === false) {
            continue;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $title = '';
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }

        if ($title === '') {
            $h1Nodes = $dom->getElementsByTagName('h1');
            if ($h1Nodes->length > 0) {
                $title = trim($h1Nodes->item(0)->textContent);
            }
        }

        if ($title === '') {
            $title = basename($relative, '.html');
        }

        $bodyNodes = $dom->getElementsByTagName('body');
        $contentText = $bodyNodes->length > 0 ? $bodyNodes->item(0)->textContent : $dom->textContent;
        $contentText = preg_replace('/\s+/u', ' ', trim($contentText)) ?? trim($contentText);

        $parts = explode('/', $relative);
        $category = count($parts) > 1 ? $parts[0] : 'Startseite';

        $upsert->execute([
            'title' => mb_substr($title, 0, 255),
            'category' => mb_substr($category, 0, 150),
            'url' => $relative,
            'content' => $contentText,
        ]);

        $imported++;
    }

    $message = $imported . ' HTML-Seiten wurden in die Datenbank übernommen.';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wiki importieren – MedizinInformatik Wiki</title>
<style>
body{margin:0;background:#f5f8fb;color:#18232d;font-family:Arial,Helvetica,sans-serif}
.header{background:#07335f;color:#fff;padding:20px}
.main{max-width:900px;margin:35px auto;padding:0 20px}
.card{background:#fff;border:1px solid #dfe6ec;border-radius:12px;padding:25px}
button{background:#07335f;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer}
button:hover{background:#0b6ebc}
.message{margin:20px 0;padding:14px;background:#eaf6ee;border:1px solid #b9ddc4;border-radius:8px}
a{color:#0b6ebc}
</style>
</head>
<body>
<header class="header"><strong>MedizinInformatik Wiki – Wiki-Import</strong></header>
<main class="main">
<section class="card">
<h1>Wiki automatisch in die Datenbank übernehmen</h1>
<p>Diese Funktion durchsucht die vorhandenen HTML-Seiten deines Wikis und speichert Titel, Kategorie, URL und Seiteninhalt in <code>wiki_pages</code>.</p>
<?php if ($message !== ''): ?>
<div class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form method="post">
<button type="submit">Wiki jetzt importieren</button>
</form>
<p><a href="index.php">← Zurück zum Admin-Bereich</a></p>
</section>
</main>
</body>
</html>
