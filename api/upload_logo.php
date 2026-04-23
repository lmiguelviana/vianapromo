<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$file = $_FILES['logo'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $erros = [
        UPLOAD_ERR_INI_SIZE  => 'Arquivo muito grande (limite do servidor).',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande.',
        UPLOAD_ERR_NO_FILE   => 'Nenhum arquivo enviado.',
    ];
    $msg = $erros[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Erro no upload.';
    jsonResponse(['ok' => false, 'error' => $msg], 400);
}

// Max 2 MB para logo
$maxBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    jsonResponse(['ok' => false, 'error' => 'Logo deve ter no máximo 2 MB.'], 400);
}

$mimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $mimes)) {
    jsonResponse(['ok' => false, 'error' => 'Formato inválido. Use JPG, PNG, WebP ou SVG.'], 400);
}

$ext      = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', default => 'jpg' };
$nome     = 'system_logo_' . time() . '.' . $ext;
$destDir  = __DIR__ . '/../uploads/';
$destPath = $destDir . $nome;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['ok' => false, 'error' => 'Erro ao salvar arquivo no servidor.'], 500);
}

// Remove logo antigo, se existir e for diferente do novo
$logoAntigo = getConfig('system_logo_path');
if ($logoAntigo && $logoAntigo !== $destPath && file_exists($logoAntigo)) {
    @unlink($logoAntigo);
}

// Salva no banco
setConfig('system_logo_path', $destPath);
setConfig('system_logo_url',  BASE . '/uploads/' . $nome);

jsonResponse([
    'ok'  => true,
    'url' => BASE . '/uploads/' . $nome,
]);
