<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();

// Métricas do bot
$totalGrupos  = (int)$db->query('SELECT COUNT(*) FROM grupos WHERE ativo = 1')->fetchColumn();
$ofertasHoje  = (int)$db->query("SELECT COUNT(*) FROM ofertas WHERE date(coletado_em) = date('now','localtime')")->fetchColumn();
$enviosHoje   = (int)$db->query("SELECT COUNT(*) FROM historico WHERE date(enviado_em) = date('now','localtime')")->fetchColumn();
$sucessosHoje = (int)$db->query("SELECT COUNT(*) FROM historico WHERE status='sucesso' AND date(enviado_em) = date('now','localtime')")->fetchColumn();
$filaAtiva    = (int)$db->query("SELECT COUNT(*) FROM ofertas WHERE status IN ('nova','pronta')")->fetchColumn();

$ultimosFull = $db->query("
    SELECT h.*,
           COALESCE(o.nome, l.nome_produto) as nome_produto,
           g.nome as nome_grupo
    FROM historico h
    LEFT JOIN links l ON l.id = h.link_id
    LEFT JOIN ofertas o ON o.id = h.link_id
    LEFT JOIN grupos g ON g.id = h.grupo_id
    ORDER BY h.enviado_em DESC
    LIMIT 8
")->fetchAll();

// Cliques (rastreamento do portal)
try {
    $cliquesHoje = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE date(clicado_em) = date('now','localtime')")->fetchColumn();
    $cliques7d   = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE clicado_em > datetime('now', '-7 days', 'localtime')")->fetchColumn();
    $topClicados = $db->query("
        SELECT o.nome, o.id, COUNT(c.id) AS total
        FROM clicks c
        JOIN ofertas o ON o.id = c.oferta_id
        WHERE c.clicado_em > datetime('now', '-7 days', 'localtime')
        GROUP BY c.oferta_id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll();
} catch (\PDOException) {
    $cliquesHoje = 0;
    $cliques7d   = 0;
    $topClicados = [];
}

$apiConfigurada = getConfig('evolution_url') !== '';
$botKey         = getConfig('openrouter_apikey') !== '';

layoutStart('index', 'Dashboard');
?>

<?php if (!$apiConfigurada || !$botKey): ?>
    <div class="mb-6 flex flex-col items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <p class="text-sm text-amber-800">
            <strong>Configuração incompleta.</strong>
            <?= !$apiConfigurada ? 'Evolution API não configurada. ' : '' ?>
            <?= !$botKey ? 'OpenRouter API Key não configurada. ' : '' ?>
            <a href="<?= BASE ?>/config" class="underline ml-1">Configurar agora →</a>
        </p>
    </div>
<?php endif; ?>

<!-- Métricas do bot -->
<div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-5">

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Grupos Ativos</p>
            <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-emerald-600 mb-3"><?= $totalGrupos ?></p>
        <a href="<?= BASE ?>/grupos" class="text-xs text-emerald-600 font-medium hover:underline">Gerenciar grupos →</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Ofertas Hoje</p>
            <div class="w-8 h-8 bg-sky-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-sky-600 mb-3"><?= $ofertasHoje ?></p>
        <a href="<?= BASE ?>/fila" class="text-xs text-sky-600 font-medium hover:underline">Ver fila →</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Aguardando Envio</p>
            <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-amber-600 mb-3"><?= $filaAtiva ?></p>
        <a href="<?= BASE ?>/fila" class="text-xs text-amber-600 font-medium hover:underline">Abrir fila →</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Envios Hoje</p>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= $sucessosHoje ?>/<?= $enviosHoje ?> OK</span>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-3"><?= $enviosHoje ?></p>
        <a href="<?= BASE ?>/historico" class="text-xs text-gray-500 font-medium hover:underline">Ver histórico →</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Cliques Hoje</p>
            <div class="w-8 h-8 bg-teal-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-teal-600 mb-1"><?= $cliquesHoje ?></p>
        <p class="text-xs text-gray-400"><?= $cliques7d ?> nos últimos 7 dias</p>
    </div>

</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <!-- Últimos envios -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-2">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-800">Últimos Envios</h2>
            <a href="<?= BASE ?>/historico" class="text-xs text-emerald-600 hover:underline">Ver todos</a>
        </div>
        <?php if (empty($ultimosFull)): ?>
            <div class="px-4 py-10 text-center text-sm text-gray-400 sm:px-6">Nenhum envio registrado ainda. Rode o bot para começar!</div>
        <?php else: ?>
            <div class="divide-y divide-gray-100 sm:hidden">
                <?php foreach ($ultimosFull as $u): ?>
                    <div class="space-y-3 px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($u['nome_produto'] ?? '—') ?></p>
                                <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($u['nome_grupo'] ?? '—') ?></p>
                            </div>
                            <?php if ($u['status'] === 'sucesso'): ?>
                                <span class="inline-flex flex-shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">OK</span>
                            <?php else: ?>
                                <span class="inline-flex flex-shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Erro</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs font-mono text-gray-400"><?= substr($u['enviado_em'], 11, 5) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full min-w-[520px]">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr>
                            <th class="px-6 py-2 text-left text-xs font-semibold text-gray-400">Produto</th>
                            <th class="px-6 py-2 text-left text-xs font-semibold text-gray-400">Grupo</th>
                            <th class="px-6 py-2 text-left text-xs font-semibold text-gray-400">Hora</th>
                            <th class="px-6 py-2 text-left text-xs font-semibold text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($ultimosFull as $u): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="max-w-[160px] truncate px-6 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['nome_produto'] ?? '—') ?></td>
                                <td class="max-w-[120px] truncate px-6 py-3 text-sm text-gray-500"><?= htmlspecialchars($u['nome_grupo'] ?? '—') ?></td>
                                <td class="whitespace-nowrap px-6 py-3 font-mono text-xs text-gray-400"><?= substr($u['enviado_em'], 11, 5) ?></td>
                                <td class="px-6 py-3">
                                    <?php if ($u['status'] === 'sucesso'): ?>
                                        <span class="inline-block rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">OK</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Erro</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Painel lateral -->
    <div class="space-y-4">

        <!-- Status do bot -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Status do Bot</h2>
            <div class="space-y-2 mb-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Evolution API</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $apiConfigurada ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $apiConfigurada ? '✓ OK' : '✗ Não configurado' ?>
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">OpenRouter IA</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $botKey ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $botKey ? '✓ OK' : '✗ Não configurado' ?>
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Grupos ativos</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $totalGrupos > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $totalGrupos ?> grupo(s)
                    </span>
                </div>
            </div>
            <a href="<?= BASE ?>/fila" class="btn-primary flex w-full justify-center text-center text-sm">
                📥 Rodar Bot Agora
            </a>
        </div>

        <!-- Mais Clicados -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800">Mais Clicados</h2>
                <span class="text-xs text-gray-400">últimos 7 dias</span>
            </div>
            <?php if (empty($topClicados)): ?>
                <p class="text-xs text-gray-400 py-2">Nenhum clique registrado ainda.</p>
            <?php else: ?>
                <div class="space-y-2">
                <?php
                $maxCliques = (int)($topClicados[0]['total'] ?? 1);
                foreach ($topClicados as $i => $tc):
                    $pct = $maxCliques > 0 ? round($tc['total'] / $maxCliques * 100) : 0;
                ?>
                <div>
                    <div class="flex items-center justify-between mb-0.5">
                        <p class="text-xs text-gray-700 truncate max-w-[75%]"><?= htmlspecialchars(mb_substr($tc['nome'], 0, 40, 'UTF-8'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <span class="text-xs font-bold text-teal-600 ml-2 flex-shrink-0"><?= $tc['total'] ?></span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-teal-400 rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <!-- Atalhos -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Atalhos</h2>
            <div class="space-y-1">
                <a href="<?= BASE ?>/fila" class="flex items-center gap-2 text-sm text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 px-2 py-1.5 rounded-lg transition">
                    <span>📥</span> Fila de ofertas
                </a>
                <a href="<?= BASE ?>/logs" class="flex items-center gap-2 text-sm text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 px-2 py-1.5 rounded-lg transition">
                    <span>🖥️</span> Logs do sistema
                </a>
                <a href="<?= BASE ?>/grupos" class="flex items-center gap-2 text-sm text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 px-2 py-1.5 rounded-lg transition">
                    <span>👥</span> Sincronizar grupos
                </a>
                <a href="<?= BASE ?>/config" class="flex items-center gap-2 text-sm text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 px-2 py-1.5 rounded-lg transition">
                    <span>⚙️</span> Configurações
                </a>
            </div>
        </div>

    </div>
</div>

<?php layoutEnd(); ?>

