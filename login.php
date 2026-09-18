<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    exit('config.php fehlt. Kopiere config.example.php nach config.php und trage deine lokalen Datenbankdaten ein.');
}

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
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankverbindung fehlgeschlagen. Prüfe config.php und ob MySQL/MariaDB läuft.');
}

require __DIR__ . '/auth.php';
start_secure_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Benutzername oder Passwort ist falsch.';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – MedizinInformatik Wiki</title>
<style>
:root{--navy:#07335f;--blue:#0b6ebc;--line:#dfe6ec;--text:#18232d}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f8fb;font-family:Arial,Helvetica,sans-serif;color:var(--text)}
.login{width:min(420px,calc(100% - 32px));background:#fff;border:1px solid var(--line);border-radius:14px;padding:32px;box-shadow:0 8px 30px rgba(5,40,75,.10)}
.logo{text-align:center;color:var(--navy);font-weight:800;font-size:22px;margin-bottom:24px}
label{display:block;font-weight:700;font-size:14px;margin:14px 0 6px}
input{width:100%;padding:12px;border:1px solid #cfd9e2;border-radius:7px;font-size:15px}
button{width:100%;margin-top:20px;padding:12px;border:0;border-radius:7px;background:var(--navy);color:#fff;font-weight:700;cursor:pointer}
button:hover{background:var(--blue)}
.error{background:#fff0f0;border-left:4px solid #c0392b;padding:10px 12px;margin-bottom:14px;color:#8b1e16;font-size:14px}
.back{text-align:center;margin-top:18px;font-size:13px}.back a{color:var(--blue);text-decoration:none}
</style>
</head>
<body>
<main class="login">
<div class="logo">MedizinInformatik Wiki<br><small>Login</small></div>
<?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<label for="username">Benutzername</label>
<input id="username" name="username" type="text" required autocomplete="username">
<label for="password">Passwort</label>
<input id="password" name="password" type="password" required autocomplete="current-password">
<button type="submit">Anmelden</button>
</form>
<div class="back"><a href="index.html">← Zur Wiki-Startseite</a></div>
</main>
</body>
</html>
