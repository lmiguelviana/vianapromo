<?php
/**
 * api/fila_poll.php — Polling silencioso da fila de ofertas.
 * Retorna quantas ofertas com id > last_id existem (com filtro opcional).
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$lastId = (int)($_GET['last_id'] ?? 0);
$filtro = $_GET['status'] ?? 'todas';

$db = getDB();

if ($filtro !== 'todas') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM ofertas WHERE status = ? AND id > ?");
    $stmt->execute([$filtro, $lastId]);
} else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM ofertas WHERE id > ?");
    $stmt->execute([$lastId]);
}

jsonResponse(['ok' => true, 'novas' => (int)$stmt->fetchColumn()]);
