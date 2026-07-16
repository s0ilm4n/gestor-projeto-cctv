<?php
/**
 * Projetos CCTV — Lista, Criar, Ver, Editar
 */
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$filtro_cliente = (int)($_GET['cliente_id'] ?? 0);
$filtro_estado = $_GET['estado'] ?? '';

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $nome_projeto = validar_texto($_POST['nome_projeto'] ?? '', 200);
        $local_instalacao = validar_texto($_POST['local_instalacao'] ?? '', 255);
        $referencia_interna = validar_texto($_POST['referencia_interna'] ?? '', 50);
        $estado = $_POST['estado'] ?? 'orçamento';
        $nivel_risco_global = $_POST['nivel_risco_global'] ?? 'medio';
        $objetivo_dori = $_POST['objetivo_dori_principal'] ?? 'R';
        $alvara_psp = validar_texto($_POST['alvara_psp'] ?? '', 50);
        $tecnico = validar_texto($_POST['tecnico_responsavel'] ?? '', 100);
        $tecnico_registo = validar_texto($_POST['tecnico_registo'] ?? '', 50);
        $data_inicio = validar_data($_POST['data_inicio'] ?? '');
        $data_conclusao = $_POST['data_conclusao'] ? validar_data($_POST['data_conclusao']) : null;
        $tipo_gravador = $_POST['tipo_gravador'] ?? 'NVR';
        $gravador_marca = validar_texto($_POST['gravador_marca'] ?? '', 100);
        $gravador_modelo = validar_texto($_POST['gravador_modelo'] ?? '', 100);
        $num_canais = (int)($_POST['num_canais'] ?? 0);
        $capacidade_gb = (int)($_POST['capacidade_armazenamento_gb'] ?? 0);
        $retencao = (int)($_POST['retencao_dias'] ?? 30);
        $sinaletica = isset($_POST['sinaletica_colocada']) ? 1 : 0;
        $cnpd = isset($_POST['comunicacao_cnpd']) ? 1 : 0;
        $obs = $_POST['observacoes'] ?? '';

        try {
            if ($post_action === 'add') {
                $stmt = $db->prepare("INSERT INTO projetos_cctv (cliente_id, nome_projeto, local_instalacao, referencia_interna, estado, nivel_risco_global, objetivo_dori_principal, alvara_psp, tecnico_responsavel, tecnico_registo, data_inicio, data_conclusao, tipo_gravador, gravador_marca, gravador_modelo, num_canais, capacidade_armazenamento_gb, retencao_dias, sinaletica_colocada, comunicacao_cnpd, observacoes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$cliente_id, $nome_projeto, $local_instalacao, $referencia_interna, $estado, $nivel_risco_global, $objetivo_dori, $alvara_psp, $tecnico, $tecnico_registo, $data_inicio, $data_conclusao, $tipo_gravador, $gravador_marca, $gravador_modelo, $num_canais, $capacidade_gb, $retencao, $sinaletica, $cnpd, $obs]);
                $novo_id = $db->lastInsertId();
                logAuditoria('projeto_add', "ID: $novo_id, Nome: $nome_projeto");
                $_SESSION['flash'] = 'Projeto criado com sucesso.';
                header("Location: index.php?p=projetos&action=view&id=$novo_id"); exit;
            } else {
                $stmt = $db->prepare("UPDATE projetos_cctv SET cliente_id=?, nome_projeto=?, local_instalacao=?, referencia_interna=?, estado=?, nivel_risco_global=?, objetivo_dori_principal=?, alvara_psp=?, tecnico_responsavel=?, tecnico_registo=?, data_inicio=?, data_conclusao=?, tipo_gravador=?, gravador_marca=?, gravador_modelo=?, num_canais=?, capacidade_armazenamento_gb=?, retencao_dias=?, sinaletica_colocada=?, comunicacao_cnpd=?, observacoes=? WHERE id=?");
                $stmt->execute([$cliente_id, $nome_projeto, $local_instalacao, $referencia_interna, $estado, $nivel_risco_global, $objetivo_dori, $alvara_psp, $tecnico, $tecnico_registo, $data_inicio, $data_conclusao, $tipo_gravador, $gravador_marca, $gravador_modelo, $num_canais, $capacidade_gb, $retencao, $sinaletica, $cnpd, $obs, $id]);
                logAuditoria('projeto_edit', "ID: $id");
                $_SESSION['flash'] = 'Projeto atualizado.';
                header("Location: index.php?p=projetos&action=view&id=$id"); exit;
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
            header("Location: index.php?p=projetos"); exit;
        }
    }

    if ($post_action === 'delete' && $id) {
        try {
            $db->prepare("DELETE FROM projetos_cctv WHERE id=?")->execute([$id]);
            logAuditoria('projeto_delete', "ID: $id");
            $_SESSION['flash'] = 'Projeto eliminado.';
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro ao eliminar.';
        }
        header('Location: index.php?p=projetos'); exit;
    }
}

// ===== LIST =====
if ($action === 'list') {
    $where = '1=1';
    $params = [];
    if ($filtro_cliente) { $where .= ' AND p.cliente_id = ?'; $params[] = $filtro_cliente; }
    if ($filtro_estado) { $where .= ' AND p.estado = ?'; $params[] = $filtro_estado; }

    $projetos = $db->prepare("
        SELECT p.*, c.nome AS cliente_nome,
            (SELECT COUNT(*) FROM equipamentos WHERE projeto_id = p.id) AS total_equip,
            (SELECT COUNT(*) FROM dori_calculos WHERE projeto_id = p.id AND conforme = 0) AS nao_conformes
        FROM projetos_cctv p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE $where
        ORDER BY p.updated_at DESC
    ");
    $projetos->execute($params);
    $projetos = $projetos->fetchAll();

    $estados = ['orçamento','planeamento','instalação','concluído','manutenção','encerrado'];
?>
    <div class="page-header">
        <h1>📋 Projetos CCTV</h1>
        <div class="page-actions">
            <a href="index.php?p=projetos&action=add" class="btn btn-primary">+ Novo Projeto</a>
        </div>
    </div>

    <div class="filters">
        <form method="GET" style="display:contents">
            <input type="hidden" name="p" value="projetos">
            <div class="form-group">
                <label>Estado</label>
                <select name="estado" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $e): ?>
                    <option value="<?= $e ?>" <?= $filtro_estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtro_cliente): ?>
                <a href="index.php?p=projetos" class="btn btn-sm btn-outline" style="align-self:end">Limpar filtros</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (count($projetos)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Equip.</th>
                        <th>Nível Risco</th>
                        <th>DORI</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projetos as $p): ?>
                    <tr>
                        <td>
                            <a href="index.php?p=projetos&action=view&id=<?= $p['id'] ?>">
                                <strong><?= e($p['nome_projeto']) ?></strong>
                            </a>
                            <br><small style="color:var(--text-muted)"><?= e($p['referencia_interna'] ?: $p['local_instalacao']) ?></small>
                        </td>
                        <td><?= e($p['cliente_nome']) ?></td>
                        <td><span class="badge badge-<?= $p['estado'] === 'concluído' ? 'success' : ($p['estado'] === 'instalação' ? 'info' : ($p['estado'] === 'encerrado' ? 'danger' : 'warning')) ?>"><?= e($p['estado']) ?></span></td>
                        <td><span class="badge badge-info"><?= $p['total_equip'] ?></span></td>
                        <td>
                            <span class="badge badge-<?= $p['nivel_risco_global'] === 'critico' ? 'danger' : ($p['nivel_risco_global'] === 'alto' ? 'warning' : ($p['nivel_risco_global'] === 'medio' ? 'info' : 'success')) ?>">
                                <?= ucfirst(e($p['nivel_risco_global'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="dori-badge <?= $p['objetivo_dori_principal'] ?>"><?= $p['objetivo_dori_principal'] ?></span>
                            <?php if ($p['nao_conformes']): ?>
                                <span class="badge badge-danger"><?= $p['nao_conformes'] ?> ❌</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a href="index.php?p=projetos&action=view&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">👁️</a>
                            <a href="index.php?p=projetos&action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                            <a href="index.php?p=plantas&projeto_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">🗺️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>Nenhum projeto</h3>
            <p>Crie o primeiro projeto CCTV</p>
        </div>
        <?php endif; ?>
    </div>
<?php
}

// ===== VIEW =====
if ($action === 'view' && $id) {
    $p = $db->prepare("SELECT p.*, c.nome AS cliente_nome, c.nif AS cliente_nif, c.morada AS cliente_morada, c.telefone AS cliente_telefone, c.email AS cliente_email, c.contato_nome, c.contato_telefone, c.contato_email FROM projetos_cctv p JOIN clientes c ON p.cliente_id = c.id WHERE p.id=?");
    $p->execute([$id]);
    $p = $p->fetch();
    if (!$p) { header('Location: index.php?p=projetos'); exit; }

    $equipamentos = $db->prepare("SELECT * FROM equipamentos WHERE projeto_id=? ORDER BY tipo");
    $equipamentos->execute([$id]);
    $equipamentos = $equipamentos->fetchAll();

    $dori_calcs = $db->prepare("SELECT * FROM dori_calculos WHERE projeto_id=? ORDER BY nome_zona");
    $dori_calcs->execute([$id]);
    $dori_calcs = $dori_calcs->fetchAll();

    $plantas = $db->prepare("SELECT * FROM plantas WHERE projeto_id=?");
    $plantas->execute([$id]);
    $plantas = $plantas->fetchAll();

    $checklist = $db->prepare("SELECT * FROM checklist_itens WHERE projeto_id=? ORDER BY secao, item_codigo");
    $checklist->execute([$id]);
    $checklist = $checklist->fetchAll();
?>
    <div class="page-header">
        <h1>📋 <?= e($p['nome_projeto']) ?></h1>
        <div class="page-actions">
            <a href="index.php?p=projetos&action=edit&id=<?= $id ?>" class="btn btn-primary">✏️ Editar</a>
            <a href="index.php?p=equipamentos&projeto_id=<?= $id ?>" class="btn btn-outline">📹 Equipamentos</a>
            <a href="index.php?p=dori&projeto_id=<?= $id ?>" class="btn btn-outline">🔬 DORI</a>
            <a href="index.php?p=plantas&projeto_id=<?= $id ?>" class="btn btn-outline">🗺️ Plantas</a>
            <a href="index.php?p=projetos" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    <div class="card">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <h3 style="color:var(--text-heading);margin-bottom:12px">Dados do Projeto</h3>
                <div class="info-row"><span class="label">Estado</span><span class="value"><span class="badge badge-info"><?= e($p['estado']) ?></span></span></div>
                <div class="info-row"><span class="label">Cliente</span><span class="value"><?= e($p['cliente_nome']) ?></span></div>
                <div class="info-row"><span class="label">Local</span><span class="value"><?= e($p['local_instalacao'] ?: '-') ?></span></div>
                <div class="info-row"><span class="label">Ref. Interna</span><span class="value"><?= e($p['referencia_interna'] ?: '-') ?></span></div>
                <div class="info-row"><span class="label">Nível Risco</span><span class="value"><span class="badge badge-<?= $p['nivel_risco_global'] === 'critico' ? 'danger' : ($p['nivel_risco_global'] === 'alto' ? 'warning' : 'info') ?>"><?= ucfirst($p['nivel_risco_global']) ?></span></span></div>
                <div class="info-row"><span class="label">Objetivo DORI</span><span class="value"><span class="dori-badge <?= $p['objetivo_dori_principal'] ?>"><?= $p['objetivo_dori_principal'] ?> - <?= doriLabel($p['objetivo_dori_principal']) ?></span></span></div>
            </div>
            <div>
                <h3 style="color:var(--text-heading);margin-bottom:12px">Legislação / Técnico</h3>
                <div class="info-row"><span class="label">Alvará PSP</span><span class="value"><?= e($p['alvara_psp'] ?: '-') ?></span></div>
                <div class="info-row"><span class="label">Técnico</span><span class="value"><?= e($p['tecnico_responsavel'] ?: '-') ?></span></div>
                <div class="info-row"><span class="label">Registo Técnico</span><span class="value"><?= e($p['tecnico_registo'] ?: '-') ?></span></div>
                <div class="info-row"><span class="label">Data Início</span><span class="value"><?= fmtData($p['data_inicio']) ?></span></div>
                <div class="info-row"><span class="label">Data Conclusão</span><span class="value"><?= fmtData($p['data_conclusao']) ?></span></div>
                <div class="info-row"><span class="label">Sinalética</span><span class="value"><?= $p['sinaletica_colocada'] ? '✅ Sim' : '❌ Não' ?></span></div>
                <div class="info-row"><span class="label">CNPD</span><span class="value"><?= $p['comunicacao_cnpd'] ? '✅ Comunicado' : '❌ Pendente' ?></span></div>
            </div>
        </div>
    </div>

    <!-- Gravador / Armazenamento -->
    <div class="card">
        <div class="card-header">🖥️ Gravador / Armazenamento</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
            <div class="info-row"><span class="label">Tipo</span><span class="value"><?= e($p['tipo_gravador']) ?></span></div>
            <div class="info-row"><span class="label">Marca/Modelo</span><span class="value"><?= e($p['gravador_marca']) ?> <?= e($p['gravador_modelo']) ?></span></div>
            <div class="info-row"><span class="label">Canais</span><span class="value"><?= $p['num_canais'] ?> ch</span></div>
            <div class="info-row"><span class="label">Armazenamento</span><span class="value"><?= $p['capacidade_armazenamento_gb'] ?> GB</span></div>
            <div class="info-row"><span class="label">Retenção</span><span class="value"><?= $p['retencao_dias'] ?> dias</span></div>
            <?php if ($p['retencao_dias'] < 30): ?>
            <div class="info-row"><span class="label">⚠️ RGPD</span><span class="value" style="color:#EF5350">Mínimo 30 dias exigido</span></div>
            <?php else: ?>
            <div class="info-row"><span class="label">✅ RGPD</span><span class="value" style="color:#81C784">Conforme (≥30 dias)</span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Equipamentos -->
    <div class="card">
        <div class="card-header">
            📹 Equipamentos
            <span class="badge badge-info" style="float:right;font-size:0.8rem"><?= count($equipamentos) ?> itens</span>
        </div>
        <?php if (count($equipamentos)): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Tipo</th><th>Marca/Modelo</th><th>Localização</th><th>Resolução</th><th>DORI</th></tr></thead>
                <tbody>
                    <?php foreach ($equipamentos as $eq): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= e($eq['tipo']) ?></span></td>
                        <td><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></td>
                        <td><?= e($eq['localizacao'] ?: '-') ?></td>
                        <td><?= $eq['resolucao_h'] ?>×<?= $eq['resolucao_v'] ?></td>
                        <td><?= $eq['nivel_dori'] ? '<span class="dori-badge '.$eq['nivel_dori'].'">'.$eq['nivel_dori'].'</span>' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px">
            <p>Nenhum equipamento registado. <a href="index.php?p=equipamentos&projeto_id=<?= $id ?>">Adicionar equipamentos</a></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- DORI -->
    <div class="card">
        <div class="card-header">
            🔬 Cálculos DORI (EN 62676-4)
            <span class="badge badge-info" style="float:right;font-size:0.8rem"><?= count($dori_calcs) ?> zonas</span>
        </div>
        <?php if (count($dori_calcs)): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Zona</th><th>Objetivo</th><th>PPM</th><th>Necessário</th><th>FOV</th><th>Lente</th><th>Risco</th><th>Conforme</th></tr></thead>
                <tbody>
                    <?php foreach ($dori_calcs as $d): ?>
                    <tr>
                        <td><strong><?= e($d['nome_zona']) ?></strong></td>
                        <td><span class="badge badge-primary"><?= e(ucfirst($d['objetivo'])) ?></span></td>
                        <td><strong><?= fmtNumero($d['ppm_calculado']) ?></strong></td>
                        <td><?= $d['ppm_necessario'] ?> ppm</td>
                        <td><?= fmtNumero($d['fov_h_graus']) ?>°</td>
                        <td><?= fmtNumero($d['distancia_focal_recomendada_mm']) ?> mm</td>
                        <td><span class="badge badge-<?= $d['nivel_risco'] === 'critico' ? 'danger' : ($d['nivel_risco'] === 'alto' ? 'warning' : 'info') ?>"><?= e(ucfirst($d['nivel_risco'])) ?></span></td>
                        <td><?= $d['conforme'] ? '<span class="badge badge-success">✅ OK</span>' : '<span class="badge badge-danger">❌</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px">
            <p>Nenhum cálculo DORI. <a href="index.php?p=dori&projeto_id=<?= $id ?>">Calcular DORI</a></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Plantas -->
    <div class="card">
        <div class="card-header">
            🗺️ Plantas
            <span class="badge badge-info" style="float:right;font-size:0.8rem"><?= count($plantas) ?> plantas</span>
        </div>
        <?php if (count($plantas)): ?>
        <table>
            <thead><tr><th>Nome</th><th>Tipo</th><th>Piso</th><th>Câmaras</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($plantas as $pl): ?>
                <tr>
                    <td><?= e($pl['nome']) ?></td>
                    <td><span class="badge badge-info"><?= e($pl['tipo']) ?></span></td>
                    <td><?= $pl['piso'] > 0 ? $pl['piso'] . 'º Piso' : 'R/C' ?></td>
                    <td><?php
                        $cc = $db->prepare("SELECT COUNT(*) FROM plantas_cameras WHERE planta_id=?");
                        $cc->execute([$pl['id']]);
                        echo $cc->fetchColumn();
                    ?></td>
                    <td><a href="pages/editor-planta.php?id=<?= $pl['id'] ?>" class="btn btn-sm btn-outline">✏️ Abrir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state" style="padding:20px">
            <p>Nenhuma planta criada. <a href="index.php?p=plantas&projeto_id=<?= $id ?>&action=add">Criar planta</a></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Checklist Conformidade -->
    <div class="card">
        <div class="card-header">📋 Checklist de Conformidade</div>
        <?php if (count($checklist)): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Secção</th><th>Código</th><th>Descrição</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($checklist as $ch): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= e($ch['secao']) ?></span></td>
                        <td><?= e($ch['item_codigo']) ?></td>
                        <td><?= e($ch['item_descricao']) ?></td>
                        <td><?= $ch['verificado'] ? '✅' : '⬜' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px">
            <p>Checklist não gerada. Pode gerar a checklist de conformidade nas ações do projeto.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Observações -->
    <?php if ($p['observacoes']): ?>
    <div class="card">
        <div class="card-header">📝 Observações</div>
        <p><?= nl2br(e($p['observacoes'])) ?></p>
    </div>
    <?php endif; ?>
<?php
}

// ===== ADD / EDIT =====
if ($action === 'add' || ($action === 'edit' && $id)) {
    $p = [
        'cliente_id'=>0,'nome_projeto'=>'','local_instalacao'=>'','referencia_interna'=>'',
        'estado'=>'orçamento','nivel_risco_global'=>'medio','objetivo_dori_principal'=>'R',
        'alvara_psp'=>'','tecnico_responsavel'=>'','tecnico_registo'=>'',
        'data_inicio'=>date('Y-m-d'),'data_conclusao'=>'','tipo_gravador'=>'NVR',
        'gravador_marca'=>'','gravador_modelo'=>'','num_canais'=>0,
        'capacidade_armazenamento_gb'=>0,'retencao_dias'=>30,
        'sinaletica_colocada'=>0,'comunicacao_cnpd'=>0,'observacoes'=>''
    ];
    $titulo = 'Novo Projeto CCTV';
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM projetos_cctv WHERE id=?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) { header('Location: index.php?p=projetos'); exit; }
        $titulo = 'Editar Projeto';
    }

    $clientes = $db->query("SELECT id, nome, nif FROM clientes ORDER BY nome")->fetchAll();
?>
    <div class="page-header">
        <h1><?= $titulo ?></h1>
        <div class="page-actions">
            <a href="index.php?p=projetos" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action ?>">

            <h3 style="color:var(--text-heading);margin-bottom:16px">📋 Dados Gerais</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="nome_projeto">Nome do Projeto *</label>
                    <input type="text" id="nome_projeto" name="nome_projeto" class="form-control" value="<?= e($p['nome_projeto']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="cliente_id">Cliente *</label>
                    <select id="cliente_id" name="cliente_id" class="form-control" required>
                        <option value="">Selecionar cliente</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $p['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?> (<?= e($c['nif'] ?: '--') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="local_instalacao">Local de Instalação</label>
                    <input type="text" id="local_instalacao" name="local_instalacao" class="form-control" value="<?= e($p['local_instalacao']) ?>" placeholder="Endereço da instalação">
                </div>
                <div class="form-group">
                    <label for="referencia_interna">Referência Interna</label>
                    <input type="text" id="referencia_interna" name="referencia_interna" class="form-control" value="<?= e($p['referencia_interna']) ?>" placeholder="CCTV-2026-001">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="orçamento" <?= $p['estado']==='orçamento'?'selected':'' ?>>Orçamento</option>
                        <option value="planeamento" <?= $p['estado']==='planeamento'?'selected':'' ?>>Planeamento</option>
                        <option value="instalação" <?= $p['estado']==='instalação'?'selected':'' ?>>Instalação</option>
                        <option value="concluído" <?= $p['estado']==='concluído'?'selected':'' ?>>Concluído</option>
                        <option value="manutenção" <?= $p['estado']==='manutenção'?'selected':'' ?>>Manutenção</option>
                        <option value="encerrado" <?= $p['estado']==='encerrado'?'selected':'' ?>>Encerrado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nivel_risco_global">Nível de Risco Global</label>
                    <select id="nivel_risco_global" name="nivel_risco_global" class="form-control">
                        <option value="baixo" <?= $p['nivel_risco_global']==='baixo'?'selected':'' ?>>Baixo</option>
                        <option value="medio" <?= $p['nivel_risco_global']==='medio'?'selected':'' ?>>Médio</option>
                        <option value="alto" <?= $p['nivel_risco_global']==='alto'?'selected':'' ?>>Alto</option>
                        <option value="critico" <?= $p['nivel_risco_global']==='critico'?'selected':'' ?>>Crítico</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Objetivo DORI Principal</label>
                    <select name="objetivo_dori_principal" class="form-control">
                        <option value="I" <?= $p['objetivo_dori_principal']==='I'?'selected':'' ?>>I — Identificação (250 ppm)</option>
                        <option value="R" <?= $p['objetivo_dori_principal']==='R'?'selected':'' ?>>R — Reconhecimento (125 ppm)</option>
                        <option value="O" <?= $p['objetivo_dori_principal']==='O'?'selected':'' ?>>O — Observação (62 ppm)</option>
                        <option value="D" <?= $p['objetivo_dori_principal']==='D'?'selected':'' ?>>D — Detecção (25 ppm)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data_inicio">Data Início</label>
                    <input type="date" id="data_inicio" name="data_inicio" class="form-control" value="<?= $p['data_inicio'] ?>">
                </div>
            </div>

            <h3 style="color:var(--text-heading);margin:24px 0 16px">🔐 Legislação / Técnico (Lei 34/2013)</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Alvará PSP</label>
                    <input type="text" name="alvara_psp" class="form-control" value="<?= e($p['alvara_psp']) ?>" placeholder="Ex: 1234/PSP">
                </div>
                <div class="form-group">
                    <label>Técnico Responsável</label>
                    <input type="text" name="tecnico_responsavel" class="form-control" value="<?= e($p['tecnico_responsavel']) ?>">
                </div>
                <div class="form-group">
                    <label>Nº Registo Técnico</label>
                    <input type="text" name="tecnico_registo" class="form-control" value="<?= e($p['tecnico_registo']) ?>" placeholder="Ex: 5678/PSP">
                </div>
                <div class="form-group">
                    <label for="data_conclusao">Data Conclusão</label>
                    <input type="date" id="data_conclusao" name="data_conclusao" class="form-control" value="<?= e($p['data_conclusao']) ?>">
                </div>
            </div>

            <h3 style="color:var(--text-heading);margin:24px 0 16px">🖥️ Gravador / Armazenamento</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo Gravador</label>
                    <select name="tipo_gravador" class="form-control">
                        <option value="DVR" <?= $p['tipo_gravador']==='DVR'?'selected':'' ?>>DVR</option>
                        <option value="NVR" <?= $p['tipo_gravador']==='NVR'?'selected':'' ?>>NVR</option>
                        <option value="HVR" <?= $p['tipo_gravador']==='HVR'?'selected':'' ?>>HVR</option>
                        <option value="cloud" <?= $p['tipo_gravador']==='cloud'?'selected':'' ?>>Cloud</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="gravador_marca" class="form-control" value="<?= e($p['gravador_marca']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="gravador_modelo" class="form-control" value="<?= e($p['gravador_modelo']) ?>">
                </div>
                <div class="form-group">
                    <label>Nº Canais</label>
                    <input type="number" name="num_canais" class="form-control" value="<?= $p['num_canais'] ?>" min="0" max="256">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Capacidade Armazenamento (GB)</label>
                    <input type="number" name="capacidade_armazenamento_gb" class="form-control" value="<?= $p['capacidade_armazenamento_gb'] ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Retenção (dias) <span style="color:var(--text-muted)">— mínimo legal: 30</span></label>
                    <input type="number" name="retencao_dias" class="form-control" value="<?= $p['retencao_dias'] ?>" min="1" max="365">
                </div>
            </div>

            <h3 style="color:var(--text-heading);margin:24px 0 16px">✅ Conformidade</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="sinaletica_colocada" value="1" <?= $p['sinaletica_colocada'] ? 'checked' : '' ?>>
                        Sinalética Informativa Colocada (RGPD)
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="comunicacao_cnpd" value="1" <?= $p['comunicacao_cnpd'] ? 'checked' : '' ?>>
                        Comunicação CNPD Efetuada
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" class="form-control"><?= e($p['observacoes']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">💾 Guardar</button>
            <a href="index.php?p=projetos" class="btn btn-outline">Cancelar</a>
        </form>
    </div>
<?php
}
