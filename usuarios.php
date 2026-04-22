<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();
$eu = currentUser();
$usuarios = $db->query('SELECT id, nome, email, ativo, criado_em FROM usuarios ORDER BY criado_em')->fetchAll();

layoutStart('usuarios', 'Usuários');
toast();
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Gerencie quem tem acesso ao painel.</p>
    <button onclick="abrirModal()"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Usuário
    </button>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table>
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-3 text-left text-gray-500">Nome</th>
                <th class="px-6 py-3 text-left text-gray-500">E-mail</th>
                <th class="px-6 py-3 text-left text-gray-500">Desde</th>
                <th class="px-6 py-3 text-left text-gray-500">Status</th>
                <th class="px-6 py-3 text-right text-gray-500">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($usuarios as $u): ?>
                <?php $sou = (int)$u['id'] === (int)$eu['id']; ?>
                <tr class="hover:bg-gray-50" id="urow-<?= $u['id'] ?>">
                    <td class="px-6 py-3 text-sm font-medium text-gray-800">
                        <?= htmlspecialchars($u['nome']) ?>
                        <?php if ($sou): ?>
                            <span class="ml-1 text-xs text-indigo-500 font-normal">(você)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-6 py-3 text-xs text-gray-400 font-mono"><?= substr($u['criado_em'], 0, 10) ?></td>
                    <td class="px-6 py-3">
                        <?php if ($u['ativo']): ?>
                            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-800">Ativo</span>
                        <?php else: ?>
                            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right space-x-3">
                        <button onclick="abrirTrocarSenha(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>')"
                            class="text-xs text-indigo-600 hover:underline">Trocar Senha</button>
                        <?php if (!$sou): ?>
                            <button onclick="toggleUsuario(<?= $u['id'] ?>, <?= $u['ativo'] ?>)"
                                class="text-xs text-yellow-600 hover:underline">
                                <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                            <button onclick="excluirUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>')"
                                class="text-xs text-red-500 hover:underline">Excluir</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Novo Usuário -->
<div id="modal-novo" class="modal-backdrop hidden">
    <div class="modal-box max-w-sm">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-gray-800">Novo Usuário</h2>
            <button onclick="fecharModal('modal-novo')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form class="space-y-4" onsubmit="criarUsuario(event)">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" id="u-nome" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" id="u-email" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                <input type="password" id="u-senha" required minlength="6"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <p class="text-xs text-gray-400 mt-1">Mínimo 6 caracteres</p>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">Criar</button>
                <button type="button" onclick="fecharModal('modal-novo')" class="text-sm text-gray-500 hover:underline">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Trocar Senha -->
<div id="modal-senha" class="modal-backdrop hidden">
    <div class="modal-box max-w-sm">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-gray-800">Trocar Senha</h2>
            <button onclick="fecharModal('modal-senha')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p id="senha-nome" class="text-sm text-gray-500 mb-4"></p>
        <input type="hidden" id="senha-uid">
        <form class="space-y-4" onsubmit="salvarSenha(event)">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nova Senha</label>
                <input type="password" id="nova-senha" required minlength="6"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">Salvar</button>
                <button type="button" onclick="fecharModal('modal-senha')" class="text-sm text-gray-500 hover:underline">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('u-nome').value = '';
    document.getElementById('u-email').value = '';
    document.getElementById('u-senha').value = '';
    document.getElementById('modal-novo').classList.remove('hidden');
}

function fecharModal(id) { document.getElementById(id).classList.add('hidden'); }

function criarUsuario(e) {
    e.preventDefault();
    fetch('/viana/api/usuarios.php?action=criar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            nome:  document.getElementById('u-nome').value,
            email: document.getElementById('u-email').value,
            senha: document.getElementById('u-senha').value,
        })
    }).then(r => r.json()).then(data => {
        if (data.ok) { showToast('Usuário criado!'); fecharModal('modal-novo'); setTimeout(() => location.reload(), 900); }
        else showToast(data.error, 'error');
    });
}

function abrirTrocarSenha(id, nome) {
    document.getElementById('senha-uid').value = id;
    document.getElementById('senha-nome').textContent = 'Usuário: ' + nome;
    document.getElementById('nova-senha').value = '';
    document.getElementById('modal-senha').classList.remove('hidden');
}

function salvarSenha(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('senha-uid').value);
    fetch('/viana/api/usuarios.php?action=trocar_senha', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, senha: document.getElementById('nova-senha').value})
    }).then(r => r.json()).then(data => {
        if (data.ok) { showToast('Senha atualizada!'); fecharModal('modal-senha'); }
        else showToast(data.error, 'error');
    });
}

function toggleUsuario(id, ativo) {
    fetch('/viana/api/usuarios.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}

function excluirUsuario(id, nome) {
    if (!confirm(`Excluir o usuário "${nome}"?`)) return;
    fetch('/viana/api/usuarios.php?action=excluir', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) { document.getElementById('urow-'+id)?.remove(); showToast('Usuário excluído.'); }
        else showToast(data.error, 'error');
    });
}
</script>

<?php layoutEnd(); ?>
