<?php
declare(strict_types=1);

require __DIR__ . '/pdf-lib.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

wiki_pdf_require_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    wiki_pdf_flash('error', 'Das PDF wurde nicht gefunden.');
    header('Location: index.php', true, 303);
    exit;
}

try {
    $pdo = wiki_pdf_pdo();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT title, stored_name FROM wiki_pdfs WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        $pdo->rollBack();
        wiki_pdf_flash('error', 'Das PDF wurde nicht gefunden.');
        header('Location: index.php', true, 303);
        exit;
    }

    $storedName = (string)$row['stored_name'];
    $title = (string)$row['title'];
    $delete = $pdo->prepare('DELETE FROM wiki_pdfs WHERE id = :id');
    $delete->execute(['id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    wiki_pdf_flash('error', 'Das PDF konnte nicht gelöscht werden.');
    header('Location: index.php', true, 303);
    exit;
}

$fileRemoved = wiki_pdf_delete_stored_file($storedName);

if (isset($pdo) && $pdo instanceof PDO) {
    wiki_pdf_unsync_search($pdo, $storedName);
}

if (!$fileRemoved) {
    wiki_pdf_flash('error', 'Der Eintrag wurde gelöscht, die Datei konnte aber nicht entfernt werden.');
} else {
    wiki_pdf_flash('success', 'Das PDF „' . $title . '“ wurde gelöscht.');
}

header('Location: index.php', true, 303);
exit;
