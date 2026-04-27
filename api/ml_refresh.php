<?php
/**
 * api/ml_refresh.php
 * Renova o access_token ML usando o refresh_token salvo.
 * Dispensando nova autorização enquanto o refresh_token for válido (~6 meses).
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

$client_id     = getConfig('ml_client_id');
$client_secret = getConfig('ml_client_secret');
$refresh_token = getConfig('ml_refresh_token');

if (!$client_id || !$client_secret) {
    echo json_encode(['ok' => false, 'error' => 'Client ID ou Secret não configurados']);
    exit;
}

if (!$refresh_token) {
    echo json_encode(['ok' => false, 'error' => 'Nenhum refresh_token salvo. Reconecte a conta ML.']);
    exit;
}

// Troca o refresh_token por um novo access_token
$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'refresh_token',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'refresh_token' => $refresh_token,
    ]),
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
]);

$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);

if ($status === 200 && !empty($data['access_token'])) {
    $expires_in = (int)($data['expires_in'] ?? 21600);
    setConfig('ml_access_token',  $data['access_token']);
    // O ML rotaciona o refresh_token a cada uso — sempre salva o novo
    setConfig('ml_refresh_token', $data['refresh_token'] ?? $refresh_token);
    setConfig('ml_token_expires', (string)(time() + $expires_in));

    // Reseta o timer do bot para que o cron dispare no próximo ciclo
    setConfig('bot_ultimo_run', '');

    // Inicia o bot imediatamente se não estiver rodando
    $bot_iniciado = false;
    $lockFile = __DIR__ . '/../storage/bot.lock';
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $botRunning = false;

    if (file_exists($lockFile)) {
        $pid = (int)trim(file_get_contents($lockFile));
        if ($pid > 0) {
            $botRunning = $isWindows
                ? (bool)array_filter(
                    explode("\n", shell_exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL") ?: ''),
                    fn($l) => str_contains($l, (string)$pid)
                  )
                : file_exists("/proc/$pid");
        }
        if (!$botRunning) @unlink($lockFile);
    }

    if (!$botRunning) {
        $script = realpath(__DIR__ . '/../bot/main.py');
        if ($script) {
            file_put_contents($lockFile, '0');
            if ($isWindows) {
                $cmd = sprintf('cmd /C start /B /LOW "" "python" "%s"', $script);
                $proc = popen($cmd, 'r');
                pclose($proc);
            } else {
                $cmd = sprintf('setsid python3 %s > /dev/null 2>&1 & echo $!', escapeshellarg($script));
                $pid = trim(shell_exec($cmd));
                if (is_numeric($pid) && $pid > 0) file_put_contents($lockFile, $pid);
            }
            $bot_iniciado = true;
        }
    }

    $valido_ate = date('d/m H:i', time() + $expires_in);
    echo json_encode([
        'ok'           => true,
        'message'      => "Token renovado! Válido até $valido_ate." . ($bot_iniciado ? ' Bot iniciado automaticamente.' : ($botRunning ? ' Bot já estava rodando.' : '')),
        'valid_until'  => time() + $expires_in,
        'bot_iniciado' => $bot_iniciado,
    ]);
} else {
    $err = $data['message'] ?? $data['error'] ?? 'Erro desconhecido';
    // refresh_token expirado ou revogado — usuário precisa reconectar
    if (in_array($data['error'] ?? '', ['invalid_grant', 'invalid_token'])) {
        echo json_encode(['ok' => false, 'error' => "Refresh token expirado ou revogado. Reconecte a conta ML. (ML: $err)"]);
    } else {
        echo json_encode(['ok' => false, 'error' => "ML retornou: $err (HTTP $status)"]);
    }
}
