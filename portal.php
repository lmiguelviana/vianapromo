<?php
/**
 * portal.php — Portal público de achadinhos fitness.
 * Sem login. Lista ofertas enviadas com busca e filtro por categoria.
 */
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

$db  = getDB();
$q   = trim($_GET['q'] ?? '');

$por_pag = 24;
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($pagina - 1) * $por_pag;

$where  = "WHERE status = 'enviada'";
$params = [];
if ($q !== '') {
    $where   .= " AND nome LIKE ?";
    $params[] = "%{$q}%";
}

$stCount = $db->prepare("SELECT COUNT(*) FROM ofertas {$where}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pag));

$stmt = $db->prepare(
    "SELECT * FROM ofertas {$where} ORDER BY enviado_em DESC LIMIT {$por_pag} OFFSET {$offset}"
);
$stmt->execute($params);
$ofertas = $stmt->fetchAll();

function detectarCat(string $nome): string {
    $n = mb_strtolower($nome, 'UTF-8');
    if (preg_match('/whey|creatina|prote[ií]na|bcaa|col[aá]geno|vitamina|glutamina|termog[eê]nico|pr[eé].treino|hipercal[oó]rico|albumina|cafe[ií]na|[oô]mega|multivitamin|suplemento/u', $n)) return 'suplementos';
    if (preg_match('/legging|shorts|top |camiseta|bermuda|regata|conjunto|suti[aã]|jogger|moletom|jaqueta|corta.vento|roupa|blusa|cal[çc]a/u', $n)) return 'roupas';
    if (preg_match('/t[eê]nis|meia esportiva|cal[çc]ado/u', $n)) return 'calcados';
    if (preg_match('/haltere|anilha|barra|kettlebell|faixa|el[áa]stico|corda pular|step|roda abdominal|paralela|supino|muscula[çc][aã]o/u', $n)) return 'equipamentos';
    return 'acessorios';
}

function tempoRelativo(?string $dt): string {
    if (!$dt) return '';
    $diff = time() - strtotime($dt);
    if ($diff < 3600)  return 'há ' . max(1, (int)($diff / 60)) . ' min';
    if ($diff < 86400) return 'há ' . (int)($diff / 3600) . 'h';
    return 'há ' . (int)($diff / 86400) . ' dia(s)';
}

function imgUrl(array $o): string {
    if ($o['imagem_path'] && file_exists(__DIR__ . '/' . ltrim($o['imagem_path'], '/'))) {
        return BASE . '/' . ltrim($o['imagem_path'], '/');
    }
    if ($o['imagem_url']) return $o['imagem_url'];
    return '';
}

// 3 tiers de cor por desconto
function badgeClasses(int $pct): string {
    if ($pct >= 50) return 'bg-rose-600 text-white';
    if ($pct >= 25) return 'bg-amber-500 text-white';
    return 'bg-slate-600 text-white';
}

$cats = [
    'todas'        => 'Todas',
    'suplementos'  => 'Suplementos',
    'roupas'       => 'Roupas',
    'calcados'     => 'Calçados',
    'equipamentos' => 'Equipamentos',
    'acessorios'   => 'Acessórios',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achadinhos Fitness — Melhores Ofertas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-img { aspect-ratio: 1/1; object-fit: contain; background: #f8fafc; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cat-btn { transition: all .15s; }
        .cat-btn.active { background: #111827; color: #fff; border-color: #111827; }
        .card { transition: box-shadow .2s; }
        .card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center gap-4">

        <!-- Logo -->
        <a href="<?= BASE ?>/portal" class="flex items-center gap-2.5 flex-shrink-0 group">
            <div class="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="leading-none">
                <span class="block text-sm font-bold text-slate-900 tracking-tight">Achadinhos</span>
                <span class="block text-[10px] font-semibold text-emerald-600 tracking-widest uppercase">Fitness</span>
            </div>
        </a>

        <!-- Busca -->
        <form method="get" class="flex-1 max-w-lg">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q"
                    value="<?= htmlspecialchars($q, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="Buscar produto..."
                    class="w-full pl-9 pr-8 py-2 rounded-lg border border-slate-200 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors placeholder:text-slate-400">
                <?php if ($q): ?>
                <button type="button" onclick="location.href='<?= BASE ?>/portal'"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 w-4 h-4 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <?php endif ?>
            </div>
        </form>

        <span class="hidden sm:block text-xs text-slate-400 flex-shrink-0 tabular-nums">
            <?= number_format($total) ?> oferta<?= $total !== 1 ? 's' : '' ?>
        </span>
    </div>
</header>

<!-- Filtros -->
<div class="bg-white border-b border-slate-100">
    <div class="max-w-7xl mx-auto px-4 py-2.5 flex gap-1.5 overflow-x-auto">
        <?php foreach ($cats as $slug => $label): ?>
        <button data-cat="<?= $slug ?>" onclick="filtrarCat(this)"
            class="cat-btn flex-shrink-0 px-4 py-1.5 rounded-full border border-slate-200 text-sm font-medium text-slate-500 hover:border-slate-400 hover:text-slate-800 <?= $slug === 'todas' ? 'active' : '' ?>">
            <?= $label ?>
        </button>
        <?php endforeach ?>
    </div>
</div>

<!-- Grid -->
<main class="max-w-7xl mx-auto px-4 py-5">

    <?php if (empty($ofertas)): ?>
    <div class="text-center py-24 text-slate-400">
        <svg class="w-12 h-12 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <p class="text-base font-medium text-slate-600">Nenhuma oferta encontrada</p>
        <?php if ($q): ?><p class="text-sm mt-1">Tente outro termo de busca</p><?php endif ?>
    </div>
    <?php else: ?>

    <!-- Legenda de desconto -->
    <div class="flex items-center gap-3 mb-4">
        <span class="text-xs text-slate-400 font-medium">Desconto:</span>
        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-slate-600 text-white">5–24%</span>
        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-amber-500 text-white">25–49%</span>
        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-rose-600 text-white">50%+</span>
    </div>

    <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($ofertas as $o):
            $cat      = detectarCat($o['nome']);
            $img      = imgUrl($o);
            $tempo    = tempoRelativo($o['enviado_em']);
            $desconto = (int)$o['desconto_pct'];
            $precoPor = (float)$o['preco_por'];
            $precoDe  = (float)$o['preco_de'];
            $badgeCls = badgeClasses($desconto);
            $href     = htmlspecialchars($o['url_afiliado'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $nomeEsc  = htmlspecialchars($o['nome'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        ?>
        <div class="card bg-white rounded-xl border border-slate-100 overflow-hidden flex flex-col"
             data-cat="<?= $cat ?>">

            <!-- Imagem -->
            <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer nofollow" class="block relative">
                <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                         alt="<?= $nomeEsc ?>"
                         class="card-img w-full"
                         loading="lazy"
                         onerror="this.parentElement.innerHTML='<div class=\'card-img w-full flex items-center justify-center bg-slate-100\'><svg class=\'w-10 h-10 text-slate-300\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4\'/></svg></div>'">
                <?php else: ?>
                    <div class="card-img w-full flex items-center justify-center bg-slate-100">
                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                <?php endif ?>

                <?php if ($desconto >= 5): ?>
                <span class="absolute top-2 left-2 text-xs font-bold px-2 py-0.5 rounded <?= $badgeCls ?>">
                    -<?= $desconto ?>%
                </span>
                <?php endif ?>
            </a>

            <!-- Info -->
            <div class="p-2.5 flex flex-col flex-1">
                <p class="text-xs text-slate-700 line-clamp-2 font-medium leading-snug mb-2">
                    <?= $nomeEsc ?>
                </p>

                <div class="mt-auto space-y-0.5">
                    <?php if ($precoDe > 0 && $precoDe > $precoPor): ?>
                    <p class="text-[11px] text-slate-400 line-through">R$ <?= number_format($precoDe, 2, ',', '.') ?></p>
                    <?php endif ?>
                    <?php if ($precoPor > 0): ?>
                    <p class="text-sm font-bold text-slate-900">R$ <?= number_format($precoPor, 2, ',', '.') ?></p>
                    <?php endif ?>
                    <?php if ($tempo): ?>
                    <p class="text-[10px] text-slate-400"><?= $tempo ?></p>
                    <?php endif ?>

                    <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer nofollow"
                       class="mt-2 block w-full text-center bg-slate-900 hover:bg-slate-700 text-white text-xs font-semibold py-1.5 rounded-lg transition-colors">
                        Ver oferta
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-8">
        <?php if ($pagina > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $pagina - 1])) ?>"
           class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-slate-400 hover:text-slate-900 transition-colors">← Anterior</a>
        <?php endif ?>
        <span class="text-sm text-slate-500 tabular-nums"><?= $pagina ?> / <?= $total_paginas ?></span>
        <?php if ($pagina < $total_paginas): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $pagina + 1])) ?>"
           class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-slate-400 hover:text-slate-900 transition-colors">Próxima →</a>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php endif ?>
</main>

<footer class="text-center py-6 text-xs text-slate-400 border-t border-slate-100 mt-4">
    Achadinhos Fitness &middot; Ofertas atualizadas automaticamente &middot; Links de afiliado
</footer>

<script>
function filtrarCat(btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#grid > [data-cat]').forEach(card => {
        card.style.display = (cat === 'todas' || card.dataset.cat === cat) ? '' : 'none';
    });
}
</script>
</body>
</html>
