<?php
/**
 * api/ml_auth.php
 * Troca o Authorization Code ML por access_token + refresh_token
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$code = trim($body['code'] ?? '');

if (!$code) {
    echo json_encode(['ok' => false, 'error' => 'Código não fornecido']);
    exit;
}

$client_id     = getConfig('ml_client_id');
$client_secret = getConfig('ml_client_secret');
$redirect_uri  = 'https://www.google.com/';

if (!$client_id || !$client_secret) {
    echo json_encode(['ok' => false, 'error' => 'Client ID ou Secret não configurados']);
    exit;
}

// Troca o code pelo access_token
$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'authorization_code',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'code'          => $code,
        'redirect_uri'  => $redirect_uri,
    ]),
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
]);

$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);

if ($status === 200 && !empty($data['access_token'])) {
    setConfig('ml_access_token',  $data['access_token']);
    setConfig('ml_refresh_token', $data['refresh_token'] ?? '');
    setConfig('ml_token_expires', (string)(time() + (int)($data['expires_in'] ?? 21600)));
    setConfig('ml_user_id',       (string)($data['user_id'] ?? ''));

    echo json_encode([
        'ok'      => true,
        'message' => 'Conta ML conectada com sucesso! Bot já pode buscar produtos.',
        'user_id' => $data['user_id'] ?? '',
    ]);
} else {
    $err = $data['message'] ?? $data['error'] ?? 'Erro desconhecido';
    echo json_encode(['ok' => false, 'error' => "ML retornou: $err (HTTP $status)"]);
}
