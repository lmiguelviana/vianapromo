<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET — Lista keywords (opcionalmente filtradas por fonte)
if ($method === 'GET') {
    $fonte = $_GET['fonte'] ?? '';
    if ($fonte) {
        $stmt = $db->prepare("SELECT * FROM keywords WHERE fonte = ? ORDER BY ativo DESC, keyword ASC");
        $stmt->execute([$fonte]);
    } else {
        $stmt = $db->query("SELECT * FROM keywords ORDER BY fonte ASC, ativo DESC, keyword ASC");
    }
    jsonResponse(['ok' => true, 'keywords' => $stmt->fetchAll()]);
}

csrfVerify();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// POST — Criar nova keyword
if ($method === 'POST') {
    $fonte   = strtoupper(trim($body['fonte'] ?? 'ML'));
    $keyword = strtolower(trim($body['keyword'] ?? ''));

    if (!$keyword || !in_array($fonte, ['ML', 'SHP', 'MGZ'])) {
        jsonResponse(['ok' => false, 'error' => 'Keyword ou fonte inválida.'], 422);
    }

    try {
        $db->prepare("INSERT INTO keywords (fonte, keyword) VALUES (?, ?)")->execute([$fonte, $keyword]);
        $id = (int)$db->lastInsertId();
        jsonResponse(['ok' => true, 'id' => $id, 'keyword' => $keyword, 'fonte' => $fonte]);
    } catch (\PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Keyword já existe nesta fonte.'], 409);
    }
}

// PATCH — Toggle ativo/inativo
if ($method === 'PATCH') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido.'], 422);

    $db->prepare("UPDATE keywords SET ativo = CASE WHEN ativo = 1 THEN 0 ELSE 1 END WHERE id = ?")
       ->execute([$id]);

    $row = $db->prepare("SELECT ativo FROM keywords WHERE id = ?");
    $row->execute([$id]);
    jsonResponse(['ok' => true, 'ativo' => (int)($row->fetchColumn())]);
}

// DELETE — Remover keyword
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido.'], 422);

    $db->prepare("DELETE FROM keywords WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Método não suportado.'], 405);
