<?php
/**
 * api/fila_limpar.php — Apaga ofertas da fila.
 * tipo=rejeitada  → apaga só as rejeitadas
 * tipo=todas      → apaga tudo (exceto 'enviada')
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$tipo = $_POST['tipo'] ?? '';

$db = getDB();

if ($tipo === 'rejeitada') {
    // Salva na blacklist ANTES de apagar (para nunca mais voltar)
    $db->exec("INSERT OR IGNORE INTO blacklist (produto_id_externo, motivo)
               SELECT produto_id_externo, 'rejeitado' FROM ofertas
               WHERE status = 'rejeitada' AND produto_id_externo != ''");

    $stmt = $db->prepare("DELETE FROM ofertas WHERE status = 'rejeitada'");
    $stmt->execute();
    $n = $stmt->rowCount();
    $bl = $db->query('SELECT COUNT(*) FROM blacklist')->fetchColumn();
    jsonResponse(['ok' => true, 'message' => "$n oferta(s) removida(s). Blacklist: $bl produto(s) bloqueado(s).", 'removidas' => $n]);

} elseif ($tipo === 'todas') {
    // Mantém as enviadas para histórico
    $stmt = $db->prepare("DELETE FROM ofertas WHERE status != 'enviada'");
    $stmt->execute();
    $n = $stmt->rowCount();
    jsonResponse(['ok' => true, 'message' => "$n oferta(s) removida(s) (enviadas foram mantidas).", 'removidas' => $n]);

} else {
    jsonResponse(['ok' => false, 'error' => 'Tipo inválido. Use: rejeitada ou todas'], 400);
}
