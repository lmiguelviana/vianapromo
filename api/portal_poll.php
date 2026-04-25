<?php
/**
 * api/portal_poll.php — Polling silencioso do portal público.
 * Retorna quantas ofertas enviadas com id > last_id existem.
 * Sem autenticação — portal é público.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$lastId = (int)($_GET['last_id'] ?? 0);

$db   = getDB();
$stmt = $db->prepare("SELECT COUNT(*) FROM ofertas WHERE status = 'enviada' AND id > ?");
$stmt->execute([$lastId]);

jsonResponse(['ok' => true, 'novas' => (int)$stmt->fetchColumn()]);
