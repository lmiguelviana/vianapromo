<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

csrfVerify();
$db     = getDB();
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'criar':
        $titulo       = trim($input['titulo']       ?? '');
        $subtitulo    = trim($input['subtitulo']    ?? '');
        $imagem_path  = trim($input['imagem_path']  ?? '');
        $link_url     = trim($input['link_url']     ?? '');
        $ordem        = (int)($input['ordem']       ?? 0);

        if (!$imagem_path) jsonResponse(['ok' => false, 'error' => 'Imagem obrigatória.'], 400);

        $db->prepare("INSERT INTO slides (titulo, subtitulo, imagem_path, link_url, ordem) VALUES (?,?,?,?,?)")
           ->execute([$titulo, $subtitulo, $imagem_path, $link_url, $ordem]);
        jsonResponse(['ok' => true, 'id' => (int)$db->lastInsertId()]);

    case 'editar':
        $id          = (int)($input['id'] ?? 0);
        $titulo      = trim($input['titulo']      ?? '');
        $subtitulo   = trim($input['subtitulo']   ?? '');
        $imagem_path = trim($input['imagem_path'] ?? '');
        $link_url    = trim($input['link_url']    ?? '');
        $ordem       = (int)($input['ordem']      ?? 0);

        $db->prepare("UPDATE slides SET titulo=?, subtitulo=?, imagem_path=?, link_url=?, ordem=? WHERE id=?")
           ->execute([$titulo, $subtitulo, $imagem_path, $link_url, $ordem, $id]);
        jsonResponse(['ok' => true]);

    case 'toggle':
        $id = (int)($input['id'] ?? 0);
        $db->prepare("UPDATE slides SET ativo = CASE WHEN ativo=1 THEN 0 ELSE 1 END WHERE id=?")
           ->execute([$id]);
        $ativo = (int)$db->query("SELECT ativo FROM slides WHERE id=$id")->fetchColumn();
        jsonResponse(['ok' => true, 'ativo' => $ativo]);

    case 'deletar':
        $id = (int)($input['id'] ?? 0);
        $slide = $db->prepare("SELECT imagem_path FROM slides WHERE id=?")->execute([$id])
            ? $db->query("SELECT imagem_path FROM slides WHERE id=$id")->fetch()
            : null;
        $db->prepare("DELETE FROM slides WHERE id=?")->execute([$id]);
        // Remove arquivo local se existir
        if ($slide && $slide['imagem_path']) {
            $full = __DIR__ . '/../' . ltrim($slide['imagem_path'], '/');
            if (file_exists($full)) @unlink($full);
        }
        jsonResponse(['ok' => true]);

    default:
        jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
}
