<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();

$username = htmlspecialchars((string)$_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role = (string)$_SESSION['role'];
$roleLabel = $role === 'admin' ? 'Administrator' : 'Listener';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – MedizinInformatik Wiki</title>
<style>
body{margin:0;background:#f5f8fb;color:#18232d;font-family:Arial,Helvetica,sans-serif}.header{background:linear-gradient(135deg,#062a4f,#0a3e70);color:#fff;padding:20px}.header-inner{max-width:1000px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:20px}.header a{color:#fff;text-decoration:none}.main{max-width:1000px;margin:35px auto;padding:0 20px}.card{background:#fff;border:1px solid #dfe6ec;border-radius:12px;padding:25px;box-shadow:0 4px 18px rgba(5,40,75,.06)}.btn{display:inline-block;background:#07335f;color:#fff;text-decoration:none;padding:10px 14px;border-radius:6px;margin:6px 6px 0 0}.btn:hover{background:#0b6ebc}.muted{color:#5f6b75}
</style>
</head>
<body>
<header class="header"><div class="header-inner"><strong>MedizinInformatik Wiki</strong><a href="logout.php">Abmelden</a></div></header>
<main class="main"><section class="card">
<h1>Willkommen, <?= $username ?>!</h1>
<p class="muted">Angemeldet als <strong><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<a class="btn" href="index.html">Wiki öffnen</a>
<?php if ($role === 'admin'): ?><a class="btn" href="admin/index.php">Admin-Bereich</a><?php endif; ?>
</section></main>
</body>
</html>
