<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db = getDB();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Status de uma oferta (GET) — usado pelo JS após timeout de rede ────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT status FROM ofertas WHERE id = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    jsonResponse(['ok' => (bool)$row, 'status' => $row['status'] ?? null]);
}

// ── Rejeitar oferta ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'rejeitar') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido'], 400);

    // Busca o produto_id_externo antes de rejeitar
    $oferta = $db->prepare("SELECT produto_id_externo FROM ofertas WHERE id = ?");
    $oferta->execute([$id]);
    $prod_id = $oferta->fetchColumn();

    // Marca como rejeitada
    $db->prepare("UPDATE ofertas SET status = 'rejeitada' WHERE id = ?")->execute([$id]);

    // Adiciona à blacklist permanente
    if ($prod_id) {
        $db->prepare("INSERT OR IGNORE INTO blacklist (produto_id_externo, motivo) VALUES (?, 'rejeitado')")
           ->execute([$prod_id]);
    }

    jsonResponse(['ok' => true]);
}

// ── Remover oferta (apaga sem blacklist — bot pode recolher) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remover') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido'], 400);
    $db->prepare("DELETE FROM ofertas WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

// ── Adiar oferta (esconde por agora, sem blacklist) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'adiar') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido'], 400);
    $db->prepare("UPDATE ofertas SET status = 'adiada' WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

// ── Aprovar oferta (forçar para 'pronta') ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'aprovar') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido'], 400);

    $db->prepare("UPDATE ofertas SET status = 'pronta' WHERE id = ? AND status != 'enviada'")->execute([$id]);
    jsonResponse(['ok' => true]);
}

// ── Listar (para futuras chamadas AJAX) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar') {
    $status = $_GET['status'] ?? 'todas';
    $where  = $status !== 'todas' ? "WHERE status = ?" : "WHERE 1=1";
    $params = $status !== 'todas' ? [$status] : [];

    $stmt = $db->prepare("SELECT * FROM ofertas $where ORDER BY coletado_em DESC LIMIT 50");
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'ofertas' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
