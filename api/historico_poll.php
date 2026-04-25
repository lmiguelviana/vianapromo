<?php
/**
 * api/historico_poll.php — Polling silencioso do histórico de envios.
 * Retorna quantos registros de historico com id > last_id existem.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$lastId = (int)($_GET['last_id'] ?? 0);

$db   = getDB();
$stmt = $db->prepare("SELECT COUNT(*) FROM historico WHERE id > ?");
$stmt->execute([$lastId]);

jsonResponse(['ok' => true, 'novas' => (int)$stmt->fetchColumn()]);
