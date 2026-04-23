<?php
/**
 * api/cron_test.php — Simula a execução do bot_cron.php e retorna o diagnóstico.
 * Modo padrão: mostra o que aconteceria sem rodar de verdade.
 * Modo force=1: ignora intervalo e dispara o bot agora.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$force = !empty($input['force']);

$linhas = [];
$rodaria = true;

// 1. bot_ativo
$bot_ativo = getConfig('bot_ativo');
if ($bot_ativo !== '1') {
    $linhas[] = '❌ bot_ativo = 0 → bot está DESATIVADO no painel';
    $rodaria = false;
} else {
    $linhas[] = '✅ bot_ativo = 1 → ativado';
}

// 2. Intervalo
$intervalo_horas = max(1, (int)(getConfig('bot_intervalo_horas') ?: '6'));
$ultimo_run      = getConfig('bot_ultimo_run');
$linhas[] = "ℹ️  Intervalo configurado: {$intervalo_horas}h";

if ($ultimo_run) {
    $proximo_run = strtotime($ultimo_run) + ($intervalo_horas * 3600);
    $falta_seg   = $proximo_run - time();
    $linhas[] = "ℹ️  Último run: {$ultimo_run}";

    if ($falta_seg > 0 && !$force) {
        $falta_min = (int)ceil($falta_seg / 60);
        $linhas[] = "⏳ Próximo run em {$falta_min} min — ainda não é hora";
        $rodaria = false;
    } else {
        $linhas[] = '✅ Intervalo atingido (ou forçado)';
    }
} else {
    $linhas[] = 'ℹ️  Nunca rodou ainda';
}

// 3. Lock file
$lockFile = __DIR__ . '/../storage/bot.lock';
if (file_exists($lockFile)) {
    $pid = (int)trim(file_get_contents($lockFile));
    $isLinux = PHP_OS_FAMILY !== 'Windows';
    $rodando = $pid > 0 && $isLinux && file_exists("/proc/$pid");
    if ($rodando) {
        $linhas[] = "❌ bot.lock existe com PID ativo ($pid) → bot já está rodando";
        $rodaria = false;
    } else {
        $linhas[] = "ℹ️  bot.lock existia (PID $pid inativo) → será removido";
        if ($rodaria) @unlink($lockFile);
    }
} else {
    $linhas[] = '✅ Sem lock ativo';
}

// 4. bot/main.py existe?
$script = realpath(__DIR__ . '/../bot/main.py');
if (!$script) {
    $linhas[] = '❌ bot/main.py não encontrado';
    $rodaria = false;
} else {
    $linhas[] = '✅ bot/main.py encontrado';
}

// 5. Disparar se tudo ok
if ($rodaria) {
    setConfig('bot_ultimo_run', date('Y-m-d H:i:s'));

    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWindows) {
        $python = 'python';
        $cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s"', $python, $script);
        $proc = popen($cmd, 'r');
        pclose($proc);
    } else {
        exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));
    }

    $linhas[] = '🚀 Bot disparado! Acompanhe em Logs do Sistema.';
    jsonResponse(['ok' => true, 'disparou' => true, 'linhas' => $linhas]);
} else {
    $linhas[] = $force ? '⚠️  Não foi possível disparar — verifique os erros acima.' : '💤 Cron não dispararia agora.';
    jsonResponse(['ok' => true, 'disparou' => false, 'linhas' => $linhas]);
}
