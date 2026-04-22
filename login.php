<?php
require_once __DIR__ . '/app/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: /viana/');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? AND ativo = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['usuario_id']    = $user['id'];
        $_SESSION['usuario_nome']  = $user['nome'];
        $_SESSION['usuario_email'] = $user['email'];

        $redirect = $_GET['redirect'] ?? '/viana/';
        header('Location: ' . $redirect);
        exit;
    }

    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login — Viana Promo</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:#f9fafb;min-height:100vh;display:flex;align-items:center;justify-content:center;color:#111827}
        .wrap{width:100%;max-width:360px;padding:0 16px}
        .logo{text-align:center;margin-bottom:32px}
        .logo h1{font-size:1.75rem;font-weight:700}
        .logo h1 span.brand{color:#059669}
        .logo h1 span.promo{color:#374151}
        .logo p{font-size:.8125rem;color:#9ca3af;margin-top:4px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,.06);padding:32px}
        .card-title{font-size:.9375rem;font-weight:600;color:#1f2937;margin-bottom:24px}
        .field{margin-bottom:16px}
        label{display:block;font-size:.8125rem;font-weight:500;color:#374151;margin-bottom:6px}
        input{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;font-size:.875rem;outline:none;transition:border-color .15s,box-shadow .15s;background:#fff}
        input:focus{border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.15)}
        .btn{width:100%;background:#059669;color:#fff;border:none;border-radius:8px;padding:11px;font-size:.875rem;font-weight:600;cursor:pointer;margin-top:8px;transition:background .15s}
        .btn:hover{background:#047857}
        .btn:active{background:#065f46}
        .alert{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#b91c1c;font-size:.8125rem;padding:12px 14px;margin-bottom:16px}
        .footer{text-align:center;font-size:.75rem;color:#9ca3af;margin-top:20px}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">
            <h1><span class="brand">Viana</span><span class="promo"> Promo</span></h1>
            <p>Painel de Links de Afiliado</p>
        </div>

        <div class="card">
            <p class="card-title">Entrar na conta</p>

            <?php if ($erro): ?>
                <div class="alert"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autofocus required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>

        <p class="footer">Viana Promo · uso pessoal</p>
    </div>
</body>
</html>
