<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();

$filtroPlataforma = $_GET['plataforma'] ?? '';
$filtroStatus     = $_GET['status'] ?? '';
$pagina           = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina        = 20;
$offset           = ($pagina - 1) * $porPagina;

$where = ['1=1'];
$params = [];

if ($filtroPlataforma) {
    $where[] = 'l.plataforma = ?';
    $params[] = $filtroPlataforma;
}
if ($filtroStatus) {
    $where[] = 'h.status = ?';
    $params[] = $filtroStatus;
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("
    SELECT COUNT(*) FROM historico h
    LEFT JOIN links l ON l.id = h.link_id
    WHERE $whereStr
");
$total->execute($params);
$totalRegistros = (int)$total->fetchColumn();
$totalPaginas = (int)ceil($totalRegistros / $porPagina);
$maxId = (int)($db->query("SELECT COALESCE(MAX(id),0) FROM historico")->fetchColumn());

$stmt = $db->prepare("
    SELECT h.*, l.nome_produto, l.plataforma, g.nome as nome_grupo
    FROM historico h
    LEFT JOIN links l ON l.id = h.link_id
    LEFT JOIN grupos g ON g.id = h.grupo_id
    WHERE $whereStr
    ORDER BY h.enviado_em DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $porPagina, $offset]);
$registros = $stmt->fetchAll();

layoutStart('historico', 'Histórico de Envios');
?>

<!-- Pill de polling: aparece quando chegam novos envios -->
<div id="poll-pill" class="hidden fixed top-5 left-1/2 z-50"
     style="transform:translateX(-50%)">
    <button onclick="smoothReload()"
        class="flex items-center gap-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold pl-3.5 pr-4 py-2.5 rounded-full shadow-2xl transition-all duration-200 hover:scale-105 active:scale-95"
        style="animation:pillSlideDown .4s cubic-bezier(.34,1.56,.64,1) both">
        <span class="relative flex h-2.5 w-2.5 flex-shrink-0">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-50"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-white/90"></span>
        </span>
        <span id="poll-count">Novos envios chegaram</span>
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

<!-- Filtros -->
<form method="GET" class="flex items-center gap-3 mb-6">
    <select name="plataforma" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <option value="">Todas as plataformas</option>
        <?php foreach (PLATAFORMAS as $cod => $p): ?>
            <option value="<?= $cod ?>" <?= $filtroPlataforma === $cod ? 'selected' : '' ?>><?= $p['label'] ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <option value="">Todos os status</option>
        <option value="sucesso" <?= $filtroStatus === 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
        <option value="erro" <?= $filtroStatus === 'erro' ? 'selected' : '' ?>>Erro</option>
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        Filtrar
    </button>
    <?php if ($filtroPlataforma || $filtroStatus): ?>
        <a href="<?= BASE ?>/historico" class="text-sm text-gray-500 hover:underline">Limpar</a>
    <?php endif; ?>
    <span class="ml-auto text-sm text-gray-400"><?= $totalRegistros ?> registro(s)</span>
</form>

<?php if (empty($registros)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <p class="text-gray-400 text-sm">Nenhum envio registrado ainda.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table>
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-gray-500">Data/Hora</th>
                    <th class="px-6 py-3 text-left text-gray-500">Plataforma</th>
                    <th class="px-6 py-3 text-left text-gray-500">Produto</th>
                    <th class="px-6 py-3 text-left text-gray-500">Grupo</th>
                    <th class="px-6 py-3 text-left text-gray-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($registros as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap font-mono"><?= $r['enviado_em'] ?></td>
                        <td class="px-6 py-3"><?= $r['plataforma'] ? badgePlataforma($r['plataforma']) : '<span class="text-gray-400 text-xs">—</span>' ?></td>
                        <td class="px-6 py-3 text-sm text-gray-800 max-w-xs truncate"><?= htmlspecialchars($r['nome_produto'] ?? '(link removido)') ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars($r['nome_grupo'] ?? '(grupo removido)') ?></td>
                        <td class="px-6 py-3">
                            <?php if ($r['status'] === 'sucesso'): ?>
                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-800">Sucesso</span>
                            <?php else: ?>
                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-red-100 text-red-800" title="<?= htmlspecialchars($r['mensagem_erro'] ?? '') ?>">Erro</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php paginacao($pagina, $totalPaginas, $totalRegistros, 'pagina', ['plataforma' => $filtroPlataforma, 'status' => $filtroStatus]); ?>
<?php endif; ?>

<script>
// ── Silent Polling — Histórico ───────────────────────────────────────────────
(function() {
    const MAX_ID   = <?= $maxId ?>;
    const ENDPOINT = BASE + '/api/historico_poll.php';
    let   timer;

    // Fade-in suave ao carregar
    const main = document.querySelector('main > div');
    if (main) {
        main.style.opacity = '0';
        main.style.transition = 'opacity .35s ease';
        requestAnimationFrame(() => requestAnimationFrame(() => { main.style.opacity = '1'; }));
    }

    async function check() {
        try {
            const res  = await fetch(`${ENDPOINT}?last_id=${MAX_ID}`, { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok && data.novas > 0) {
                const pill  = document.getElementById('poll-pill');
                const label = document.getElementById('poll-count');
                label.textContent = data.novas === 1
                    ? '1 novo envio registrado — Atualizar'
                    : `${data.novas} novos envios registrados — Atualizar`;
                pill.classList.remove('hidden');
                clearInterval(timer);
            }
        } catch (_) { /* silencioso */ }
    }

    // Primeira verificação após 10s, depois a cada 30s
    setTimeout(check, 10000);
    timer = setInterval(check, 30000);

    window.smoothReload = function() {
        document.getElementById('poll-pill').classList.add('hidden');
        const el = document.querySelector('main > div');
        if (el) { el.style.transition = 'opacity .22s ease'; el.style.opacity = '0'; }
        setTimeout(() => location.reload(), 240);
    };
})();
</script>

<?php layoutEnd(); ?>

