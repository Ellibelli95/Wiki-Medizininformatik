<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_role('admin');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – MedizinInformatik Wiki</title>
<style>
body{margin:0;background:#f5f8fb;color:#18232d;font-family:Arial,Helvetica,sans-serif}.header{background:#07335f;color:#fff;padding:20px}.main{max-width:900px;margin:35px auto;padding:0 20px}.card{background:#fff;border:1px solid #dfe6ec;border-radius:12px;padding:25px}
a{color:#0b6ebc}
</style>
</head>
<body>
<header class="header"><strong>MedizinInformatik Wiki – Admin</strong></header>
<main class="main"><section class="card">
<h1>Admin-Bereich</h1>
<p>Dieser Bereich ist nur für Benutzer mit der Rolle <strong>admin</strong> erreichbar.</p>
<p><a href="../dashboard.php">← Zum Dashboard</a> · <a href="../logout.php">Abmelden</a></p>
</section></main>
</body>
</html>
