<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/ml_token.php';

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function pidRodandoBot(int $pid, string $fonte): bool {
    if ($pid <= 0) return false;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') return false;
    $cmdline = "/proc/$pid/cmdline";
    if (!file_exists($cmdline)) return false;
    $cmd = (string)@file_get_contents($cmdline);
    if ($cmd === '') return false;
    $cmd = str_replace("\0", " ", $cmd);
    return str_contains($cmd, 'main.py')
        && (str_contains($cmd, "--fonte $fonte") || str_contains($cmd, "--fonte=$fonte"));
}

function tailLog(string $path, int $lines = 8): array {
    if (!file_exists($path)) return ['Nenhum log ainda.'];
    $raw = str_replace(["\r\n", "\r"], "\n", (string)@file_get_contents($path));
    $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    $arr = array_values(array_filter(explode("\n", $raw), fn($l) => trim($l) !== ''));
    return array_slice($arr, -$lines);
}

function botInfo(string $fonte): array {
    $prefix = "bot_{$fonte}";
    $label = $fonte === 'ml' ? 'Bot ML' : 'Bot Shopee';
    $logPath = __DIR__ . "/storage/{$prefix}.log";
    $lockPath = __DIR__ . "/storage/{$prefix}.lock";

    $intervalo = max(1, (int)(getConfig("{$prefix}_intervalo_horas") ?: ($fonte === 'shopee' ? '12' : '6')));
    $ultimoRun = getConfig("{$prefix}_ultimo_run");
    $proximoRunTs = $ultimoRun ? strtotime($ultimoRun) + ($intervalo * 3600) : 0;
    $checkedAt = getConfig("{$prefix}_cron_checked_at");
    $checkedTs = $checkedAt ? strtotime($checkedAt) : 0;
    $staleMin = $checkedTs ? floor((time() - $checkedTs) / 60) : null;

    $pid = file_exists($lockPath) ? (int)trim((string)@file_get_contents($lockPath)) : 0;
    $lockAtivo = $pid > 0 && pidRodandoBot($pid, $fonte);

    return [
        'fonte' => $fonte,
        'prefix' => $prefix,
        'label' => $label,
        'ativo' => getConfig("{$prefix}_ativo") !== '0',
        'intervalo' => $intervalo,
        'ultimo_run' => $ultimoRun,
        'proximo_run_ts' => $proximoRunTs,
        'cron_checked_at' => $checkedAt,
        'cron_status' => getConfig("{$prefix}_cron_status") ?: 'sem_status',
        'cron_message' => getConfig("{$prefix}_cron_message") ?: 'Cron ainda não registrou status.',
        'cron_pid' => getConfig("{$prefix}_cron_pid"),
        'cron_last_start_at' => getConfig("{$prefix}_cron_last_start_at"),
        'stale_min' => $staleMin,
        'lock_path' => "storage/{$prefix}.lock",
        'lock_pid' => $pid,
        'lock_ativo' => $lockAtivo,
        'log_url' => BASE . ($fonte === 'ml' ? '/logs-ml' : '/logs-shopee'),
        'log_tail' => tailLog($logPath),
        'ml_token' => $fonte === 'ml' ? mlTokenInfo() : null,
    ];
}

$bots = [botInfo('ml'), botInfo('shopee')];

layoutStart('monitor_crons', 'Monitor dos Crons');
toast();
?>

<div class="mb-5 flex items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Monitor dos Crons</h1>
        <p class="text-sm text-gray-500">Acompanhe se os crons ML e Shopee estão acordando, pulando ou disparando.</p>
    </div>
    <a href="<?= BASE ?>/monitor-crons" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Atualizar</a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
<?php foreach ($bots as $bot):
    $fresh = $bot['stale_min'] !== null && $bot['stale_min'] <= 45;
    $cronBadge = $fresh
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : 'bg-red-100 text-red-700 border-red-200';
    $statusColor = match ($bot['cron_status']) {
        'iniciado' => 'bg-blue-100 text-blue-700 border-blue-200',
        'aguardando' => 'bg-amber-100 text-amber-700 border-amber-200',
        'rodando' => 'bg-sky-100 text-sky-700 border-sky-200',
        'pausado' => 'bg-gray-100 text-gray-700 border-gray-200',
        'erro' => 'bg-red-100 text-red-700 border-red-200',
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };
?>
    <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900"><?= h($bot['label']) ?></h2>
                <p class="text-xs text-gray-500"><?= $bot['ativo'] ? 'Ativo' : 'Pausado' ?> · intervalo <?= (int)$bot['intervalo'] ?>h</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full border <?= $cronBadge ?>">
                <?= $fresh ? 'Cron OK' : 'Cron sem sinal' ?>
            </span>
        </div>

        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs text-gray-400">Último check</p>
                    <p class="text-sm font-semibold text-gray-800"><?= $bot['cron_checked_at'] ? date('d/m H:i:s', strtotime($bot['cron_checked_at'])) : 'Nunca' ?></p>
                    <p class="text-xs text-gray-400"><?= $bot['stale_min'] !== null ? "{$bot['stale_min']} min atrás" : 'sem registro' ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs text-gray-400">Próximo run</p>
                    <p class="text-sm font-semibold text-gray-800"><?= $bot['proximo_run_ts'] ? date('d/m H:i', $bot['proximo_run_ts']) : 'Sem histórico' ?></p>
                    <p class="text-xs text-gray-400"><?= $bot['ultimo_run'] ? 'último: ' . date('d/m H:i', strtotime($bot['ultimo_run'])) : 'nunca rodou' ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs text-gray-400">Status do cron</p>
                    <span class="inline-flex mt-1 text-xs font-semibold px-2.5 py-1 rounded-full border <?= $statusColor ?>">
                        <?= h($bot['cron_status']) ?>
                    </span>
                </div>
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs text-gray-400">Lock</p>
                    <p class="text-sm font-semibold <?= $bot['lock_ativo'] ? 'text-sky-700' : 'text-emerald-700' ?>">
                        <?= $bot['lock_ativo'] ? "Rodando PID {$bot['lock_pid']}" : ($bot['lock_pid'] ? "Zumbi PID {$bot['lock_pid']}" : 'Livre') ?>
                    </p>
                    <p class="text-xs text-gray-400 font-mono"><?= h($bot['lock_path']) ?></p>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
                <p class="text-xs text-gray-400 mb-1">Mensagem do cron</p>
                <p class="text-sm text-gray-700"><?= h($bot['cron_message']) ?></p>
            </div>

            <?php if ($bot['fonte'] === 'ml' && $bot['ml_token']):
                $token = $bot['ml_token'];
                $tokenClass = match ($token['status']) {
                    'valido' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    'vence_logo' => 'bg-amber-100 text-amber-700 border-amber-200',
                    default => 'bg-red-100 text-red-700 border-red-200',
                };
            ?>
            <div class="border border-gray-100 rounded-lg p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Token Mercado Livre</p>
                        <span class="inline-flex text-xs font-semibold px-2.5 py-1 rounded-full border <?= $tokenClass ?>">
                            <?= h($token['label']) ?>
                        </span>
                    </div>
                    <button type="button" onclick="renovarML(this)" class="px-3 py-2 rounded-lg border border-yellow-300 bg-yellow-50 text-yellow-800 text-xs font-semibold hover:bg-yellow-100">
                        Renovar Token
                    </button>
                </div>
                <div class="mt-2 text-xs text-gray-500 space-y-0.5">
                    <p>Valido ate: <strong><?= $token['expires_at'] ? date('d/m H:i', (int)$token['expires_at']) : 'sem token' ?></strong></p>
                    <p>Ultima renovacao: <strong><?= $token['last_refresh_at'] ? date('d/m H:i:s', strtotime($token['last_refresh_at'])) : 'nunca' ?></strong></p>
                    <?php if ($token['last_refresh_message']): ?>
                        <p><?= h($token['last_refresh_message']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-gray-950 rounded-lg overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
                    <span class="text-xs text-gray-400">Últimas linhas do log</span>
                    <a href="<?= h($bot['log_url']) ?>" class="text-xs text-emerald-400 hover:text-emerald-300">abrir log</a>
                </div>
                <pre class="p-3 text-[11px] leading-relaxed text-gray-300 whitespace-pre-wrap max-h-52 overflow-y-auto"><?php foreach ($bot['log_tail'] as $line) echo h($line) . "\n"; ?></pre>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="liberarBot('<?= $bot['fonte'] ?>', this)" class="px-3 py-2 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 text-sm font-medium hover:bg-orange-100">Liberar Lock</button>
                <button type="button" onclick="rodarBot('<?= $bot['fonte'] ?>', this)" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Rodar Agora</button>
                <a href="<?= BASE ?>/config#<?= $bot['fonte'] === 'ml' ? 'bot_ml' : 'bot_shopee' ?>" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Configurar</a>
            </div>
        </div>
    </section>
<?php endforeach; ?>
</div>

<script>
function postJson(url, body) {
    return fetch(BASE + url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    }).then(r => r.json());
}

function liberarBot(fonte, btn) {
    btn.disabled = true;
    postJson('/api/bot_lock_clear.php', {fonte}).then(data => {
        alert((data.ok ? 'OK: ' : 'Erro: ') + (data.message || data.error || ''));
        location.reload();
    }).catch(() => {
        btn.disabled = false;
        alert('Erro de rede.');
    });
}

function rodarBot(fonte, btn) {
    btn.disabled = true;
    postJson('/api/bot_run.php', {fonte}).then(data => {
        alert((data.ok ? 'OK: ' : 'Erro: ') + (data.message || data.error || ''));
        setTimeout(() => location.reload(), 1000);
    }).catch(() => {
        btn.disabled = false;
        alert('Erro de rede.');
    });
}

function renovarML(btn) {
    btn.disabled = true;
    btn.textContent = 'Renovando...';
    postJson('/api/ml_refresh.php', {}).then(data => {
        alert((data.ok ? 'OK: ' : 'Erro: ') + (data.message || data.error || ''));
        location.reload();
    }).catch(() => {
        btn.disabled = false;
        btn.textContent = 'Renovar Token';
        alert('Erro de rede.');
    });
}
</script>

<?php layoutEnd(); ?>
