<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Salvar grupo(s) do WhatsApp no banco
if ($method === 'POST' && $action === 'salvar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $grupos = $input['grupos'] ?? [];

    $stmt = $db->prepare('INSERT OR IGNORE INTO grupos (nome, group_jid) VALUES (?, ?)');
    $salvos = 0;
    foreach ($grupos as $g) {
        $jid  = trim($g['jid'] ?? '');
        $nome = trim($g['nome'] ?? $jid);
        if ($jid) {
            $stmt->execute([$nome, $jid]);
            $salvos++;
        }
    }
    jsonResponse(['ok' => true, 'salvos' => $salvos]);
}

// Listar grupos salvos
if ($method === 'GET' && $action === 'listar') {
    $rows = $db->query('SELECT * FROM grupos ORDER BY nome')->fetchAll();
    jsonResponse(['ok' => true, 'grupos' => $rows]);
}

// Alternar ativo/inativo
if ($method === 'POST' && $action === 'toggle') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $db->prepare('UPDATE grupos SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

// Excluir grupo
if ($method === 'POST' && $action === 'excluir') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $db->prepare('DELETE FROM grupos WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
