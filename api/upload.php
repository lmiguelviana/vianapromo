<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$file = $_FILES['imagem'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $erros = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor).',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
    ];
    $msg = $erros[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Erro no upload.';
    jsonResponse(['ok' => false, 'error' => $msg], 400);
}

$maxBytes = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxBytes) {
    jsonResponse(['ok' => false, 'error' => 'Imagem deve ter no máximo 5 MB.'], 400);
}

$mimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $mimes)) {
    jsonResponse(['ok' => false, 'error' => 'Formato inválido. Use JPG, PNG ou WebP.'], 400);
}

$ext      = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
$nome     = uniqid('img_', true) . '.' . $ext;
$destDir  = __DIR__ . '/../uploads/';
$destPath = $destDir . $nome;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['ok' => false, 'error' => 'Erro ao salvar arquivo no servidor.'], 500);
}

jsonResponse([
    'ok'   => true,
    'path' => $destPath,
    'url'  => '/viana/uploads/' . $nome,
]);
