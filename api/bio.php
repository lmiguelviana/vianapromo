<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

requireLogin();
csrfVerify();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$db     = getDB();

if ($action === 'criar') {
    $titulo = trim($body['titulo'] ?? '');
    $url    = trim($body['url'] ?? '');
    if (!$titulo || !$url) jsonResponse(['ok' => false, 'error' => 'Título e URL obrigatórios.'], 400);
    $db->prepare("INSERT INTO bio_links (titulo, url, icone, cor, ordem) VALUES (?,?,?,?,?)")
       ->execute([$titulo, $url, $body['icone'] ?? 'link', $body['cor'] ?? '#059669', (int)($body['ordem'] ?? 0)]);
    jsonResponse(['ok' => true]);
}

if ($action === 'editar') {
    $id     = (int)($body['id'] ?? 0);
    $titulo = trim($body['titulo'] ?? '');
    $url    = trim($body['url'] ?? '');
    if (!$id || !$titulo || !$url) jsonResponse(['ok' => false, 'error' => 'Dados inválidos.'], 400);
    $db->prepare("UPDATE bio_links SET titulo=?, url=?, icone=?, cor=?, ordem=? WHERE id=?")
       ->execute([$titulo, $url, $body['icone'] ?? 'link', $body['cor'] ?? '#059669', (int)($body['ordem'] ?? 0), $id]);
    jsonResponse(['ok' => true]);
}

if ($action === 'toggle') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido.'], 400);
    $db->prepare("UPDATE bio_links SET ativo = CASE WHEN ativo=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($action === 'deletar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido.'], 400);
    $db->prepare("DELETE FROM bio_links WHERE id=?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($action === 'perfil') {
    setConfig('bio_nome',        trim($body['nome'] ?? ''));
    setConfig('bio_descricao',   trim($body['descricao'] ?? ''));
    setConfig('bio_avatar_path', trim($body['avatar_path'] ?? ''));
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida.'], 400);
