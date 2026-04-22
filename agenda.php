<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db = getDB();

$links  = $db->query('SELECT * FROM links WHERE ativo = 1 ORDER BY nome_produto')->fetchAll();
$grupos = $db->query('SELECT * FROM grupos WHERE ativo = 1 ORDER BY nome')->fetchAll();

$agendamentos = $db->query("
    SELECT a.*, l.nome_produto, l.plataforma, g.nome as nome_grupo
    FROM agendamentos a
    JOIN links l ON l.id = a.link_id
    JOIN grupos g ON g.id = a.grupo_id
    ORDER BY a.horario
")->fetchAll();

$diasNomes = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

layoutStart('agenda', 'Agenda de Envios');
toast();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Formulário novo agendamento -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-4">Novo Agendamento</h2>
            <form id="form-agenda" class="space-y-4" onsubmit="salvarAgendamento(event)">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Link</label>
                    <select id="ag-link" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" required>
                        <option value="">Selecione um link...</option>
                        <?php foreach ($links as $l): ?>
                            <option value="<?= $l['id'] ?>">[<?= $l['plataforma'] ?>] <?= htmlspecialchars($l['nome_produto']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grupo WhatsApp</label>
                    <select id="ag-grupo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" required>
                        <option value="">Selecione um grupo...</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dias da semana</label>
                    <div class="flex gap-2 flex-wrap">
                        <?php foreach ($diasNomes as $i => $d): ?>
                            <label class="flex flex-col items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="dias" value="<?= $i ?>" checked class="rounded">
                                <span class="text-xs text-gray-600"><?= $d ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Horário</label>
                    <input type="time" id="ag-horario" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                </div>
                <button type="submit"
                    class="w-full btn-primary">
                    Criar Agendamento
                </button>
            </form>
        </div>
    </div>

    <!-- Lista de agendamentos -->
    <div class="lg:col-span-2">
        <?php if (empty($agendamentos)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center h-full flex items-center justify-center">
                <p class="text-gray-400 text-sm">Nenhum agendamento criado ainda.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($agendamentos as $a): ?>
                    <?php $dias = json_decode($a['dias_semana'], true) ?? []; ?>
                    <div class="bg-white rounded-xl border border-gray-200 p-5" id="ag-row-<?= $a['id'] ?>">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <?= badgePlataforma($a['plataforma']) ?>
                                    <?php if (!$a['ativo']): ?>
                                        <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500">Pausado</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($a['nome_produto']) ?></p>
                                <p class="text-xs text-gray-500 mt-0.5">Grupo: <?= htmlspecialchars($a['nome_grupo']) ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-sm font-mono font-semibold text-emerald-600"><?= $a['horario'] ?></span>
                                    <div class="flex gap-1">
                                        <?php foreach ($diasNomes as $i => $d): ?>
                                            <span class="text-xs px-1.5 py-0.5 rounded <?= in_array($i, $dias) ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'bg-gray-100 text-gray-400' ?>">
                                                <?= $d ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php if ($a['ultimo_envio']): ?>
                                    <p class="text-xs text-gray-400 mt-1">Último envio: <?= $a['ultimo_envio'] ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col gap-2 items-end flex-shrink-0">
                                <button onclick="enviarAgoraAg(<?= $a['link_id'] ?>, <?= $a['grupo_id'] ?>)"
                                    class="text-xs bg-green-50 hover:bg-green-100 text-green-700 font-medium px-3 py-1.5 rounded-lg transition">
                                    Enviar Agora
                                </button>
                                <button onclick="toggleAg(<?= $a['id'] ?>, <?= $a['ativo'] ?>)"
                                    class="text-xs text-emerald-600 hover:underline">
                                    <?= $a['ativo'] ? 'Pausar' : 'Ativar' ?>
                                </button>
                                <button onclick="excluirAg(<?= $a['id'] ?>)"
                                    class="text-xs text-red-500 hover:underline">Excluir</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function salvarAgendamento(e) {
    e.preventDefault();
    const diasChecks = document.querySelectorAll('input[name="dias"]:checked');
    const dias = Array.from(diasChecks).map(c => parseInt(c.value));
    if (!dias.length) { showToast('Selecione ao menos um dia.', 'error'); return; }

    const body = {
        link_id:     parseInt(document.getElementById('ag-link').value),
        grupo_id:    parseInt(document.getElementById('ag-grupo').value),
        dias_semana: dias,
        horario:     document.getElementById('ag-horario').value,
    };

    fetch('/viana/api/agenda.php?action=criar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast('Agendamento criado!');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.error, 'error');
        }
    });
}

function toggleAg(id, ativo) {
    fetch('/viana/api/agenda.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}

function excluirAg(id) {
    if (!confirm('Excluir este agendamento?')) return;
    fetch('/viana/api/agenda.php?action=excluir', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(r => r.json()).then(data => {
        if (data.ok) { document.getElementById('ag-row-'+id)?.remove(); showToast('Agendamento excluído.'); }
        else showToast(data.error, 'error');
    });
}

function enviarAgoraAg(linkId, grupoId) {
    if (!confirm('Enviar este link agora para o grupo?')) return;
    fetch('/viana/api/enviar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({link_id: linkId, grupo_id: grupoId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) showToast('Mensagem enviada com sucesso!');
        else showToast('Erro: ' + data.error, 'error');
    });
}
</script>

<?php layoutEnd(); ?>
