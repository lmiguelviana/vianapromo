<?php
/**
 * Scheduler por fonte.
 *
 * Uso:
 *   php cron/bot_cron_fonte.php ml
 *   php cron/bot_cron_fonte.php shopee
 */
require_once __DIR__ . '/../app/db.php';

$fonte = $argv[1] ?? '';
if (!in_array($fonte, ['ml', 'shopee'], true)) {
    echo '[' . date('Y-m-d H:i:s') . "] Fonte inválida. Use: ml | shopee\n";
    exit(1);
}

$ts = fn() => '[' . date('Y-m-d H:i:s') . '] ';
$label = $fonte === 'ml' ? 'Bot ML' : 'Bot Shopee';
$prefix = "bot_{$fonte}";
$lockFile = __DIR__ . "/../storage/{$prefix}.lock";
$logFile = __DIR__ . "/../storage/{$prefix}.log";

function log_cron(string $path, string $msg): void {
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . "] [INFO] [CRON] {$msg}\n", FILE_APPEND);
}

function cfg_fonte(string $prefix, string $suffix, string $fallback, string $default = ''): string {
    $v = getConfig("{$prefix}_{$suffix}");
    if ($v !== '') return $v;
    $v = getConfig($fallback);
    return $v !== '' ? $v : $default;
}

function pid_bot_rodando(int $pid, string $fonte): bool {
    if ($pid <= 0) return false;
    $cmdline = "/proc/$pid/cmdline";
    if (!file_exists($cmdline)) return false;
    $cmd = (string)@file_get_contents($cmdline);
    if ($cmd === '') return false;
    $cmd = str_replace("\0", " ", $cmd);
    return str_contains($cmd, 'main.py')
        && (str_contains($cmd, "--fonte $fonte") || str_contains($cmd, "--fonte=$fonte"));
}

if (getConfig('bot_ativo') === '0') {
    $msg = "{$label}: pausa geral ativa. Pulando.";
    echo $ts() . $msg . "\n";
    log_cron($logFile, $msg);
    exit;
}

$ativoFonte = cfg_fonte($prefix, 'ativo', 'bot_ativo', '1');
if ($ativoFonte !== '1') {
    $msg = "{$label}: pausado na configuração ({$prefix}_ativo=0). Pulando.";
    echo $ts() . $msg . "\n";
    log_cron($logFile, $msg);
    exit;
}

$intervaloHoras = max(1, (int)cfg_fonte($prefix, 'intervalo_horas', 'bot_intervalo_horas', $fonte === 'shopee' ? '12' : '6'));
$ultimoRun = getConfig("{$prefix}_ultimo_run");
$proximoRun = $ultimoRun ? strtotime($ultimoRun) + ($intervaloHoras * 3600) : 0;

if (time() < $proximoRun) {
    $faltaMin = (int)ceil(($proximoRun - time()) / 60);
    $msg = "{$label}: próximo run em {$faltaMin} min. Pulando.";
    echo $ts() . $msg . "\n";
    log_cron($logFile, $msg);
    exit;
}

if (file_exists($lockFile)) {
    $pid = (int)trim((string)@file_get_contents($lockFile));
    if ($pid > 0 && pid_bot_rodando($pid, $fonte)) {
        $msg = "{$label}: já em execução (PID {$pid}). Pulando.";
        echo $ts() . $msg . "\n";
        log_cron($logFile, $msg);
        exit;
    }
    @unlink($lockFile);
    log_cron($logFile, "{$label}: lock zumbi removido (PID {$pid}).");
}

$script = realpath(__DIR__ . '/../bot/main.py');
if (!$script) {
    $msg = "{$label}: ERRO bot/main.py não encontrado.";
    echo $ts() . $msg . "\n";
    log_cron($logFile, $msg);
    exit(1);
}

setConfig("{$prefix}_ultimo_run", date('Y-m-d H:i:s'));

$cmd = sprintf('setsid python3 %s --fonte %s > /dev/null 2>&1 & echo $!', escapeshellarg($script), escapeshellarg($fonte));
$pid = trim((string)shell_exec($cmd));
if (is_numeric($pid) && (int)$pid > 0) {
    @file_put_contents($lockFile, $pid);
}

$msg = "{$label}: iniciado (PID {$pid}, intervalo {$intervaloHoras}h).";
echo $ts() . $msg . "\n";
log_cron($logFile, $msg);
