<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/evolution.php';

$api = new EvolutionAPI();

if (!$api->isConfigured()) {
    jsonResponse(['ok' => false, 'error' => 'Evolution API não configurada. Vá em Config primeiro.'], 400);
}

$result = $api->getGroups();

if (!$result['ok']) {
    jsonResponse(['ok' => false, 'error' => $result['error']], 500);
}

$raw = $result['data'];

// A Evolution API pode retornar array direto ou dentro de uma chave
$grupos = [];
if (isset($raw[0])) {
    $grupos = $raw;
} elseif (isset($raw['groups'])) {
    $grupos = $raw['groups'];
} else {
    $grupos = $raw;
}

$lista = [];
foreach ($grupos as $g) {
    $jid  = $g['id'] ?? $g['jid'] ?? '';
    $nome = $g['subject'] ?? $g['name'] ?? $jid;
    if ($jid) {
        $lista[] = ['jid' => $jid, 'nome' => $nome];
    }
}

usort($lista, fn($a, $b) => strcmp($a['nome'], $b['nome']));

jsonResponse(['ok' => true, 'grupos' => $lista]);
