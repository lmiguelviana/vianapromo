<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();

// Paginação
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$por_pag  = 12;
$offset   = ($pagina - 1) * $por_pag;
$filtro   = $_GET['status'] ?? 'todas';

// Query com filtro
$where = $filtro !== 'todas' ? "WHERE status = ?" : "WHERE 1=1";
$params = $filtro !== 'todas' ? [$filtro] : [];

$total = $db->prepare("SELECT COUNT(*) FROM ofertas $where");
$total->execute($params);
$total = (int)$total->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pag));
$maxId = (int)($db->query("SELECT COALESCE(MAX(id),0) FROM ofertas")->fetchColumn());

$stmt = $db->prepare("SELECT * FROM ofertas $where ORDER BY coletado_em DESC LIMIT $por_pag OFFSET $offset");
$stmt->execute($params);
$ofertas = $stmt->fetchAll();

// Contadores por status
$contadores = $db->query("SELECT status, COUNT(*) as c FROM ofertas GROUP BY status")->fetchAll();
$counts = ['todas' => 0];
foreach ($contadores as $c) {
    $counts[$c['status']] = (int)$c['c'];
    $counts['todas'] += (int)$c['c'];
}

$bot_ativo = (getConfig('bot_ml_ativo') !== '0') || (getConfig('bot_shopee_ativo') !== '0');

$badge_status = [
    'nova'     => 'bg-blue-100 text-blue-800',
    'pronta'   => 'bg-amber-100 text-amber-800',
    'enviada'  => 'bg-emerald-100 text-emerald-800',
    'erro_ia'  => 'bg-red-100 text-red-800',
    'rejeitada'=> 'bg-gray-100 text-gray-600',
    'adiada'   => 'bg-orange-100 text-orange-700',
];

$label_status = [
    'nova'      => 'Nova',
    'pronta'    => 'Pronta p/ envio',
    'enviada'   => 'Enviada',
    'erro_ia'   => 'Erro IA',
    'rejeitada' => 'Rejeitada',
    'adiada'    => 'Adiada',
];

layoutStart('fila', 'Fila de Ofertas');
toast();
?>

<!-- Pill de polling: aparece quando chegam ofertas novas -->
<div id="poll-pill" class="hidden fixed top-5 left-1/2 z-50"
     style="transform:translateX(-50%)">
    <button onclick="smoothReload()"
        class="flex items-center gap-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold pl-3.5 pr-4 py-2.5 rounded-full shadow-2xl transition-all duration-200 hover:scale-105 active:scale-95"
        style="animation:pillSlideDown .4s cubic-bezier(.34,1.56,.64,1) both">
        <span class="relative flex h-2.5 w-2.5 flex-shrink-0">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-50"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-white/90"></span>
        </span>
        <span id="poll-count">Novas ofertas chegaram</span>
        <svg class="w-3.5 h-3.5 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
    </button>
</div>
<style>
@keyframes pillSlideDown {
    from { opacity:0; transform:translateX(-50%) translateY(-16px) scale(.92); }
    to   { opacity:1; transform:translateX(-50%) translateY(0)      scale(1);   }
}
</style>

<!-- Filtros por status -->
<div class="flex items-center gap-2 mb-6 flex-wrap">
    <?php
    $tabs = ['todas' => 'Todas', 'nova' => 'Novas', 'pronta' => 'Prontas', 'adiada' => 'Adiadas', 'enviada' => 'Enviadas', 'rejeitada' => 'Rejeitadas'];
    foreach ($tabs as $key => $label):
        $ativo = $filtro === $key;
        $cnt   = $counts[$key] ?? 0;
    ?>
        <a href="<?= BASE ?>/fila?status=<?= $key ?>"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
                <?= $ativo ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' ?>">
            <?= $label ?>
            <span class="text-xs <?= $ativo ? 'bg-emerald-500' : 'bg-gray-100 text-gray-500' ?> px-1.5 py-0.5 rounded-full"><?= $cnt ?></span>
        </a>
    <?php endforeach; ?>

    <div class="ml-auto flex items-center gap-2">
        <button onclick="limparFila('rejeitada')" title="Apaga só as rejeitadas"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Limpar Rejeitadas
        </button>
        <button onclick="limparFila('todas')" title="Apaga TODAS as ofertas"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Limpar Tudo
        </button>
        <button onclick="toggleBot(this)" id="btn-toggle-bot"
            data-ativo="<?= $bot_ativo ? '1' : '0' ?>"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border transition
                <?= $bot_ativo
                    ? 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100'
                    : 'bg-red-50 border-red-300 text-red-700 hover:bg-red-100' ?>">
            <?php if ($bot_ativo): ?>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Bot Ligado
            <?php else: ?>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Bot Pausado
            <?php endif; ?>
        </button>
        <button onclick="liberarLock(this)" title="Remove o arquivo bot.lock caso o bot tenha travado"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-500 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            Liberar Lock
        </button>
        <button onclick="rodarBot('ml')" id="btn-bot-ml"
            class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Bot ML
        </button>
        <button onclick="rodarBot('shopee')" id="btn-bot-shopee"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Bot Shopee
        </button>
    </div>
</div>

<?php if (empty($ofertas)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-16 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        </div>
        <p class="text-gray-500 text-sm font-medium mb-1">Nenhuma oferta encontrada</p>
        <p class="text-gray-400 text-xs">Clique em "Rodar Bot Agora" para coletar ofertas do Mercado Livre.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <?php foreach ($ofertas as $o): ?>
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col" id="oferta-<?= $o['id'] ?>">

                <!-- Imagem -->
                <?php $img = $o['imagem_path'] && file_exists($o['imagem_path'])
                    ? BASE . '/uploads/' . basename($o['imagem_path'])
                    : ($o['imagem_url'] ?: ''); ?>
                <?php if ($img): ?>
                    <div class="h-36 bg-gray-100 overflow-hidden">
                        <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-full h-full object-cover">
                    </div>
                <?php else: ?>
                    <div class="h-36 bg-gray-50 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                <?php endif; ?>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded <?= $badge_status[$o['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= $label_status[$o['status']] ?? $o['status'] ?>
                        </span>
                        <span class="text-xs font-bold text-emerald-600"><?= $o['desconto_pct'] ?>% OFF</span>
                    </div>

                    <p class="text-sm font-semibold text-gray-800 line-clamp-2 mb-2"><?= htmlspecialchars($o['nome']) ?></p>

                    <div class="flex items-center gap-2 mb-2">
                        <?php if ($o['preco_de'] > 0): ?>
                            <span class="text-xs text-gray-400 line-through">R$ <?= number_format($o['preco_de'], 2, ',', '.') ?></span>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-emerald-600">R$ <?= number_format($o['preco_por'], 2, ',', '.') ?></span>
                        <span class="text-xs text-gray-400"><?= $o['fonte'] ?></span>
                    </div>

                    <!-- Texto da IA (se gerado) -->
                    <?php if ($o['mensagem_ia']): ?>
                        <div class="text-xs text-gray-500 bg-gray-50 rounded-lg p-2 mb-3 line-clamp-3 font-mono">
                            <?= htmlspecialchars(substr($o['mensagem_ia'], 0, 120)) ?>...
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-1.5 mt-auto pt-3 border-t border-gray-100">
                        <a href="<?= htmlspecialchars($o['url_afiliado']) ?>" target="_blank"
                            class="flex-1 text-center text-xs text-emerald-600 hover:underline truncate">
                            Ver produto
                        </a>
                        <?php if ($o['status'] !== 'rejeitada' && $o['status'] !== 'enviada'): ?>
                            <!-- Enviar -->
                            <button onclick="enviarOferta(<?= $o['id'] ?>, this)"
                                title="Enviar agora para o WhatsApp"
                                class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                Enviar
                            </button>
                            <!-- Adiar: esconde sem blacklist, não aparece no portal -->
                            <button onclick="adiarOferta(<?= $o['id'] ?>)"
                                title="Adiar: some da fila agora mas pode voltar depois"
                                class="p-2 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                            <!-- Remover: apaga sem blacklist, bot pode recolher -->
                            <button onclick="removerOferta(<?= $o['id'] ?>)"
                                title="Remover do site (sem blacklist — bot pode trazer de novo)"
                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <!-- Rejeitar permanente: vai pra blacklist -->
                            <button onclick="rejeitarOferta(<?= $o['id'] ?>)"
                                title="Rejeitar permanente: nunca mais aparece (blacklist)"
                                class="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </button>
                        <?php elseif ($o['status'] === 'enviada'): ?>
                            <span class="text-xs text-emerald-600 font-medium flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Enviada
                            </span>
                            <button onclick="enviarOferta(<?= $o['id'] ?>, this)"
                                title="Reenviar para o WhatsApp"
                                class="flex items-center gap-1 px-2.5 py-1.5 bg-gray-100 hover:bg-emerald-600 hover:text-white text-gray-500 text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reenviar
                            </button>
                            <button onclick="removerOferta(<?= $o['id'] ?>)"
                                title="Remover do site (bot pode trazer de novo)"
                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        <?php elseif ($o['status'] === 'adiada'): ?>
                            <button onclick="enviarOferta(<?= $o['id'] ?>, this)"
                                title="Enviar agora"
                                class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                Enviar
                            </button>
                            <button onclick="removerOferta(<?= $o['id'] ?>)"
                                title="Remover"
                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginação -->
    <?php paginacao($pagina, $total_paginas, $total, 'pagina', ['status' => $filtro]); ?>
<?php endif; ?>

<!-- Log do bot (última execução) -->
<div id="bot-log" class="hidden mt-6 bg-gray-900 rounded-xl p-4">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
        <span class="text-xs text-gray-400 font-mono">Executando bot...</span>
    </div>
    <pre id="bot-log-texto" class="text-xs text-emerald-400 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto"></pre>
</div>

<script>
function rejeitarOferta(id) {
    if (!confirm('Rejeitar PERMANENTE? Este produto nunca mais aparecerá (vai para a blacklist).')) return;
    fetch(BASE + '/api/fila.php?action=rejeitar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            document.getElementById('oferta-' + id)?.remove();
            showToast('Oferta rejeitada permanentemente.');
        } else {
            showToast(data.error, 'error');
        }
    });
}

function adiarOferta(id) {
    fetch(BASE + '/api/fila.php?action=adiar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            document.getElementById('oferta-' + id)?.remove();
            showToast('Oferta adiada. Você pode enviá-la depois na aba "Adiadas".');
        } else {
            showToast(data.error, 'error');
        }
    });
}

function removerOferta(id) {
    if (!confirm('Remover do site? O bot pode trazer este produto novamente na próxima busca.')) return;
    fetch(BASE + '/api/fila.php?action=remover', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            document.getElementById('oferta-' + id)?.remove();
            showToast('Oferta removida.');
        } else {
            showToast(data.error, 'error');
        }
    });
}

function liberarLock(btn) {
    btn.disabled = true;
    fetch(BASE + '/api/bot_lock_clear.php', {method: 'POST'})
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.ok) showToast('🔓 ' + data.message);
            else showToast(data.error || 'Erro ao limpar lock.', 'error');
        })
        .catch(() => { btn.disabled = false; showToast('Erro de rede.', 'error'); });
}

function toggleBot(btn) {
    const ativo = btn.dataset.ativo === '1';
    btn.disabled = true;

    fetch(BASE + '/api/bot_toggle.php', {method: 'POST'})
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (!data.ok) { showToast(data.error || 'Erro ao alterar estado do bot.', 'error'); return; }
            const novoAtivo = data.bot_ativo === '1';
            btn.dataset.ativo = novoAtivo ? '1' : '0';
            if (novoAtivo) {
                btn.className = 'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border transition bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100';
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Bot Ligado';
                showToast('✅ Bot reativado — voltará a coletar no próximo ciclo.');
            } else {
                btn.className = 'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border transition bg-red-50 border-red-300 text-red-700 hover:bg-red-100';
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Bot Pausado';
                showToast('⏸ Bot pausado — coletas e envios automáticos suspensos.');
            }
        })
        .catch(() => { btn.disabled = false; showToast('Erro de rede.', 'error'); });
}

function rodarBot(fonte) {
    const btnId = fonte === 'ml' ? 'btn-bot-ml' : 'btn-bot-shopee';
    const btn = document.getElementById(btnId);
    const label = fonte === 'ml' ? 'Bot ML' : 'Bot Shopee';
    const log = document.getElementById('bot-log');
    const logTxt = document.getElementById('bot-log-texto');
    const spinner = '<span class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>';

    btn.disabled = true;
    btn.innerHTML = spinner + ' Iniciando...';
    log.classList.remove('hidden');
    logTxt.textContent = `Disparando ${label} em background...\n`;

    fetch(BASE + '/api/bot_run.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fonte})
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            const ico = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            btn.innerHTML = ico + ' ' + label;

            const logUrl = BASE + '/logs?bot=' + fonte;
            if (data.ok) {
                logTxt.innerHTML = `✅ ${label} iniciado em background!\n\n<a href="${logUrl}" style="color:#34d399;text-decoration:underline">→ Abrir Logs do ${label}</a>`;
                showToast(`🤖 ${label} rodando! Veja os logs para acompanhar.`);
            } else if (data.running) {
                logTxt.innerHTML = `⚠️ ${label} já está em execução.\n<a href="${logUrl}" style="color:#fbbf24;text-decoration:underline">→ Abrir Logs do ${label}</a>`;
                showToast(`${label} já está rodando!`, 'error');
            } else {
                logTxt.textContent = data.error || 'Erro desconhecido';
                showToast('Erro ao iniciar o bot.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = label;
            showToast('Erro de rede.', 'error');
        });
}

function limparFila(tipo) {
    const msg = tipo === 'rejeitada'
        ? 'Apagar TODAS as ofertas rejeitadas? Isso não pode ser desfeito.'
        : 'Apagar TODAS as ofertas (exceto as já enviadas)? Isso não pode ser desfeito.';

    if (!confirm(msg)) return;

    const fd = new FormData();
    fd.append('tipo', tipo);

    fetch(BASE + '/api/fila_limpar.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showToast('🗑️ ' + data.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.error || 'Erro ao limpar.', 'error');
            }
        })
        .catch(() => showToast('Erro de rede.', 'error'));
}

function enviarOferta(id, btn) {
    if (!confirm('Enviar esta oferta agora para todos os grupos ativos?')) return;

    const card = document.getElementById('oferta-' + id);
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Enviando...';

    fetch(BASE + '/api/oferta_enviar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.message);
            // Atualiza o card visualmente sem recarregar
            const badgeEl = card.querySelector('.text-xs.font-semibold.px-2');
            if (badgeEl) { badgeEl.textContent = 'Enviada'; badgeEl.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800'; }
            btn.closest('.flex.items-center.gap-2').innerHTML = '<span class="text-xs text-emerald-600 font-medium flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Enviada</span>';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Enviar';
            showToast(data.error || 'Erro ao enviar.', 'error');
        }
    })
    .catch(() => {
        // Resposta perdida (timeout do proxy) — verifica status real no banco
        fetch(BASE + '/api/fila.php?action=status&id=' + id)
            .then(r => r.json())
            .then(s => {
                if (s.status === 'enviada') {
                    showToast('✅ Enviada com sucesso!');
                    const badgeEl = card ? card.querySelector('.text-xs.font-semibold.px-2') : null;
                    if (badgeEl) { badgeEl.textContent = 'Enviada'; badgeEl.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800'; }
                    const actions = btn.closest('.flex.items-center.gap-2');
                    if (actions) actions.innerHTML = '<span class="text-xs text-emerald-600 font-medium flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Enviada</span>';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Enviar';
                    showToast('Erro de rede. Tente novamente.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = 'Enviar';
                showToast('Erro de rede.', 'error');
            });
    });
}
</script>

<script>
// ── Silent Polling — Fila de Ofertas ─────────────────────────────────────────
(function() {
    const MAX_ID   = <?= $maxId ?>;
    const FILTRO   = '<?= addslashes($filtro) ?>';
    const ENDPOINT = BASE + '/api/fila_poll.php';
    let   timer;

    // Fade-in suave ao carregar a página
    const main = document.querySelector('main > div');
    if (main) {
        main.style.opacity = '0';
        main.style.transition = 'opacity .35s ease';
        requestAnimationFrame(() => requestAnimationFrame(() => { main.style.opacity = '1'; }));
    }

    async function check() {
        try {
            const res  = await fetch(`${ENDPOINT}?last_id=${MAX_ID}&status=${encodeURIComponent(FILTRO)}`, { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok && data.novas > 0) {
                const pill  = document.getElementById('poll-pill');
                const label = document.getElementById('poll-count');
                label.textContent = data.novas === 1
                    ? '1 nova oferta chegou — Atualizar'
                    : `${data.novas} novas ofertas chegaram — Atualizar`;
                pill.classList.remove('hidden');
                clearInterval(timer); // para de checar, já achou algo novo
            }
        } catch (_) { /* ignora erro de rede silenciosamente */ }
    }

    // Primeira verificação após 8s, depois a cada 20s
    setTimeout(check, 8000);
    timer = setInterval(check, 20000);

    // Reload suave: fade-out → reload → fade-in
    window.smoothReload = function() {
        document.getElementById('poll-pill').classList.add('hidden');
        const el = document.querySelector('main > div');
        if (el) { el.style.transition = 'opacity .22s ease'; el.style.opacity = '0'; }
        setTimeout(() => location.reload(), 240);
    };
})();
</script>

<?php layoutEnd(); ?>


