<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$atual = getConfig('bot_ativo');
$novo  = ($atual === '0') ? '1' : '0';
setConfig('bot_ativo', $novo);

jsonResponse(['ok' => true, 'bot_ativo' => $novo]);
