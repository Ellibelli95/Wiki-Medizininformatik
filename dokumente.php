<?php
declare(strict_types=1);

require __DIR__ . '/admin/pdf-lib.php';

$pdo = wiki_pdf_pdo();
$pdfs = wiki_pdf_list($pdo);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PDF-Dokumente – MedizinInformatik Wiki</title>
<style>
:root{--navy:#07335f;--blue:#0b6ebc;--line:#dfe6ec;--text:#18232d;--muted:#5f6b75}
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;color:var(--text);background:#fff;line-height:1.55}
a{color:inherit;text-decoration:none}
.header{background:linear-gradient(135deg,#062a4f,#0a3e70);color:#fff}
.header-inner{max-width:980px;margin:auto;display:flex;align-items:center;gap:22px;padding:18px 24px}
.logo{display:flex;align-items:center;gap:12px;color:#fff}
.logo-mark{width:44px;height:44px;border:3px solid #fff;border-radius:50%;display:grid;place-items:center;font-size:28px;font-weight:700}
.logo-title{font-size:18px;font-weight:800;line-height:1.05}
.logo-subtitle{font-size:11px;opacity:.8;margin-top:4px}
main{max-width:980px;margin:auto;padding:28px 24px 60px}
.back-link{display:inline-block;margin-bottom:18px;font-size:13px;color:var(--blue)}
h1{color:var(--navy);margin:0 0 8px}
.intro{color:#404a53;margin:0 0 22px}
.doc{display:block;border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:14px}
.doc:hover{border-color:#b8ccdc;box-shadow:0 4px 14px rgba(5,40,75,.08)}
.doc h2{font-size:18px;color:var(--navy);margin:0 0 6px}
.doc p{margin:0;color:#3f484f;font-size:14px}
.meta{margin-top:8px;color:var(--muted);font-size:13px}
.empty{color:var(--muted)}
</style>
</head>
<body>
<header class="header">
<div class="header-inner">
<a class="logo" href="index.html" aria-label="MedizinInformatik Wiki Startseite">
<span class="logo-mark">+</span>
<span><span class="logo-title">MedizinInformatik<br>WIKI</span><span class="logo-subtitle">Technische Dokumentation</span></span>
</a>
</div>
</header>
<main>
<a class="back-link" href="index.html">← Zurück zur Startseite</a>
<h1>PDF-Dokumente</h1>
<p class="intro">Ergänzende Unterlagen, die im Admin-Bereich hochgeladen wurden.</p>
<?php if ($pdfs === []): ?>
<p class="empty">Es wurden noch keine PDF-Dokumente hochgeladen.</p>
<?php else: ?>
<?php foreach ($pdfs as $pdf): ?>
<?php
    $storedName = (string)$pdf['stored_name'];
    if (!wiki_pdf_is_stored_name($storedName)) {
        continue;
    }
    $createdLabel = (string)$pdf['created_at'];
    try {
        $createdLabel = (new DateTimeImmutable($createdLabel))->format('d.m.Y');
    } catch (Exception $e) {
        $createdLabel = (string)$pdf['created_at'];
    }
    $description = trim((string)($pdf['description'] ?? ''));
?>
<a class="doc" href="<?= wiki_pdf_e(wiki_pdf_public_url($storedName)) ?>" target="_blank" rel="noopener">
<h2><?= wiki_pdf_e((string)$pdf['title']) ?></h2>
<?php if ($description !== ''): ?><p><?= wiki_pdf_e($description) ?></p><?php endif; ?>
<div class="meta"><?= wiki_pdf_e((string)$pdf['original_name']) ?> · <?= wiki_pdf_e(wiki_pdf_format_size((int)$pdf['file_size'])) ?> · <?= wiki_pdf_e($createdLabel) ?></div>
</a>
<?php endforeach; ?>
<?php endif; ?>
</main>
</body>
</html>
