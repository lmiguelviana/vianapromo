<?php
/**
 * api/click.php — Registra clique em oferta e redireciona para o link afiliado.
 * Público — sem login. Usado pelo portal.php.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . BASE . '/');
    exit;
}

$db     = getDB();
$oferta = $db->prepare('SELECT url_afiliado FROM ofertas WHERE id = ? AND status = ?');
$oferta->execute([$id, 'enviada']);
$row = $oferta->fetch();

if (!$row || empty($row['url_afiliado'])) {
    header('Location: ' . BASE . '/');
    exit;
}

// Registra o clique
try {
    $db->prepare('INSERT INTO clicks (oferta_id) VALUES (?)')->execute([$id]);
} catch (\PDOException) {
    // Não bloqueia o redirect se falhar
}

header('Location: ' . $row['url_afiliado'], true, 302);
exit;
