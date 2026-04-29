<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$mlAtivo  = getConfig('bot_ml_ativo') !== '0';
$shpAtivo = getConfig('bot_shopee_ativo') !== '0';

// Botão legado da fila agora liga/desliga os dois bots independentes.
$novo = ($mlAtivo || $shpAtivo) ? '0' : '1';
setConfig('bot_ml_ativo', $novo);
setConfig('bot_shopee_ativo', $novo);
setConfig('bot_ativo', $novo); // mantém compatibilidade com pipeline completo legado

jsonResponse(['ok' => true, 'bot_ativo' => $novo]);
