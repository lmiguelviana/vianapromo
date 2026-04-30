<?php
/**
 * api/ml_refresh.php
 * Renova o access_token ML usando o refresh_token salvo.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/ml_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Metodo invalido'], 405);
}

$resultado = mlRefreshTokenAuto(0, true);

if ($resultado['ok']) {
    // Libera o proximo ciclo do Bot ML sem iniciar o bot completo antigo.
    setConfig('bot_ml_ultimo_run', '');
    jsonResponse([
        'ok' => true,
        'message' => $resultado['message'] ?? 'Token ML renovado.',
        'valid_until' => $resultado['valid_until'] ?? null,
    ]);
}

jsonResponse([
    'ok' => false,
    'error' => $resultado['error'] ?? 'Nao foi possivel renovar o token ML.',
], 400);
