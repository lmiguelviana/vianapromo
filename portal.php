<?php
/**
 * portal.php — Portal público de achadinhos fitness.
 * Sem login. Lista ofertas enviadas com busca e filtro por categoria.
 */
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

$db  = getDB();
$q   = trim($_GET['q'] ?? '');

// Busca com paginação
$por_pag = 24;
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($pagina - 1) * $por_pag;

$where  = "WHERE status = 'enviada'";
$params = [];
if ($q !== '') {
    $where   .= " AND nome LIKE ?";
    $params[] = "%{$q}%";
}

$total   = (int)$db->prepare("SELECT COUNT(*) FROM ofertas {$where}")->execute($params) ? 0 : 0;
$stCount = $db->prepare("SELECT COUNT(*) FROM ofertas {$where}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pag));

$stmt = $db->prepare(
    "SELECT * FROM ofertas {$where} ORDER BY enviado_em DESC LIMIT {$por_pag} OFFSET {$offset}"
);
$stmt->execute($params);
$ofertas = $stmt->fetchAll();

// Detecta categoria pelo nome do produto
function detectarCat(string $nome): string {
    $n = mb_strtolower($nome, 'UTF-8');
    if (preg_match('/whey|creatina|prote[ií]na|bcaa|col[aá]geno|vitamina|glutamina|termog[eê]nico|pr[eé].treino|hipercal[oó]rico|albumina|cafe[ií]na|[oô]mega|multivitamin|suplemento/u', $n)) return 'suplementos';
    if (preg_match('/legging|shorts|top |camiseta|bermuda|regata|conjunto|suti[aã]|jogger|moletom|jaqueta|corta.vento|roupa|blusa|calça/u', $n)) return 'roupas';
    if (preg_match('/t[eê]nis|meia esportiva|calcado|cal[çc]ado/u', $n)) return 'calcados';
    if (preg_match('/haltere|anilha|barra|kettlebell|faixa|el[áa]stico|corda pular|step|roda abdominal|paralela|supino|muscula[çc][aã]o/u', $n)) return 'equipamentos';
    return 'acessorios';
}

// Tempo relativo
function tempoRelativo(?string $dt): string {
    if (!$dt) return '';
    $diff = time() - strtotime($dt);
    if ($diff < 3600)  return 'há ' . max(1, (int)($diff / 60)) . ' min';
    if ($diff < 86400) return 'há ' . (int)($diff / 3600) . 'h';
    return 'há ' . (int)($diff / 86400) . ' dia(s)';
}

// Imagem pública
function imgUrl(array $o): string {
    if ($o['imagem_path'] && file_exists(__DIR__ . '/' . ltrim($o['imagem_path'], '/'))) {
        return BASE . '/' . ltrim($o['imagem_path'], '/');
    }
    if ($o['imagem_url']) return $o['imagem_url'];
    return '';
}

$cats = [
    'todas'        => ['label' => 'Todas',        'emoji' => '🔥'],
    'suplementos'  => ['label' => 'Suplementos',  'emoji' => '💊'],
    'roupas'       => ['label' => 'Roupas',        'emoji' => '👕'],
    'calcados'     => ['label' => 'Calçados',      'emoji' => '👟'],
    'equipamentos' => ['label' => 'Equipamentos',  'emoji' => '🏋️'],
    'acessorios'   => ['label' => 'Acessórios',    'emoji' => '🎒'],
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
        body { font-family: 'Inter', sans-serif; }
        .card-img { aspect-ratio: 1/1; object-fit: contain; background: #f9fafb; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cat-btn.active { background: #059669; color: #fff; border-color: #059669; }
        .card { transition: transform .15s, box-shadow .15s; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.10); }
        .badge-desc { background: linear-gradient(135deg, #059669, #047857); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
        <div class="flex items-center gap-2 flex-shrink-0">
            <div class="w-9 h-9 bg-emerald-600 rounded-xl flex items-center justify-center">
                <span class="text-white text-lg">🔥</span>
            </div>
            <div>
                <div class="font-bold text-gray-900 leading-tight text-sm">Achadinhos</div>
                <div class="text-emerald-600 font-semibold text-xs leading-tight">FITNESS</div>
            </div>
        </div>

        <!-- Busca -->
        <form method="get" class="flex-1 max-w-xl">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="Buscar produto..."
                    class="w-full pl-9 pr-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-gray-50">
                <?php if ($q): ?>
                    <a href="<?= BASE ?>/portal" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">✕</a>
                <?php endif ?>
            </div>
        </form>

        <div class="hidden sm:block text-xs text-gray-400 flex-shrink-0">
            <?= $total ?> oferta<?= $total !== 1 ? 's' : '' ?>
        </div>
    </div>
</header>

<!-- Filtros de categoria -->
<div class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-2 flex gap-2 overflow-x-auto no-scrollbar">
        <?php foreach ($cats as $slug => $cat): ?>
        <button
            data-cat="<?= $slug ?>"
            onclick="filtrarCat(this)"
            class="cat-btn flex-shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border border-gray-200 text-sm font-medium text-gray-600 hover:border-emerald-400 hover:text-emerald-700 transition-colors <?= $slug === 'todas' ? 'active' : '' ?>">
            <span><?= $cat['emoji'] ?></span>
            <span><?= $cat['label'] ?></span>
        </button>
        <?php endforeach ?>
    </div>
</div>

<!-- Grid de ofertas -->
<main class="max-w-7xl mx-auto px-4 py-6">

    <?php if (empty($ofertas)): ?>
    <div class="text-center py-20 text-gray-400">
        <div class="text-5xl mb-3">🔍</div>
        <p class="text-lg font-medium">Nenhuma oferta encontrada</p>
        <?php if ($q): ?><p class="text-sm mt-1">Tente outro termo de busca</p><?php endif ?>
    </div>
    <?php else: ?>

    <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($ofertas as $o):
            $cat     = detectarCat($o['nome']);
            $img     = imgUrl($o);
            $tempo   = tempoRelativo($o['enviado_em']);
            $desconto = (int)$o['desconto_pct'];
            $precoPor = (float)$o['preco_por'];
            $precoDe  = (float)$o['preco_de'];
        ?>
        <div class="card bg-white rounded-2xl border border-gray-100 overflow-hidden flex flex-col"
             data-cat="<?= $cat ?>">

            <!-- Imagem -->
            <a href="<?= htmlspecialchars($o['url_afiliado'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
               target="_blank" rel="noopener noreferrer nofollow" class="block relative">
                <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($o['nome'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                         class="card-img w-full"
                         loading="lazy"
                         onerror="this.closest('.block').querySelector('.ph').style.display='flex';this.style.display='none'">
                    <div class="ph w-full hidden items-center justify-center bg-gray-100 text-gray-300 text-4xl" style="aspect-ratio:1/1">🛍️</div>
                <?php else: ?>
                    <div class="w-full flex items-center justify-center bg-gray-100 text-gray-300 text-4xl" style="aspect-ratio:1/1">🛍️</div>
                <?php endif ?>

                <?php if ($desconto >= 5): ?>
                <span class="badge-desc absolute top-2 left-2 text-white text-xs font-bold px-2 py-0.5 rounded-lg shadow">
                    -<?= $desconto ?>%
                </span>
                <?php endif ?>
            </a>

            <!-- Info -->
            <div class="p-2.5 flex flex-col flex-1 gap-1.5">
                <p class="text-xs text-gray-700 line-clamp-2 font-medium leading-snug">
                    <?= htmlspecialchars($o['nome'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>

                <div class="mt-auto">
                    <?php if ($precoDe > 0 && $precoDe > $precoPor): ?>
                    <p class="text-xs text-gray-400 line-through">R$ <?= number_format($precoDe, 2, ',', '.') ?></p>
                    <?php endif ?>
                    <?php if ($precoPor > 0): ?>
                    <p class="text-sm font-bold text-emerald-700">R$ <?= number_format($precoPor, 2, ',', '.') ?></p>
                    <?php endif ?>

                    <?php if ($tempo): ?>
                    <p class="text-[10px] text-gray-400 mt-0.5"><?= $tempo ?></p>
                    <?php endif ?>

                    <a href="<?= htmlspecialchars($o['url_afiliado'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                       target="_blank" rel="noopener noreferrer nofollow"
                       class="mt-2 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-1.5 rounded-xl transition-colors">
                        Ver oferta →
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
           class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:border-emerald-400 hover:text-emerald-700">← Anterior</a>
        <?php endif ?>

        <span class="text-sm text-gray-500">Página <?= $pagina ?> de <?= $total_paginas ?></span>

        <?php if ($pagina < $total_paginas): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $pagina + 1])) ?>"
           class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:border-emerald-400 hover:text-emerald-700">Próxima →</a>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php endif ?>
</main>

<!-- Footer -->
<footer class="text-center py-6 text-xs text-gray-400 border-t border-gray-100 mt-4">
    Achadinhos Fitness · Ofertas atualizadas automaticamente · Links de afiliado
</footer>

<script>
function filtrarCat(btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#grid > div[data-cat]').forEach(card => {
        card.style.display = (cat === 'todas' || card.dataset.cat === cat) ? '' : 'none';
    });
}
</script>
</body>
</html>
