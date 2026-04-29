<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$lock = __DIR__ . '/../storage/bot.lock';

if (!file_exists($lock)) {
    jsonResponse(['ok' => true, 'message' => 'Lock já estava limpo.']);
}

if (@unlink($lock)) {
    jsonResponse(['ok' => true, 'message' => 'Lock removido. Bot pode ser executado novamente.']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Não foi possível remover o lock. Verifique permissões.']);
}
