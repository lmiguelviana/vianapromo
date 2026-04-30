<?php
/**
 * api/bot_run.php — Dispara o bot em background.
 * Parâmetro POST opcional: fonte = "ml" | "shopee" | "" (completo)
 * Detecta automaticamente Windows ou Linux/Ubuntu.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$fonte = trim($data['fonte'] ?? $_POST['fonte'] ?? '');
if (!in_array($fonte, ['ml', 'shopee', ''], true)) {
    jsonResponse(['ok' => false, 'error' => 'fonte inválida'], 400);
}

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$python    = $isWindows ? 'python' : 'python3';
$script    = realpath(__DIR__ . '/../bot/main.py');

// Lock correspondente ao bot solicitado
$lockMap = [
    'ml'     => __DIR__ . '/../storage/bot_ml.lock',
    'shopee' => __DIR__ . '/../storage/bot_shopee.lock',
    ''       => __DIR__ . '/../storage/bot.lock',
];
$lockFile = $lockMap[$fonte];
$label    = $fonte ? "Bot $fonte" : 'Bot completo';

if (!$script || !file_exists($script)) {
    jsonResponse(['ok' => false, 'error' => 'bot/main.py não encontrado'], 500);
}

// Verifica lock
if (file_exists($lockFile)) {
    $pid = (int)trim(file_get_contents($lockFile));
    if ($pid > 0) {
        $running = $isWindows ? _pidRunningWindows($pid) : _pidRunningLinux($pid);
        if ($running) {
            jsonResponse(['ok' => false, 'running' => true,
                'error' => "$label já está em execução. Acompanhe nos Logs."]);
        }
    }
    @unlink($lockFile);
}

function _pidRunningWindows(int $pid): bool {
    exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL", $out);
    return !empty(array_filter($out, fn($l) => str_contains($l, (string)$pid)));
}
function _pidRunningLinux(int $pid): bool {
    $cmdline = "/proc/$pid/cmdline";
    if (!file_exists($cmdline)) return false;
    $cmd = @file_get_contents($cmdline);
    if ($cmd === false || $cmd === '') return false;
    $cmd = str_replace("\0", " ", $cmd);
    return str_contains($cmd, 'main.py');
}

// Argumento --fonte para main.py
$fonteArg = $fonte ? "--fonte $fonte" : '';

if ($isWindows) {
    $cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s" %s', $python, $script, $fonteArg);
    $proc = popen($cmd, 'r');
    pclose($proc);
} else {
    $cmd = sprintf('setsid %s %s %s > /dev/null 2>&1 & echo $!',
        $python, escapeshellarg($script), $fonteArg);
    $pid = trim(shell_exec($cmd));
}

jsonResponse([
    'ok'      => true,
    'message' => "$label iniciado! Acompanhe o progresso em Logs.",
    'fonte'   => $fonte ?: 'completo',
]);
