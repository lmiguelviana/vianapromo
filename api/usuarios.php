<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'criar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome  = trim($input['nome']  ?? '');
    $email = trim($input['email'] ?? '');
    $senha = $input['senha'] ?? '';

    if (!$nome || !$email || !$senha) {
        jsonResponse(['ok' => false, 'error' => 'Preencha todos os campos.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'E-mail inválido.'], 400);
    }

    if (strlen($senha) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Senha deve ter ao menos 6 caracteres.'], 400);
    }

    $existe = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existe->execute([$email]);
    if ($existe->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'E-mail já cadastrado.'], 400);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)')->execute([$nome, $email, $hash]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

if ($method === 'POST' && $action === 'trocar_senha') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = (int)($input['id'] ?? 0);
    $senha = $input['senha'] ?? '';

    if (!$id || strlen($senha) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Senha deve ter ao menos 6 caracteres.'], 400);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([$hash, $id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'POST' && $action === 'toggle') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    // Não pode desativar a si mesmo
    if ($id === (int)currentUser()['id']) {
        jsonResponse(['ok' => false, 'error' => 'Você não pode desativar sua própria conta.'], 400);
    }

    $db->prepare('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'POST' && $action === 'excluir') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if ($id === (int)currentUser()['id']) {
        jsonResponse(['ok' => false, 'error' => 'Você não pode excluir sua própria conta.'], 400);
    }

    $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
