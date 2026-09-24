<?php
declare(strict_types=1);

require __DIR__ . '/pdf-lib.php';
require_role('admin');

header('Cache-Control: no-store');

$pdo = wiki_pdf_pdo();
$flash = wiki_pdf_take_flash();
$token = wiki_pdf_csrf_token();
$maxBytes = wiki_pdf_max_bytes();
$directoryError = wiki_pdf_ensure_directory();
$pdfs = wiki_pdf_list($pdo);
$username = wiki_pdf_e((string)($_SESSION['username'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PDF-Upload – MedizinInformatik Wiki</title>
<style>
body{margin:0;background:#f5f8fb;color:#18232d;font-family:Arial,Helvetica,sans-serif}
.header{background:#07335f;color:#fff;padding:20px}
.header-inner{max-width:980px;margin:auto;display:flex;justify-content:space-between;gap:16px;align-items:center}
.header a{color:#fff}
.main{max-width:980px;margin:35px auto;padding:0 20px 50px}
.card{background:#fff;border:1px solid #dfe6ec;border-radius:12px;padding:25px;margin-bottom:18px}
label{display:block;font-weight:700;font-size:14px;margin:14px 0 6px}
input[type=text],textarea,input[type=file]{width:100%;padding:12px;border:1px solid #cfd9e2;border-radius:8px;font:inherit;background:#fff}
textarea{min-height:90px;resize:vertical}
button,.link-btn{display:inline-block;background:#07335f;color:#fff;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none}
button:hover,.link-btn:hover{background:#0b6ebc}
button.danger{background:#9d2c2c}
button.danger:hover{background:#c0392b}
.message{margin:0 0 16px;padding:14px;border-radius:8px}
.message.success{background:#eaf6ee;border:1px solid #b9ddc4}
.message.error{background:#fff0f0;border:1px solid #f0c2c2}
.hint{color:#5f6b75;font-size:14px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #dfe6ec;vertical-align:top}
th{font-size:13px;color:#5f6b75}
.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.actions form{margin:0}
.file-meta{color:#5f6b75;font-size:13px}
a{color:#0b6ebc}
.table-wrap{overflow-x:auto}
</style>
</head>
<body>
<header class="header">
<div class="header-inner">
<strong>MedizinInformatik Wiki – Admin</strong>
<span><?= $username ?></span>
</div>
</header>
<main class="main">
<section class="card">
<h1>PDF-Dokumente hochladen</h1>
<p class="hint">Nur Administratoren können Dateien hochladen oder löschen. Gespeicherte PDFs sind danach im Wiki öffentlich unter <a href="../dokumente.php">PDF-Dokumente</a> abrufbar. Keine vertraulichen Patientendaten hochladen.</p>
<p class="hint">Die Tabelle <code>wiki_pdfs</code> wird beim Öffnen dieser Seite angelegt. Alternativ kannst du <code>database-pdf.sql</code> in phpMyAdmin importieren. Dateien liegen in <code>uploads/pdf</code>.</p>
<?php if ($flash !== null): ?>
<div class="message <?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= wiki_pdf_e($flash['text']) ?></div>
<?php endif; ?>
<?php if ($directoryError !== null): ?>
<div class="message error"><?= wiki_pdf_e($directoryError) ?></div>
<?php endif; ?>
<form method="post" action="upload.php" enctype="multipart/form-data" accept-charset="UTF-8">
<input type="hidden" name="csrf_token" value="<?= wiki_pdf_e($token) ?>">
<label for="title">Titel</label>
<input id="title" name="title" type="text" maxlength="255" required placeholder="z. B. HL7 Merkblatt">
<label for="description">Beschreibung (optional)</label>
<textarea id="description" name="description" maxlength="2000" placeholder="Worum geht es in dem PDF?"></textarea>
<label for="pdf">PDF-Datei</label>
<input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
<p class="hint">Erlaubt sind nur PDF-Dateien bis <?= wiki_pdf_e(wiki_pdf_format_size($maxBytes)) ?>.<?php if ($maxBytes < WIKI_PDF_APP_MAX_BYTES): ?> Das PHP-Limit von XAMPP (upload_max_filesize / post_max_size) liegt darunter. Größere Dateien kannst du in der <code>php.ini</code> zulassen.<?php endif; ?></p>
<button type="submit"<?= $directoryError !== null ? ' disabled' : '' ?>>PDF hochladen</button>
</form>
</section>

<section class="card">
<h2>Vorhandene PDFs (<?= count($pdfs) ?>)</h2>
<?php if ($pdfs === []): ?>
<p class="hint">Noch keine PDF-Datei hochgeladen.</p>
<?php else: ?>
<div class="table-wrap">
<table>
<thead>
<tr><th>Dokument</th><th>Größe</th><th>Hochgeladen</th><th>Aktion</th></tr>
</thead>
<tbody>
<?php foreach ($pdfs as $pdf): ?>
<?php
    $storedName = (string)$pdf['stored_name'];
    $created = (string)$pdf['created_at'];
    $createdLabel = $created;
    try {
        $createdLabel = (new DateTimeImmutable($created))->format('d.m.Y H:i');
    } catch (Exception $e) {
        $createdLabel = $created;
    }
    $description = trim((string)($pdf['description'] ?? ''));
?>
<tr>
<td>
<strong><?= wiki_pdf_e((string)$pdf['title']) ?></strong>
<div class="file-meta"><?= wiki_pdf_e((string)$pdf['original_name']) ?></div>
<?php if ($description !== ''): ?><div class="file-meta"><?= wiki_pdf_e($description) ?></div><?php endif; ?>
</td>
<td><?= wiki_pdf_e(wiki_pdf_format_size((int)$pdf['file_size'])) ?></td>
<td><?= wiki_pdf_e($createdLabel) ?></td>
<td>
<div class="actions">
<?php if (wiki_pdf_is_stored_name($storedName)): ?>
<a class="link-btn" href="../<?= wiki_pdf_e(wiki_pdf_public_url($storedName)) ?>" target="_blank" rel="noopener">Öffnen</a>
<?php endif; ?>
<form method="post" action="delete.php" onsubmit="return confirm('Dieses PDF wirklich löschen?');">
<input type="hidden" name="csrf_token" value="<?= wiki_pdf_e($token) ?>">
<input type="hidden" name="id" value="<?= (int)$pdf['id'] ?>">
<button class="danger" type="submit">Löschen</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<p><a href="../dashboard.php">← Zum Dashboard</a> · <a href="import-wiki.php">Wiki-Seiten importieren</a> · <a href="../logout.php">Abmelden</a></p>
</section>
</main>
</body>
</html>
