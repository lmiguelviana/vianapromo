<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function extrairCamposLink(array $input): array {
    return [
        'plataforma'   => trim($input['plataforma']   ?? ''),
        'nome_produto' => trim($input['nome_produto'] ?? ''),
        'url_afiliado' => trim($input['url_afiliado'] ?? ''),
        'mensagem'     => trim($input['mensagem']     ?? ''),
        'imagem_url'   => trim($input['imagem_url']   ?? ''),
        'imagem_path'  => trim($input['imagem_path']  ?? ''),
        'preco_de'     => trim($input['preco_de']     ?? ''),
        'preco_por'    => trim($input['preco_por']    ?? ''),
    ];
}

if ($method === 'POST' && $action === 'criar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $d = extrairCamposLink($input);

    if (!$d['plataforma'] || !$d['nome_produto'] || !$d['url_afiliado']) {
        jsonResponse(['ok' => false, 'error' => 'Preencha plataforma, nome e URL.'], 400);
    }

    $db->prepare('INSERT INTO links (plataforma, nome_produto, url_afiliado, mensagem, imagem_url, imagem_path, preco_de, preco_por) VALUES (?,?,?,?,?,?,?,?)')
       ->execute([$d['plataforma'], $d['nome_produto'], $d['url_afiliado'], $d['mensagem'], $d['imagem_url'], $d['imagem_path'], $d['preco_de'], $d['preco_por']]);

    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

if ($method === 'POST' && $action === 'editar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $d  = extrairCamposLink($input);
    $id = (int)($input['id'] ?? 0);

    if (!$id || !$d['plataforma'] || !$d['nome_produto'] || !$d['url_afiliado']) {
        jsonResponse(['ok' => false, 'error' => 'Dados inválidos.'], 400);
    }

    $db->prepare('UPDATE links SET plataforma=?, nome_produto=?, url_afiliado=?, mensagem=?, imagem_url=?, imagem_path=?, preco_de=?, preco_por=? WHERE id=?')
       ->execute([$d['plataforma'], $d['nome_produto'], $d['url_afiliado'], $d['mensagem'], $d['imagem_url'], $d['imagem_path'], $d['preco_de'], $d['preco_por'], $id]);

    jsonResponse(['ok' => true]);
}

if ($method === 'POST' && $action === 'excluir') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    // Remove imagem local se existir
    $link = $db->prepare('SELECT imagem_path FROM links WHERE id = ?');
    $link->execute([$id]);
    $row = $link->fetch();
    if ($row && $row['imagem_path'] && file_exists($row['imagem_path'])) {
        @unlink($row['imagem_path']);
    }

    $db->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'POST' && $action === 'toggle') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $db->prepare('UPDATE links SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'GET' && $action === 'buscar') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM links WHERE id = ?');
    $stmt->execute([$id]);
    $link = $stmt->fetch();
    $link ? jsonResponse(['ok' => true, 'link' => $link]) : jsonResponse(['ok' => false, 'error' => 'Não encontrado'], 404);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
