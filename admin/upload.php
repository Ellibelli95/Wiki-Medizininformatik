<?php
declare(strict_types=1);

require __DIR__ . '/pdf-lib.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

wiki_pdf_require_csrf();

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && $_POST === [] && $_FILES === []) {
    wiki_pdf_flash('error', 'Die Datei ist zu groß für die PHP-Upload-Limits (post_max_size). In der XAMPP-php.ini upload_max_filesize und post_max_size erhöhen.');
    header('Location: index.php', true, 303);
    exit;
}

$directoryError = wiki_pdf_ensure_directory();
if ($directoryError !== null) {
    wiki_pdf_flash('error', $directoryError);
    header('Location: index.php', true, 303);
    exit;
}

$pdo = wiki_pdf_pdo();

$maxBytes = wiki_pdf_max_bytes();
$validated = wiki_pdf_validate_upload(
    (string)($_POST['title'] ?? ''),
    (string)($_POST['description'] ?? ''),
    is_array($_FILES['pdf'] ?? null) ? $_FILES['pdf'] : [],
    $maxBytes
);

if (is_string($validated)) {
    wiki_pdf_flash('error', $validated);
    header('Location: index.php', true, 303);
    exit;
}

$storedName = '';
$destination = '';
do {
    $storedName = bin2hex(random_bytes(16)) . '.pdf';
    $destination = wiki_pdf_directory() . DIRECTORY_SEPARATOR . $storedName;
} while (is_file($destination));

if (!move_uploaded_file($validated['tmp_name'], $destination)) {
    wiki_pdf_flash('error', 'Die Datei konnte nicht gespeichert werden.');
    header('Location: index.php', true, 303);
    exit;
}

@chmod($destination, 0644);

try {
    $userId = $_SESSION['user_id'] ?? null;
    $uploadedBy = is_int($userId) ? $userId : (is_string($userId) && ctype_digit($userId) ? (int)$userId : null);
    $size = filesize($destination);
    if ($size === false) {
        $size = $validated['size'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO wiki_pdfs (title, description, original_name, stored_name, mime_type, file_size, uploaded_by)
         VALUES (:title, :description, :original_name, :stored_name, :mime_type, :file_size, :uploaded_by)'
    );
    $stmt->execute([
        'title' => $validated['title'],
        'description' => $validated['description'],
        'original_name' => $validated['original_name'],
        'stored_name' => $storedName,
        'mime_type' => 'application/pdf',
        'file_size' => $size,
        'uploaded_by' => $uploadedBy,
    ]);

    wiki_pdf_sync_search(
        $pdo,
        $validated['title'],
        $validated['description'],
        $validated['original_name'],
        $storedName
    );
} catch (Throwable $e) {
    if (is_file($destination)) {
        @unlink($destination);
    }
    wiki_pdf_flash('error', 'Das PDF konnte nicht in der Datenbank gespeichert werden.');
    header('Location: index.php', true, 303);
    exit;
}

wiki_pdf_flash('success', 'Das PDF „' . $validated['title'] . '“ wurde gespeichert.');
header('Location: index.php', true, 303);
exit;
