<?php
/**
 * cron/bot_cron.php — Verifica se o bot automático deve rodar.
 *
 * Chamado pelo cron do Docker a cada 30 min:
 *   */30 * * * * www-data php /var/www/viana/cron/bot_cron.php >> /dev/null 2>&1
 *
 * Não roda se:
 *   - bot_ativo = 0  (desativado no painel)
 *   - Ainda não passou o intervalo configurado desde o último run
 *   - bot.lock existe com processo ativo (bot já em execução)
 */
require_once __DIR__ . '/../app/db.php';

$ts = fn() => '[' . date('Y-m-d H:i:s') . '] ';

// 1. Verificar se está ativado
if (getConfig('bot_ativo') !== '1') {
    echo $ts() . "Bot desativado. Pulando.\n";
    exit;
}

// 2. Verificar intervalo
$intervalo_horas = max(1, (int)(getConfig('bot_intervalo_horas') ?: '6'));
$ultimo_run      = getConfig('bot_ultimo_run');
$proximo_run     = $ultimo_run ? strtotime($ultimo_run) + ($intervalo_horas * 3600) : 0;

if (time() < $proximo_run) {
    $falta_min = (int)ceil(($proximo_run - time()) / 60);
    echo $ts() . "Próximo run em {$falta_min} min. Pulando.\n";
    exit;
}

// 3. Verificar lock (evitar execução dupla)
$lockFile = __DIR__ . '/../storage/bot.lock';
if (file_exists($lockFile)) {
    $pid = (int)trim(file_get_contents($lockFile));
    if ($pid > 0 && file_exists("/proc/$pid")) {
        echo $ts() . "Bot já em execução (PID $pid). Pulando.\n";
        exit;
    }
    @unlink($lockFile);
}

// 4. Registra o início antes de lançar (evita disparo duplo em janelas sobrepostas)
setConfig('bot_ultimo_run', date('Y-m-d H:i:s'));

// 5. Lança o bot em background (Linux/Ubuntu)
$script = realpath(__DIR__ . '/../bot/main.py');
if (!$script) {
    echo $ts() . "ERRO: bot/main.py não encontrado.\n";
    exit(1);
}

exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));

echo $ts() . "Bot iniciado (intervalo: {$intervalo_horas}h, próximo: " . date('H:i', time() + $intervalo_horas * 3600) . ").\n";
