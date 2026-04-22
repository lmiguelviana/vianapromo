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
        <a href="/viana/historico" class="text-sm text-gray-500 hover:underline">Limpar</a>
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
    <?php if ($totalPaginas > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-6">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <?php
                $qs = http_build_query(['plataforma' => $filtroPlataforma, 'status' => $filtroStatus, 'pagina' => $i]);
                $ativo = $i === $pagina;
                ?>
                <a href="?<?= $qs ?>"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $ativo ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php layoutEnd(); ?>
