<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/evolution.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

csrfVerify();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

$api = new EvolutionAPI();

if (!$api->isConfigured()) {
    jsonResponse(['ok' => false, 'error' => 'Evolution API não configurada.']);
}

switch ($action) {
    case 'status':
        $r = $api->testConnection();
        if (!$r['ok']) jsonResponse($r);
        $state = $r['data']['instance']['state'] ?? $r['data']['state'] ?? 'unknown';
        jsonResponse(['ok' => true, 'state' => $state]);

    case 'logout':
        $r = $api->logout();
        if (!$r['ok']) jsonResponse($r);
        jsonResponse(['ok' => true, 'message' => 'Número desconectado.']);

    case 'qrcode':
        $r = $api->getQRCode();
        if (!$r['ok']) jsonResponse($r);
        $data   = $r['data'] ?? [];
        $base64 = $data['base64'] ?? '';
        $code   = $data['code']   ?? '';
        // Já conectado (sem QR) quando retorna state=open
        $state  = $data['instance']['state'] ?? $data['state'] ?? '';
        if ($state === 'open') {
            jsonResponse(['ok' => true, 'connected' => true]);
        }
        if (!$base64) {
            jsonResponse(['ok' => false, 'error' => 'QR code não disponível. Tente novamente em alguns segundos.']);
        }
        jsonResponse(['ok' => true, 'connected' => false, 'base64' => $base64, 'code' => $code]);

    default:
        jsonResponse(['ok' => false, 'error' => 'Ação inválida.'], 400);
}
