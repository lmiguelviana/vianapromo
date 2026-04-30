<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db   = getDB();
$csrf = csrfToken();

// Filtra por fonte
$fonteAtiva = strtoupper($_GET['fonte'] ?? 'ML');
if (!in_array($fonteAtiva, ['ML', 'SHP', 'MGZ'])) $fonteAtiva = 'ML';

$keywords = $db->prepare("SELECT * FROM keywords WHERE fonte = ? ORDER BY ativo DESC, keyword ASC");
$keywords->execute([$fonteAtiva]);
$keywords = $keywords->fetchAll();

$resumo = [];
foreach ($db->query("SELECT fonte, COUNT(*) as total, SUM(ativo) as ativos FROM keywords GROUP BY fonte")->fetchAll() as $r) {
    $resumo[$r['fonte']] = $r;
}

$fontes = [
    'ML'  => ['label' => 'Mercado Livre', 'color' => 'bg-orange-100 text-orange-700 border-orange-200'],
    'SHP' => ['label' => 'Shopee',        'color' => 'bg-red-100 text-red-700 border-red-200'],
    'MGZ' => ['label' => 'Magalu',        'color' => 'bg-blue-100 text-blue-700 border-blue-200'],
];

layoutStart('keywords', 'Keywords do Bot');
?>

<?php toast(); ?>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

<!-- Cabeçalho + Tabs por fonte -->
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-sm text-gray-500 mt-0.5">Palavras-chave usadas pelo bot para buscar ofertas. Ative, desative ou adicione novas.</h2>
    </div>
    <!-- Formulário inline de adição -->
    <div class="flex items-center gap-2 shrink-0">
        <select id="nova-fonte" class="text-sm border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-700">
            <option value="ML"  <?= $fonteAtiva === 'ML'  ? 'selected' : '' ?>>Mercado Livre</option>
            <option value="SHP" <?= $fonteAtiva === 'SHP' ? 'selected' : '' ?>>Shopee</option>
            <option value="MGZ" <?= $fonteAtiva === 'MGZ' ? 'selected' : '' ?>>Magalu</option>
        </select>
        <input id="nova-keyword" type="text" placeholder="nova keyword..." maxlength="120"
               class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 w-44 focus:outline-none focus:ring-2 focus:ring-emerald-400"
               onkeydown="if(event.key==='Enter') adicionarKeyword()">
        <button onclick="adicionarKeyword()"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Adicionar
        </button>
    </div>
</div>

<!-- Tabs por fonte -->
<div class="flex gap-2 mb-5 flex-wrap">
    <?php foreach ($fontes as $cod => $info): ?>
        <?php $r = $resumo[$cod] ?? ['total' => 0, 'ativos' => 0]; ?>
        <a href="?fonte=<?= $cod ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-semibold transition-colors
                  <?= $fonteAtiva === $cod
                      ? 'bg-emerald-600 text-white border-emerald-600'
                      : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700' ?>">
            <?= $info['label'] ?>
            <span class="ml-0.5 text-[11px] font-bold opacity-75"><?= (int)$r['ativos'] ?>/<?= (int)$r['total'] ?></span>
        </a>
    <?php endforeach ?>
</div>

<!-- Lista de keywords -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
            Keyword
        </p>
        <div class="flex items-center gap-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</p>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide w-12 text-right">Ação</p>
        </div>
    </div>

    <?php if (empty($keywords)): ?>
        <div class="py-10 text-center text-sm text-gray-400">Nenhuma keyword cadastrada para esta fonte.</div>
    <?php else: ?>
        <div class="divide-y divide-gray-50" id="keywords-list">
            <?php foreach ($keywords as $kw): ?>
            <div id="kw-<?= $kw['id'] ?>" class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 transition-colors group <?= !$kw['ativo'] ? 'opacity-50' : '' ?>">
                <span class="text-sm text-gray-800 font-mono <?= !$kw['ativo'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($kw['keyword']) ?></span>
                <div class="flex items-center gap-4">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $kw['ativo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $kw['ativo'] ? 'Ativa' : 'Inativa' ?>
                    </span>
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="toggleKeyword(<?= $kw['id'] ?>)"
                                title="<?= $kw['ativo'] ? 'Desativar' : 'Ativar' ?>"
                                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-amber-600 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <?= $kw['ativo']
                                    ? '<path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                                    : '<path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                                ?>
                            </svg>
                        </button>
                        <button onclick="removerKeyword(<?= $kw['id'] ?>, '<?= htmlspecialchars(addslashes($kw['keyword'])) ?>')"
                                title="Remover"
                                class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<p class="mt-4 text-xs text-gray-400">
    💡 Keywords inativas são ignoradas pelo bot no próximo ciclo de coleta. Alterações entram em vigor imediatamente.
</p>

<script>
const CSRF = document.querySelector('[name=csrf_token]').value;

async function apiCall(method, body) {
    const r = await fetch(BASE + '/api/keywords.php', {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body)
    });
    return r.json();
}

async function adicionarKeyword() {
    const inp   = document.getElementById('nova-keyword');
    const fonte = document.getElementById('nova-fonte').value;
    const kw    = inp.value.trim().toLowerCase();
    if (!kw) return;

    const d = await apiCall('POST', { fonte, keyword: kw });
    if (d.ok) {
        showToast('Keyword adicionada!');
        inp.value = '';
        setTimeout(() => location.reload(), 600);
    } else {
        showToast(d.error || 'Erro ao adicionar.', 'error');
    }
}

async function toggleKeyword(id) {
    const row = document.getElementById('kw-' + id);
    const d   = await apiCall('PATCH', { id });
    if (d.ok) {
        showToast(d.ativo ? 'Keyword ativada.' : 'Keyword desativada.');
        setTimeout(() => location.reload(), 600);
    } else {
        showToast('Erro ao atualizar.', 'error');
    }
}

async function removerKeyword(id, nome) {
    if (!confirm('Remover a keyword "' + nome + '"?\n\nEla não será mais usada pelo bot.')) return;
    const d = await apiCall('DELETE', { id });
    if (d.ok) {
        showToast('Keyword removida.');
        document.getElementById('kw-' + id)?.remove();
    } else {
        showToast('Erro ao remover.', 'error');
    }
}
</script>

<?php layoutEnd(); ?>
