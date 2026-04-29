<?php
/**
 * api/cron_test.php — Simula ou força execução de um bot específico.
 * JSON: { fonte: "ml"|"shopee"|"", force: true|false }
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$fonte = trim($input['fonte'] ?? '');
$force = !empty($input['force']);

if (!in_array($fonte, ['ml', 'shopee', ''], true)) {
    jsonResponse(['ok' => false, 'error' => 'fonte inválida'], 400);
}

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$script    = realpath(__DIR__ . '/../bot/main.py');
$label     = $fonte === 'ml' ? 'Bot ML' : ($fonte === 'shopee' ? 'Bot Shopee' : 'Bot completo');
$prefix    = $fonte ? "bot_{$fonte}" : 'bot';
$lockFile  = match ($fonte) {
    'ml'     => __DIR__ . '/../storage/bot_ml.lock',
    'shopee' => __DIR__ . '/../storage/bot_shopee.lock',
    default  => __DIR__ . '/../storage/bot.lock',
};

$linhas = [];
$rodaria = true;

$linhas[] = "Diagnóstico: {$label}";

// 1. Pausa geral
$botAtivo = getConfig('bot_ativo');
if ($botAtivo !== '1') {
    $linhas[] = '❌ bot_ativo = 0 → pausa geral ligada';
    $rodaria = false;
} else {
    $linhas[] = '✅ bot_ativo = 1 → pausa geral desligada';
}

// 2. Pausa por fonte
if ($fonte) {
    $fonteAtiva = getConfig("{$prefix}_ativo");
    if ($fonteAtiva === '') $fonteAtiva = $botAtivo;
    if ($fonteAtiva !== '1') {
        $linhas[] = "❌ {$prefix}_ativo = 0 → {$label} pausado";
        $rodaria = false;
    } else {
        $linhas[] = "✅ {$prefix}_ativo = 1 → {$label} ativo";
    }
}

// 3. Intervalo
$intervalo = max(1, (int)(_cfg("{$prefix}_intervalo_horas", 'bot_intervalo_horas', $fonte === 'shopee' ? '12' : '6')));
$ultimoRun = _cfg("{$prefix}_ultimo_run", 'bot_ultimo_run', '');
$linhas[] = "ℹ️ Intervalo configurado: {$intervalo}h";

if ($ultimoRun) {
    $proximoRun = strtotime($ultimoRun) + ($intervalo * 3600);
    $faltaSeg = $proximoRun - time();
    $linhas[] = "ℹ️ Último run: {$ultimoRun}";
    if ($faltaSeg > 0 && !$force) {
        $faltaMin = (int)ceil($faltaSeg / 60);
        $linhas[] = "⏳ Próximo run em {$faltaMin} min — ainda não é hora";
        $rodaria = false;
    } else {
        $linhas[] = '✅ Intervalo atingido ou execução forçada';
    }
} else {
    $linhas[] = 'ℹ️ Nunca rodou ainda';
}

// 4. Lock por fonte
if (file_exists($lockFile)) {
    $pid = (int)trim((string)@file_get_contents($lockFile));
    if ($pid > 0 && _pidRodandoBot($pid, $fonte)) {
        $linhas[] = "❌ Lock ativo: {$lockFile} com PID {$pid}";
        $rodaria = false;
    } else {
        $linhas[] = "ℹ️ Lock zumbi encontrado (PID {$pid}) → removendo";
        @unlink($lockFile);
    }
} else {
    $linhas[] = '✅ Sem lock ativo';
}

// 5. Script
if (!$script || !file_exists($script)) {
    $linhas[] = '❌ bot/main.py não encontrado';
    $rodaria = false;
} else {
    $linhas[] = '✅ bot/main.py encontrado';
}

if (!$rodaria) {
    $linhas[] = $force ? '⚠️ Não foi possível disparar — corrija os itens acima.' : '💤 Cron não dispararia agora.';
    jsonResponse(['ok' => true, 'disparou' => false, 'linhas' => $linhas]);
}

if (!$force) {
    $linhas[] = "✅ Simulação OK: {$label} dispararia agora.";
    jsonResponse(['ok' => true, 'disparou' => false, 'linhas' => $linhas]);
}

setConfig("{$prefix}_ultimo_run", date('Y-m-d H:i:s'));

$fonteArg = $fonte ? "--fonte {$fonte}" : '';
if ($isWindows) {
    $python = 'python';
    $cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s" %s', $python, $script, $fonteArg);
    $proc = popen($cmd, 'r');
    if ($proc) pclose($proc);
} else {
    $cmd = sprintf('setsid python3 %s %s > /dev/null 2>&1 & echo $!', escapeshellarg($script), $fonteArg);
    $pid = trim((string)shell_exec($cmd));
    if (is_numeric($pid) && $pid > 0) {
        @file_put_contents($lockFile, $pid);
    }
}

$logUrl = $fonte === 'ml' ? '/logs-ml' : ($fonte === 'shopee' ? '/logs-shopee' : '/logs');
$linhas[] = "🚀 {$label} disparado! Acompanhe em {$logUrl}.";
jsonResponse(['ok' => true, 'disparou' => true, 'linhas' => $linhas]);

function _cfg(string $key, string $fallback, string $default = ''): string {
    $v = getConfig($key);
    if ($v !== '') return $v;
    $v = getConfig($fallback);
    return $v !== '' ? $v : $default;
}

function _pidRodandoBot(int $pid, string $fonte): bool {
    if ($pid <= 0) return false;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("wmic process where ProcessId=$pid get CommandLine /value 2>NUL", $out);
        $cmd = implode(' ', $out);
    } else {
        $cmdline = "/proc/$pid/cmdline";
        if (!file_exists($cmdline)) return false;
        $cmd = (string)@file_get_contents($cmdline);
        if ($cmd === '') return false;
        $cmd = str_replace("\0", " ", $cmd);
    }
    if (!str_contains($cmd, 'main.py')) return false;
    if ($fonte === '') return true;
    return str_contains($cmd, "--fonte {$fonte}") || str_contains($cmd, "--fonte={$fonte}");
}
