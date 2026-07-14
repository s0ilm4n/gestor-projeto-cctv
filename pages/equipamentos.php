<?php
/**
 * Equipamentos — Gestão de câmaras, controlos de acesso, etc.
 */
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$projeto_id = (int)($_GET['projeto_id'] ?? 0);

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        $projeto_id = (int)($_POST['projeto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'camera';
        $subtipo = validar_texto($_POST['subtipo'] ?? '', 50);
        $marca = validar_texto($_POST['marca'] ?? '', 100);
        $modelo = validar_texto($_POST['modelo'] ?? '', 100);
        $numero_serie = validar_texto($_POST['numero_serie'] ?? '', 100);
        $mac = validar_texto($_POST['mac_address'] ?? '', 17);
        $ip = validar_texto($_POST['ip_address'] ?? '', 15);
        $resolucao_h = (int)($_POST['resolucao_h'] ?? 1920);
        $resolucao_v = (int)($_POST['resolucao_v'] ?? 1080);
        $mp = (float)($_POST['megapixels'] ?? 2.0);
        $tipo_lente = $_POST['tipo_lente'] ?? 'fixa';
        $focal_min = $_POST['distancia_focal_mm_min'] ? (float)$_POST['distancia_focal_mm_min'] : null;
        $focal_max = $_POST['distancia_focal_mm_max'] ? (float)$_POST['distancia_focal_mm_max'] : null;
        $sensor = validar_texto($_POST['sensor_tamanho'] ?? '', 20);
        $fov_h = $_POST['fov_h_graus'] ? (float)$_POST['fov_h_graus'] : null;
        $nocturna = isset($_POST['vision_nocturna']) ? 1 : 0;
        $audio = isset($_POST['audio']) ? 1 : 0;
        $localizacao = validar_texto($_POST['localizacao'] ?? '', 200);
        $canal = (int)($_POST['canal_dvr'] ?? 0);
        $obs = $_POST['observacoes'] ?? '';

        try {
            if ($post_action === 'add') {
                $stmt = $db->prepare("INSERT INTO equipamentos (projeto_id, tipo, subtipo, marca, modelo, numero_serie, mac_address, ip_address, resolucao_h, resolucao_v, megapixels, tipo_lente, distancia_focal_mm_min, distancia_focal_mm_max, sensor_tamanho, fov_h_graus, vision_nocturna, audio, localizacao, canal_dvr, observacoes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$projeto_id, $tipo, $subtipo, $marca, $modelo, $numero_serie, $mac, $ip, $resolucao_h, $resolucao_v, $mp, $tipo_lente, $focal_min, $focal_max, $sensor, $fov_h, $nocturna, $audio, $localizacao, $canal, $obs]);
                $_SESSION['flash'] = 'Equipamento adicionado.';
            } else {
                $stmt = $db->prepare("UPDATE equipamentos SET tipo=?, subtipo=?, marca=?, modelo=?, numero_serie=?, mac_address=?, ip_address=?, resolucao_h=?, resolucao_v=?, megapixels=?, tipo_lente=?, distancia_focal_mm_min=?, distancia_focal_mm_max=?, sensor_tamanho=?, fov_h_graus=?, vision_nocturna=?, audio=?, localizacao=?, canal_dvr=?, observacoes=? WHERE id=?");
                $stmt->execute([$tipo, $subtipo, $marca, $modelo, $numero_serie, $mac, $ip, $resolucao_h, $resolucao_v, $mp, $tipo_lente, $focal_min, $focal_max, $sensor, $fov_h, $nocturna, $audio, $localizacao, $canal, $obs, $id]);
                $_SESSION['flash'] = 'Equipamento atualizado.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
        }
        header("Location: index.php?p=equipamentos&projeto_id=$projeto_id"); exit;
    }

    if ($post_action === 'delete' && $id) {
        $q = $db->prepare("SELECT projeto_id FROM equipamentos WHERE id=?");
        $q->execute([$id]);
        $eq = $q->fetch();
        $pid = $eq ? $eq['projeto_id'] : 0;
        $db->prepare("DELETE FROM equipamentos WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = 'Equipamento eliminado.';
        header("Location: index.php?p=equipamentos&projeto_id=$pid"); exit;
    }
}

// ===== LIST =====
if ($action === 'list') {
    $projetos_list = $db->query("SELECT id, nome_projeto FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();

    $where = '1=1';
    $params = [];
    if ($projeto_id) { $where .= ' AND e.projeto_id = ?'; $params[] = $projeto_id; }

    $equips = $db->prepare("
        SELECT e.*, p.nome_projeto
        FROM equipamentos e
        JOIN projetos_cctv p ON e.projeto_id = p.id
        WHERE $where
        ORDER BY p.nome_projeto, e.tipo, e.localizacao
    ");
    $equips->execute($params);
    $equips = $equips->fetchAll();
?>
    <div class="page-header">
        <h1>📹 Equipamentos</h1>
        <div class="page-actions">
            <?php if ($projeto_id): ?>
            <a href="index.php?p=equipamentos&action=add&projeto_id=<?= $projeto_id ?>" class="btn btn-primary">+ Novo Equipamento</a>
            <?php endif; ?>
            <a href="index.php?p=projetos" class="btn btn-outline">← Projetos</a>
        </div>
    </div>

    <div class="filters">
        <form method="GET" style="display:contents">
            <input type="hidden" name="p" value="equipamentos">
            <div class="form-group">
                <label>Projeto</label>
                <select name="projeto_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos os projetos</option>
                    <?php foreach ($projetos_list as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $projeto_id === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['nome_projeto']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if (count($equips)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Projeto</th>
                        <th>Marca/Modelo</th>
                        <th>Localização</th>
                        <th>Resolução</th>
                        <th>Lente</th>
                        <th>IP / MAC</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equips as $eq): ?>
                    <tr>
                        <td>
                            <span class="badge badge-primary"><?= e($eq['tipo']) ?></span>
                            <?php if ($eq['subtipo']): ?><br><small><?= e($eq['subtipo']) ?></small><?php endif; ?>
                        </td>
                        <td><a href="index.php?p=projetos&action=view&id=<?= $eq['projeto_id'] ?>"><?= e($eq['nome_projeto']) ?></a></td>
                        <td><strong><?= e($eq['marca']) ?></strong> <?= e($eq['modelo']) ?></td>
                        <td><?= e($eq['localizacao'] ?: '-') ?></td>
                        <td>
                            <?php if ($eq['tipo'] === 'camera'): ?>
                                <?= $eq['resolucao_h'] ?>×<?= $eq['resolucao_v'] ?>
                                <br><small><?= fmtNumero($eq['megapixels'],1) ?> MP</small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($eq['tipo'] === 'camera'): ?>
                                <?= $eq['distancia_focal_mm_min'] ? fmtNumero($eq['distancia_focal_mm_min'],1).'mm' : '-' ?>
                                <?= $eq['distancia_focal_mm_max'] && $eq['distancia_focal_mm_max'] != $eq['distancia_focal_mm_min'] ? ' - '.fmtNumero($eq['distancia_focal_mm_max'],1).'mm' : '' ?>
                                <?php if ($eq['fov_h_graus']): ?><br><small><?= fmtNumero($eq['fov_h_graus'],1) ?>° FOV</small><?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem">
                            <?= $eq['ip_address'] ? $eq['ip_address'] : '' ?>
                            <?= $eq['mac_address'] ? '<br>'.$eq['mac_address'] : '' ?>
                        </td>
                        <td class="table-actions">
                            <a href="index.php?p=equipamentos&action=edit&id=<?= $eq['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar equipamento?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📹</div>
            <h3>Nenhum equipamento registado</h3>
            <?php if ($projeto_id): ?>
            <p><a href="index.php?p=equipamentos&action=add&projeto_id=<?= $projeto_id ?>">Adicionar equipamento</a></p>
            <?php else: ?>
            <p>Selecione um projeto para gerir equipamentos</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php
}

// ===== ADD / EDIT =====
if ($action === 'add' || ($action === 'edit' && $id)) {
    $eq = [
        'projeto_id'=>$projeto_id,'tipo'=>'camera','subtipo'=>'','marca'=>'','modelo'=>'',
        'numero_serie'=>'','mac_address'=>'','ip_address'=>'',
        'resolucao_h'=>1920,'resolucao_v'=>1080,'megapixels'=>2.0,
        'tipo_lente'=>'fixa','distancia_focal_mm_min'=>'','distancia_focal_mm_max'=>'',
        'sensor_tamanho'=>'1/2.7"','fov_h_graus'=>'',
        'vision_nocturna'=>0,'audio'=>0,'localizacao'=>'','canal_dvr'=>0,'observacoes'=>''
    ];
    $titulo = 'Novo Equipamento';
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM equipamentos WHERE id=?");
        $stmt->execute([$id]);
        $eq = $stmt->fetch();
        if (!$eq) { header('Location: index.php?p=equipamentos'); exit; }
        $projeto_id = $eq['projeto_id'];
        $titulo = 'Editar Equipamento';
    }

    $projetos = $db->query("SELECT id, nome_projeto FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();
?>
    <div class="page-header">
        <h1><?= $titulo ?></h1>
        <div class="page-actions">
            <a href="index.php?p=equipamentos&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Projeto *</label>
                    <select name="projeto_id" class="form-control" required>
                        <option value="">Selecionar projeto</option>
                        <?php foreach ($projetos as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= $eq['projeto_id'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['nome_projeto']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" class="form-control" id="sel-tipo" required>
                        <option value="camera" <?= $eq['tipo']==='camera'?'selected':'' ?>>📹 Câmara</option>
                        <option value="dvr" <?= $eq['tipo']==='dvr'?'selected':'' ?>>🖥️ DVR</option>
                        <option value="nvr" <?= $eq['tipo']==='nvr'?'selected':'' ?>>🖥️ NVR</option>
                        <option value="servidor" <?= $eq['tipo']==='servidor'?'selected':'' ?>>🖥️ Servidor</option>
                        <option value="switch" <?= $eq['tipo']==='switch'?'selected':'' ?>>🔀 Switch</option>
                        <option value="access_point" <?= $eq['tipo']==='access_point'?'selected':'' ?>>📡 Access Point</option>
                        <option value="fonte" <?= $eq['tipo']==='fonte'?'selected':'' ?>>⚡ Fonte</option>
                        <option value="cabo" <?= $eq['tipo']==='cabo'?'selected':'' ?>>🔌 Cabo</option>
                        <option value="outro" <?= $eq['tipo']==='outro'?'selected':'' ?>>📦 Outro</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Subtipo</label>
                    <select name="subtipo" class="form-control" id="sel-subtipo">
                        <option value="">—</option>
                        <option value="domo" <?= $eq['subtipo']==='domo'?'selected':'' ?>>Domo</option>
                        <option value="bullet" <?= $eq['subtipo']==='bullet'?'selected':'' ?>>Bullet</option>
                        <option value="ptz" <?= $eq['subtipo']==='ptz'?'selected':'' ?>>PTZ</option>
                        <option value="fixa" <?= $eq['subtipo']==='fixa'?'selected':'' ?>>Fixa</option>
                        <option value="multisensor" <?= $eq['subtipo']==='multisensor'?'selected':'' ?>>Multisensor</option>
                        <option value="fish-eye" <?= $eq['subtipo']==='fish-eye'?'selected':'' ?>>Fish-eye 360°</option>
                        <option value="thermal" <?= $eq['subtipo']==='thermal'?'selected':'' ?>>Térmica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" class="form-control" value="<?= e($eq['marca']) ?>" placeholder="Hikvision, Dahua, Axis...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="modelo" class="form-control" value="<?= e($eq['modelo']) ?>">
                </div>
                <div class="form-group">
                    <label>Nº Série</label>
                    <input type="text" name="numero_serie" class="form-control" value="<?= e($eq['numero_serie']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Endereço IP</label>
                    <input type="text" name="ip_address" class="form-control" value="<?= e($eq['ip_address']) ?>" placeholder="192.168.1.100">
                </div>
                <div class="form-group">
                    <label>Endereço MAC</label>
                    <input type="text" name="mac_address" class="form-control" value="<?= e($eq['mac_address']) ?>" placeholder="AA:BB:CC:DD:EE:FF">
                </div>
            </div>

            <div class="form-group">
                <label>Canal DVR/NVR</label>
                <input type="number" name="canal_dvr" class="form-control canal-field" value="<?= $eq['canal_dvr'] ?>" min="0" max="256">
            </div>

            <h3 style="color:var(--text-heading);margin:20px 0 12px">📷 Parâmetros da Câmara</h3>
            <p class="form-hint" style="margin-bottom:12px">Preencha apenas para equipamentos do tipo câmara</p>

            <div class="form-row">
                <div class="form-group">
                    <label>Resolução Horizontal (px)</label>
                    <input type="number" name="resolucao_h" class="form-control camera-field" value="<?= $eq['resolucao_h'] ?>" min="0" max="8192">
                </div>
                <div class="form-group">
                    <label>Resolução Vertical (px)</label>
                    <input type="number" name="resolucao_v" class="form-control camera-field" value="<?= $eq['resolucao_v'] ?>" min="0" max="8192">
                </div>
            </div>

            <div class="form-group">
                <label>Megapixels</label>
                <select name="megapixels" class="form-control camera-field">
                    <option value="0.3" <?= $eq['megapixels']==0.3?'selected':'' ?>>0.3 MP (VGA)</option>
                    <option value="1.0" <?= $eq['megapixels']==1.0?'selected':'' ?>>1 MP (720p)</option>
                    <option value="2.0" <?= $eq['megapixels']==2.0?'selected':'' ?>>2 MP (1080p)</option>
                    <option value="3.0" <?= $eq['megapixels']==3.0?'selected':'' ?>>3 MP</option>
                    <option value="4.0" <?= $eq['megapixels']==4.0?'selected':'' ?>>4 MP</option>
                    <option value="5.0" <?= $eq['megapixels']==5.0?'selected':'' ?>>5 MP</option>
                    <option value="6.0" <?= $eq['megapixels']==6.0?'selected':'' ?>>6 MP</option>
                    <option value="8.0" <?= $eq['megapixels']==8.0?'selected':'' ?>>8 MP (4K)</option>
                    <option value="12.0" <?= $eq['megapixels']==12.0?'selected':'' ?>>12 MP</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Lente</label>
                    <select name="tipo_lente" class="form-control camera-field">
                        <option value="fixa" <?= $eq['tipo_lente']==='fixa'?'selected':'' ?>>Fixa</option>
                        <option value="varifocal" <?= $eq['tipo_lente']==='varifocal'?'selected':'' ?>>Varifocal</option>
                        <option value="motorizada" <?= $eq['tipo_lente']==='motorizada'?'selected':'' ?>>Motorizada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sensor</label>
                    <select name="sensor_tamanho" class="form-control camera-field">
                        <option value="1/2.7\"" <?= $eq['sensor_tamanho']==='1/2.7"'?'selected':'' ?>>1/2.7" (5.6mm) — Standard</option>
                        <option value="1/2.5\"" <?= $eq['sensor_tamanho']==='1/2.5"'?'selected':'' ?>>1/2.5" (5.8mm)</option>
                        <option value="1/1.8\"" <?= $eq['sensor_tamanho']==='1/1.8"'?'selected':'' ?>>1/1.8" (7.2mm) — Baixa luz</option>
                        <option value="1/3\"" <?= $eq['sensor_tamanho']==='1/3"'?'selected':'' ?>>1/3" (4.8mm)</option>
                        <option value="1/2\"" <?= $eq['sensor_tamanho']==='1/2"'?'selected':'' ?>>1/2" (6.4mm)</option>
                        <option value="2/3\"" <?= $eq['sensor_tamanho']==='2/3"'?'selected':'' ?>>2/3" (8.8mm)</option>
                        <option value="1\"" <?= $eq['sensor_tamanho']==='1"'?'selected':'' ?>>1" (12.8mm)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Distância Focal Mínima (mm)</label>
                    <input type="number" step="0.1" name="distancia_focal_mm_min" class="form-control camera-field" value="<?= e($eq['distancia_focal_mm_min']) ?>" placeholder="2.8">
                </div>
                <div class="form-group">
                    <label>Distância Focal Máxima (mm)</label>
                    <input type="number" step="0.1" name="distancia_focal_mm_max" class="form-control camera-field" value="<?= e($eq['distancia_focal_mm_max']) ?>" placeholder="12">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>FOV Horizontal (graus)</label>
                    <input type="number" step="0.1" name="fov_h_graus" class="form-control camera-field" value="<?= e($eq['fov_h_graus']) ?>" placeholder="90">
                </div>
                <div class="form-group">
                    <label>Localização no local</label>
                    <input type="text" name="localizacao" class="form-control" value="<?= e($eq['localizacao']) ?>" placeholder="Entrada principal, corredor...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="vision_nocturna" value="1" <?= $eq['vision_nocturna'] ? 'checked' : '' ?>>
                        Visão Noturna (IR)
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="audio" value="1" <?= $eq['audio'] ? 'checked' : '' ?>>
                        Áudio
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" class="form-control"><?= e($eq['observacoes']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">💾 Guardar</button>
            <a href="index.php?p=equipamentos&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">Cancelar</a>
        </form>
    </div>

    <script>
    document.getElementById('sel-tipo').addEventListener('change', function() {
        const isCamera = this.value === 'camera';
        document.querySelectorAll('.camera-field').forEach(el => {
            el.closest('.form-group').style.display = isCamera ? 'block' : 'none';
        });
    });
    document.getElementById('sel-tipo').dispatchEvent(new Event('change'));
    </script>
<?php
}
