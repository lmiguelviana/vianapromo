<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$logFile = __DIR__ . '/storage/bot_shopee.log';

if (($_GET['action'] ?? '') === 'clear') {
    file_put_contents($logFile, '');
    header('Location: ' . BASE . '/logs-shopee');
    exit;
}

function lerLog(string $path): array {
    if (!file_exists($path)) {
        return ['conteudo' => "Nenhum log encontrado.\nClique em \"Bot Shopee\" na Fila para iniciar.", 'tamanho' => 0];
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

layoutStart('logs_shopee', 'Logs Bot Shopee');
?>

<style>
#log-terminal {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 72vh;
    font-family: 'Courier New', Courier, monospace;
}
#log-bar {
    background: #1e293b;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    border-bottom: 1px solid #334155;
}
#log-body { flex: 1; overflow-y: auto; padding: 16px; }
#log-body pre {
    margin: 0;
    font-size: 11.5px;
    line-height: 1.65;
    color: #94a3b8;
    white-space: pre-wrap;
    word-break: break-word;
}
.dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
.dot-red    { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green  { background: #10b981; }
</style>

<!-- Cabeçalho -->
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500">
        Bot Shopee —
        <span class="font-mono text-xs text-gray-400">storage/bot_shopee.log</span>
        <?php if ($log['tamanho'] > 0): ?>
            <span class="ml-2 text-xs text-gray-400">(<?= number_format($log['tamanho'] / 1024, 1) ?> KB)</span>
        <?php endif; ?>
        <span class="ml-2 inline-flex items-center gap-1 text-xs text-orange-500">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse inline-block"></span> ao vivo
        </span>
    </p>
    <div class="flex gap-2">
        <button type="button" onclick="liberarBotShopee(this)"
           class="flex items-center gap-2 bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-4 py-2 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
            </svg>
            Liberar Bot
        </button>
        <a href="<?= BASE ?>/logs-shopee"
           class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Atualizar
        </a>
        <a href="<?= BASE ?>/logs-shopee?action=clear"
           onclick="return confirm('Limpar logs do Bot Shopee?')"
           class="flex items-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
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
        <span style="font-size:11px; color:#64748b; margin-left:8px;">🛒 viana/storage/bot_shopee.log</span>
    </div>
    <div id="log-body">
        <pre id="log-pre"><?= colorize($log['conteudo']) ?></pre>
    </div>
</div>

<script>
const body = document.getElementById('log-body');
const pre  = document.getElementById('log-pre');
function scrollBottom() { body.scrollTop = body.scrollHeight; }
scrollBottom();

let lastSize = <?= $log['tamanho'] ?>;
setInterval(() => {
    fetch(BASE + '/api/log_tail.php?bot=shopee')
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

function liberarBotShopee(btn) {
    if (!confirm('Parar processo preso e liberar lock do Bot Shopee?')) return;
    btn.disabled = true;
    fetch(BASE + '/api/bot_lock_clear.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fonte: 'shopee'})
    }).then(r => r.json()).then(data => {
        alert((data.ok ? 'OK: ' : 'Erro: ') + (data.message || data.error || ''));
        location.reload();
    }).catch(() => {
        btn.disabled = false;
        alert('Erro de rede ao liberar Bot Shopee.');
    });
}
</script>

<?php layoutEnd(); ?>
