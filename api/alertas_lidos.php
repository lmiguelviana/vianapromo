<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

csrfVerify();
header('Content-Type: application/json');

$db  = getDB();
$ids = json_decode(file_get_contents('php://input'), true)['ids'] ?? [];

if (empty($ids)) {
    // Marcar todos como lido
    $db->exec("UPDATE bot_alertas SET lido = 1 WHERE lido = 0");
} else {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE bot_alertas SET lido = 1 WHERE id IN ($placeholders)");
    $stmt->execute(array_map('intval', $ids));
}

echo json_encode(['ok' => true]);
