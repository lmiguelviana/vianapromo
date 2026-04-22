<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();
$grupos = $db->query('SELECT * FROM grupos ORDER BY nome')->fetchAll();

layoutStart('grupos', 'Grupos WhatsApp');
toast();
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Gerencie os grupos do WhatsApp que receberão os links.</p>
    <button onclick="abrirSincronizar()"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Sincronizar Grupos do WhatsApp
    </button>
</div>

<?php if (empty($grupos)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <p class="text-gray-400 text-sm">Nenhum grupo salvo ainda. Clique em "Sincronizar" para buscar seus grupos do WhatsApp.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table>
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-gray-500">Nome do Grupo</th>
                    <th class="px-6 py-3 text-left text-gray-500">JID (WhatsApp ID)</th>
                    <th class="px-6 py-3 text-left text-gray-500">Status</th>
                    <th class="px-6 py-3 text-right text-gray-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($grupos as $g): ?>
                    <tr class="hover:bg-gray-50" id="row-<?= $g['id'] ?>">
                        <td class="px-6 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($g['nome']) ?></td>
                        <td class="px-6 py-3 text-xs text-gray-400 font-mono"><?= htmlspecialchars($g['group_jid']) ?></td>
                        <td class="px-6 py-3">
                            <?php if ($g['ativo']): ?>
                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-right space-x-2">
                            <button onclick="toggleGrupo(<?= $g['id'] ?>, <?= $g['ativo'] ?>)"
                                class="text-xs text-indigo-600 hover:underline">
                                <?= $g['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                            <button onclick="excluirGrupo(<?= $g['id'] ?>, '<?= htmlspecialchars($g['nome'], ENT_QUOTES) ?>')"
                                class="text-xs text-red-500 hover:underline">
                                Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Sincronizar -->
<div id="modal-sync" class="modal-backdrop hidden">
    <div class="modal-box max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-800">Grupos do WhatsApp</h2>
            <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div id="sync-loading" class="py-8 text-center text-sm text-gray-400">
            <div class="spinner border-indigo-500 border-t-indigo-500 mx-auto mb-3" style="border-color:rgba(99,102,241,0.3);border-top-color:#6366f1;"></div>
            Buscando grupos na Evolution API...
        </div>

        <div id="sync-error" class="hidden py-4 text-sm text-red-600 bg-red-50 rounded-lg px-4"></div>

        <div id="sync-lista" class="hidden">
            <p class="text-sm text-gray-500 mb-3">Selecione os grupos que quer adicionar ao sistema:</p>
            <div id="sync-checkboxes" class="space-y-2 max-h-72 overflow-y-auto border border-gray-200 rounded-lg p-3"></div>
            <div class="flex items-center gap-3 mt-5">
                <button onclick="salvarGrupos()"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    Salvar Selecionados
                </button>
                <button onclick="fecharModal()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>
let gruposWpp = [];

function abrirSincronizar() {
    document.getElementById('modal-sync').classList.remove('hidden');
    document.getElementById('sync-loading').classList.remove('hidden');
    document.getElementById('sync-error').classList.add('hidden');
    document.getElementById('sync-lista').classList.add('hidden');

    fetch(BASE + '/api/grupos_wpp.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('sync-loading').classList.add('hidden');
            if (!data.ok) {
                document.getElementById('sync-error').textContent = data.error;
                document.getElementById('sync-error').classList.remove('hidden');
                return;
            }
            gruposWpp = data.grupos;
            const box = document.getElementById('sync-checkboxes');
            box.innerHTML = '';
            gruposWpp.forEach((g, i) => {
                box.innerHTML += `
                    <label class="flex items-center gap-3 text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                        <input type="checkbox" class="grupo-check" value="${i}" checked class="rounded">
                        <span class="font-medium">${g.nome}</span>
                        <span class="text-xs text-gray-400 font-mono ml-auto truncate max-w-[180px]">${g.jid}</span>
                    </label>`;
            });
            document.getElementById('sync-lista').classList.remove('hidden');
        })
        .catch(() => {
            document.getElementById('sync-loading').classList.add('hidden');
            document.getElementById('sync-error').textContent = 'Erro de conexão com a API.';
            document.getElementById('sync-error').classList.remove('hidden');
        });
}

function fecharModal() {
    document.getElementById('modal-sync').classList.add('hidden');
}

function salvarGrupos() {
    const checks = document.querySelectorAll('.grupo-check:checked');
    const selecionados = Array.from(checks).map(c => gruposWpp[parseInt(c.value)]);
    if (!selecionados.length) { showToast('Selecione ao menos um grupo.', 'error'); return; }

    fetch(BASE + '/api/grupos.php?action=salvar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({grupos: selecionados})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(`${data.salvos} grupo(s) salvos!`);
            fecharModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.error, 'error');
        }
    });
}

function toggleGrupo(id, ativo) {
    fetch(BASE + '/api/grupos.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}

function excluirGrupo(id, nome) {
    if (!confirm(`Excluir o grupo "${nome}"? Agendamentos vinculados serão removidos.`)) return;
    fetch(BASE + '/api/grupos.php?action=excluir', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) { document.getElementById('row-'+id)?.remove(); showToast('Grupo excluído.'); }
        else showToast(data.error, 'error');
    });
}
</script>

<?php layoutEnd(); ?>

