<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

// Aba ativa: ml ou shopee
$aba = in_array($_GET['bot'] ?? 'ml', ['ml', 'shopee']) ? ($_GET['bot'] ?? 'ml') : 'ml';

$logFiles = [
    'ml'     => __DIR__ . '/storage/bot_ml.log',
    'shopee' => __DIR__ . '/storage/bot_shopee.log',
];
$logFile = $logFiles[$aba];

if (($_GET['action'] ?? '') === 'clear') {
    file_put_contents($logFile, '');
    header('Location: ' . BASE . '/logs-ml?bot=' . $aba);
    exit;
}

function lerLog(string $path): array {
    if (!file_exists($path)) {
        return ['conteudo' => "Nenhum log encontrado.\nClique em \"Rodar Bot\" para iniciar.", 'tamanho' => 0];
    }
    $tamanho = filesize($path);
    $raw     = file_get_contents($path);
    $raw     = str_replace(["\r\n", "\r"], "\n", $raw);
    $raw     = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    $linhas  = array_slice(explode("\n", $raw), -500);
    return ['conteudo' => implode("\n", $linhas), 'tamanho' => $tamanho];
}

function colorize(string $txt): string {
    $txt = htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $txt = preg_replace('/\[(ERROR)\]/',   '<span style="color:#f87171">[$1]</span>',   $txt);
    $txt = preg_replace('/\[(WARNING)\]/', '<span style="color:#fbbf24">[$1]</span>',   $txt);
    $txt = preg_replace('/\[(INFO)\]/',    '<span style="color:#34d399">[$1]</span>',   $txt);
    $txt = preg_replace('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', '<span style="color:#6b7280">$1</span>', $txt);
    return $txt;
}

$log = lerLog($logFile);

$fonte   = $aba === 'ml' ? 'ml' : 'shopee';
$botNome = $aba === 'ml' ? 'Bot ML' : 'Bot Shopee';
$logPath = $aba === 'ml' ? 'storage/bot_ml.log' : 'storage/bot_shopee.log';

layoutStart('logs_ml', 'Logs dos Bots');
?>

<style>
#log-terminal {
    background: #0b1a12;
    border: 1px solid #1a3326;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 68vh;
    font-family: 'Courier New', Courier, monospace;
}
#log-bar {
    background: #0f2219;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    border-bottom: 1px solid #1a3326;
}
#log-body { flex: 1; overflow-y: auto; padding: 16px; }
#log-body::-webkit-scrollbar { width: 6px; }
#log-body::-webkit-scrollbar-track { background: transparent; }
#log-body::-webkit-scrollbar-thumb { background: #1e4d35; border-radius: 3px; }
#log-body pre {
    margin: 0;
    font-size: 11.5px;
    line-height: 1.7;
    color: #86efac;
    white-space: pre-wrap;
    word-break: break-word;
}
.dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
.dot-red    { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green  { background: #22c55e; }
</style>

<!-- Tabs ML / Shopee -->
<div class="flex items-center gap-2 mb-5">
    <a href="<?= BASE ?>/logs-ml?bot=ml"
       class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all border
              <?= $aba === 'ml'
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                  : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700' ?>">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
        Bot ML
    </a>
    <a href="<?= BASE ?>/logs-ml?bot=shopee"
       class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all border
              <?= $aba === 'shopee'
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                  : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700' ?>">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
        Bot Shopee
    </a>
</div>

<!-- Barra de ações -->
<div class="flex items-center justify-between mb-3 flex-wrap gap-2">
    <p class="text-sm text-gray-500">
        <?= $botNome ?> — <span class="font-mono text-xs text-gray-400"><?= $logPath ?></span>
        <?php if ($log['tamanho'] > 0): ?>
            <span class="ml-2 text-xs text-gray-400">(<?= number_format($log['tamanho'] / 1024, 1) ?> KB)</span>
        <?php endif; ?>
        <span class="ml-2 inline-flex items-center gap-1 text-xs text-emerald-500">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block"></span> ao vivo
        </span>
    </p>
    <div class="flex gap-2 flex-wrap">
        <button type="button" onclick="liberarBot(this)"
           class="flex items-center gap-2 bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            Liberar
        </button>
        <button type="button" onclick="rodarBot(this)"
           class="flex items-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700 px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Rodar Bot
        </button>
        <a href="<?= BASE ?>/logs-ml?bot=<?= $aba ?>"
           class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Atualizar
        </a>
        <a href="<?= BASE ?>/logs-ml?bot=<?= $aba ?>&action=clear"
           onclick="return confirm('Limpar logs do <?= $botNome ?>?')"
           class="flex items-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Limpar
        </a>
    </div>
</div>

<!-- Terminal -->
<div id="log-terminal">
    <div id="log-bar">
        <span class="dot dot-red"></span>
        <span class="dot dot-yellow"></span>
        <span class="dot dot-green"></span>
        <span style="font-size:11px; color:#4ade80; margin-left:8px; opacity:0.7">🤖 viana/<?= $logPath ?></span>
        <span style="margin-left:auto;font-size:10px;color:#22c55e;background:rgba(34,197,94,0.1);padding:2px 8px;border-radius:20px;border:1px solid rgba(34,197,94,0.2)"><?= $botNome ?></span>
    </div>
    <div id="log-body">
        <pre id="log-pre"><?= colorize($log['conteudo']) ?></pre>
    </div>
</div>

<script>
const BOT_FONTE = '<?= $aba ?>';
const body = document.getElementById('log-body');
const pre  = document.getElementById('log-pre');
function scrollBottom() { body.scrollTop = body.scrollHeight; }
scrollBottom();

let lastSize = <?= $log['tamanho'] ?>;
setInterval(() => {
    fetch(BASE + '/api/log_tail.php?bot=' + BOT_FONTE)
        .then(r => r.json())
        .then(d => {
            if (d.size !== lastSize) {
                lastSize = d.size;
                pre.innerHTML = d.html;
                scrollBottom();
            }
        })
        .catch(() => {});
}, 4000);

function appendStatus(txt) {
    pre.innerHTML += '\n<span style="color:#4ade80">' + txt.replace(/[&<>]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s])) + '</span>';
    scrollBottom();
}

function liberarBot(btn) {
    if (!confirm('Parar processo preso e liberar lock do ' + (BOT_FONTE === 'ml' ? 'Bot ML' : 'Bot Shopee') + '?')) return;
    btn.disabled = true;
    fetch(BASE + '/api/bot_lock_clear.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fonte: BOT_FONTE})
    }).then(r => r.json()).then(data => {
        appendStatus((data.ok ? '✅ ' : '❌ ') + (data.message || data.error || ''));
        setTimeout(() => location.reload(), 800);
    }).catch(() => { btn.disabled = false; appendStatus('Erro de rede.'); });
}

function rodarBot(btn) {
    btn.disabled = true;
    appendStatus('▶ Disparando ' + (BOT_FONTE === 'ml' ? 'Bot ML' : 'Bot Shopee') + '...');
    fetch(BASE + '/api/bot_run.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fonte: BOT_FONTE})
    }).then(r => r.json()).then(data => {
        appendStatus((data.ok ? '✅ ' : '❌ ') + (data.message || data.error || ''));
        btn.disabled = false;
    }).catch(() => { appendStatus('Erro de rede.'); btn.disabled = false; });
}
</script>

<?php layoutEnd(); ?>
