<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json');

$db = getDB();

// Alertas não lidos (últimos 50, mais recentes primeiro)
$alertas = $db->query("
    SELECT id, tipo, fonte, mensagem, criado_em
    FROM bot_alertas
    WHERE lido = 0
    ORDER BY criado_em DESC
    LIMIT 50
")->fetchAll();

echo json_encode([
    'ok'      => true,
    'total'   => count($alertas),
    'alertas' => $alertas,
], JSON_UNESCAPED_UNICODE);
