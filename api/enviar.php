<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/evolution.php';

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);

$linkId  = (int)($input['link_id'] ?? 0);
$grupoId = (int)($input['grupo_id'] ?? 0);

if (!$linkId || !$grupoId) {
    jsonResponse(['ok' => false, 'error' => 'link_id e grupo_id são obrigatórios.'], 400);
}

$link = $db->prepare('SELECT * FROM links WHERE id = ? AND ativo = 1');
$link->execute([$linkId]);
$link = $link->fetch();

if (!$link) {
    jsonResponse(['ok' => false, 'error' => 'Link não encontrado ou inativo.'], 404);
}

$grupo = $db->prepare('SELECT * FROM grupos WHERE id = ? AND ativo = 1');
$grupo->execute([$grupoId]);
$grupo = $grupo->fetch();

if (!$grupo) {
    jsonResponse(['ok' => false, 'error' => 'Grupo não encontrado ou inativo.'], 404);
}

$texto = $link['mensagem'] ?: msgTemplate(
    $link['nome_produto'],
    $link['url_afiliado'],
    $link['preco_de']  ?? '',
    $link['preco_por'] ?? ''
);

$api = new EvolutionAPI();

if (!$api->isConfigured()) {
    jsonResponse(['ok' => false, 'error' => 'Evolution API não configurada.'], 500);
}

// Envia com imagem se disponível (arquivo local tem prioridade sobre URL externa)
$imagemPath = $link['imagem_path'] ?? '';
$imagemUrl  = $link['imagem_url']  ?? '';

if ($imagemPath && file_exists($imagemPath)) {
    $result = $api->sendMedia($grupo['group_jid'], $texto, $imagemPath);
} elseif ($imagemUrl) {
    $result = $api->sendMedia($grupo['group_jid'], $texto, $imagemUrl);
} else {
    $result = $api->sendText($grupo['group_jid'], $texto);
}

$status = $result['ok'] ? 'sucesso' : 'erro';
$erroMsg = $result['ok'] ? null : $result['error'];

$db->prepare('INSERT INTO historico (link_id, grupo_id, status, mensagem_erro) VALUES (?, ?, ?, ?)')
   ->execute([$linkId, $grupoId, $status, $erroMsg]);

if ($result['ok']) {
    jsonResponse(['ok' => true]);
} else {
    jsonResponse(['ok' => false, 'error' => $result['error']], 500);
}
