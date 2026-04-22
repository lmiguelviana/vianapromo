<?php
/**
 * api/bot_run.php — Dispara o bot em background.
 * Detecta automaticamente Windows ou Linux/Ubuntu.
 * Retorna em <100ms. Não bloqueia o Apache/Nginx.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$python    = $isWindows ? 'python' : 'python3';
$script    = realpath(__DIR__ . '/../bot/main.py');
$lockFile  = __DIR__ . '/../storage/bot.lock';

if (!$script || !file_exists($script)) {
    jsonResponse(['ok' => false, 'error' => 'bot/main.py não encontrado'], 500);
}

// ── Verificação de lock (cross-platform) ────────────────────────────────────
if (file_exists($lockFile)) {
    $pid = (int)trim(file_get_contents($lockFile));
    if ($pid > 0) {
        $running = $isWindows
            ? _pidRunningWindows($pid)
            : _pidRunningLinux($pid);

        if ($running) {
            jsonResponse([
                'ok'      => false,
                'running' => true,
                'error'   => 'O bot já está em execução. Acompanhe nos Logs.',
            ]);
        }
    }
    @unlink($lockFile);
}

function _pidRunningWindows(int $pid): bool {
    exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL", $out);
    return !empty(array_filter($out, fn($l) => str_contains($l, (string)$pid)));
}

function _pidRunningLinux(int $pid): bool {
    return file_exists("/proc/$pid");
}

// ── Placeholder de lock (Python vai sobrescrever com PID real) ────────────
file_put_contents($lockFile, '0');

// ── Lançamento cross-platform ────────────────────────────────────────────
if ($isWindows) {
    // Windows: cmd /C start /B /LOW — processo completamente desacoplado do Apache
    $cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s"', $python, $script);
    $proc = popen($cmd, 'r');
    pclose($proc);
} else {
    // Linux: nohup com redirecionamento para /dev/null — Apache não espera
    $cmd = sprintf('nohup %s %s > /dev/null 2>&1 & echo $!', $python, escapeshellarg($script));
    $pid = trim(shell_exec($cmd));
    // Salva o PID real imediatamente (no Linux o nohup retorna o PID)
    if (is_numeric($pid) && $pid > 0) {
        file_put_contents($lockFile, $pid);
    }
}

jsonResponse([
    'ok'      => true,
    'message' => 'Bot iniciado! Acompanhe o progresso em Logs do Sistema.',
    'os'      => $isWindows ? 'windows' : 'linux',
]);
