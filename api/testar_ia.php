<?php
/**
 * api/testar_ia.php — Testa a conexão com OpenRouter enviando uma mensagem simples.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$apikey = getConfig('openrouter_apikey');
$modelo = getConfig('openrouter_model') ?: 'minimax/minimax-01:free';

if (!$apikey) {
    jsonResponse(['ok' => false, 'error' => 'API Key do OpenRouter não configurada.']);
}

$payload = json_encode([
    'model'    => $modelo,
    'messages' => [['role' => 'user', 'content' => 'Diga apenas: "IA funcionando! ✅"']],
    'max_tokens' => 30,
]);

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apikey,
        'HTTP-Referer: http://localhost/viana',
        'X-Title: Viana Promo',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);

$raw  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    jsonResponse(['ok' => false, 'error' => 'Erro de rede: ' . $err]);
}

$data = json_decode($raw, true);

// isset() falha quando content=null (resposta válida do OR) — usar array_key_exists
$content_ok = isset($data['choices'][0]['message'])
    && array_key_exists('content', $data['choices'][0]['message']);

if ($http !== 200 || !$content_ok) {
    // Mostra o erro exato da API para facilitar debug
    $msg = $data['error']['message'] ?? ($data['error'] ?? $raw);
    if (is_array($msg)) $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    jsonResponse(['ok' => false, 'error' => "HTTP $http: " . substr((string)$msg, 0, 400)]);
}

$resposta = trim((string)($data['choices'][0]['message']['content'] ?? '(sem resposta)'));
jsonResponse([
    'ok'      => true,
    'message' => $resposta,
    'modelo'  => $modelo,
]);
