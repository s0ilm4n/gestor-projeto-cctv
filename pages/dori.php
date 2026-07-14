<?php
/**
 * Cálculos DORI (EN 62676-4) — Deteção, Observação, Reconhecimento, Identificação
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
        $equipamento_id = (int)($_POST['equipamento_id'] ?? 0) ?: null;
        $nome_zona = validar_texto($_POST['nome_zona'] ?? '', 100);
        $objetivo = $_POST['objetivo'] ?? 'reconhecimento';
        $nivel_risco = $_POST['nivel_risco'] ?? 'medio';
        $largura_cena = (float)($_POST['largura_cena_m'] ?? 8);
        $distancia_camera = (float)($_POST['distancia_camera_m'] ?? 10);
        $resolucao = (int)($_POST['resolucao_horizontal'] ?? 1920);
        $sensor = (float)($_POST['sensor_largura_mm'] ?? 5.60);
        $altura = (float)($_POST['altura_montagem_m'] ?? 3.0);
        $angulo = (float)($_POST['angulo_inclinacao'] ?? 45.0);

        // Calcular DORI com altura e ângulo
        $dori = calcularDORI($resolucao, 4.0, $sensor, $altura, $angulo, $distancia_camera);
        $ppm = $dori['ppm'];
        $fov = $dori['fov_h'];
        $focal_rec = $dori['focal_rec'];
        $nivel_dori = $dori['nivel'];

        // PPM necessário conforme objetivo
        $ppm_nec = ['deteccao' => 25, 'observacao' => 62, 'reconhecimento' => 125, 'identificacao' => 250];
        $ppm_necessario = $ppm_nec[$objetivo] ?? 125;
        $conforme = $ppm >= $ppm_necessario ? 1 : 0;

        try {
            if ($post_action === 'add') {
                $stmt = $db->prepare("INSERT INTO dori_calculos (projeto_id, equipamento_id, nome_zona, objetivo, nivel_risco, largura_cena_m, distancia_camera_m, resolucao_horizontal, sensor_largura_mm, altura_montagem_m, angulo_inclinacao, ppm_necessario, ppm_calculado, distancia_focal_recomendada_mm, fov_h_graus, conforme) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$projeto_id, $equipamento_id, $nome_zona, $objetivo, $nivel_risco, $largura_cena, $distancia_camera, $resolucao, $sensor, $altura, $angulo, $ppm_necessario, $ppm, $focal_rec, $fov, $conforme]);
                $_SESSION['flash'] = 'Cálculo DORI adicionado.';
            } else {
                $stmt = $db->prepare("UPDATE dori_calculos SET projeto_id=?, equipamento_id=?, nome_zona=?, objetivo=?, nivel_risco=?, largura_cena_m=?, distancia_camera_m=?, resolucao_horizontal=?, sensor_largura_mm=?, altura_montagem_m=?, angulo_inclinacao=?, ppm_necessario=?, ppm_calculado=?, distancia_focal_recomendada_mm=?, fov_h_graus=?, conforme=? WHERE id=?");
                $stmt->execute([$projeto_id, $equipamento_id, $nome_zona, $objetivo, $nivel_risco, $largura_cena, $distancia_camera, $resolucao, $sensor, $altura, $angulo, $ppm_necessario, $ppm, $focal_rec, $fov, $conforme, $id]);
                $_SESSION['flash'] = 'Cálculo DORI atualizado.';
            }

            // Atualizar conformidade do projeto
            $nc = $db->prepare("SELECT COUNT(*) FROM dori_calculos WHERE projeto_id=? AND conforme=0");
            $nc->execute([$projeto_id]);
            $tem_nc = $nc->fetchColumn() > 0;
            $db->prepare("UPDATE projetos_cctv SET conformidade_dori = ? WHERE id=?")->execute([$tem_nc ? 0 : 1, $projeto_id]);

        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
        }
        header("Location: index.php?p=dori&projeto_id=$projeto_id"); exit;
    }

    if ($post_action === 'delete' && $id) {
        $q = $db->prepare("SELECT projeto_id FROM dori_calculos WHERE id=?");
        $q->execute([$id]);
        $d = $q->fetch();
        $pid = $d ? $d['projeto_id'] : 0;
        $db->prepare("DELETE FROM dori_calculos WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = 'Cálculo eliminado.';
        header("Location: index.php?p=dori&projeto_id=$pid"); exit;
    }
}

// ===== LIST =====
if ($action === 'list') {
    $projetos = $db->query("SELECT id, nome_projeto, objetivo_dori_principal, conformidade_dori FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();

    $where = '1=1';
    $params = [];
    if ($projeto_id) { $where .= ' AND dc.projeto_id = ?'; $params[] = $projeto_id; }

    $calculos = $db->prepare("
        SELECT dc.*, p.nome_projeto, p.objetivo_dori_principal, e.marca, e.modelo, e.localizacao AS eq_local
        FROM dori_calculos dc
        JOIN projetos_cctv p ON dc.projeto_id = p.id
        LEFT JOIN equipamentos e ON dc.equipamento_id = e.id
        WHERE $where
        ORDER BY p.nome_projeto, dc.nome_zona
    ");
    $calculos->execute($params);
    $calculos = $calculos->fetchAll();
?>
    <div class="page-header">
        <h1>🔬 Cálculos DORI (EN 62676-4)</h1>
        <div class="page-actions">
            <?php if ($projeto_id): ?>
            <a href="index.php?p=dori&action=add&projeto_id=<?= $projeto_id ?>" class="btn btn-primary">+ Novo Cálculo</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;background:rgba(21,101,192,0.05);border-color:rgba(21,101,192,0.2)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:0.9rem">
            <div><strong>D — Detecção</strong><br><span style="color:var(--text-muted)">≥ 25 px/m — Presença</span></div>
            <div><strong>O — Observação</strong><br><span style="color:var(--text-muted)">≥ 62 px/m — Movimento</span></div>
            <div><strong>R — Reconhecimento</strong><br><span style="color:var(--text-muted)">≥ 125 px/m — Pessoa conhecida</span></div>
            <div><strong>I — Identificação</strong><br><span style="color:var(--text-muted)">≥ 250 px/m — Pessoa desconhecida</span></div>
        </div>
    </div>

    <div class="filters">
        <form method="GET" style="display:contents">
            <input type="hidden" name="p" value="dori">
            <div class="form-group">
                <label>Projeto</label>
                <select name="projeto_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($projetos as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $projeto_id === (int)$pr['id'] ? 'selected' : '' ?>>
                        <?= e($pr['nome_projeto']) ?>
                        <?= $pr['conformidade_dori'] === null ? '' : ($pr['conformidade_dori'] ? '✅' : '❌') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if (count($calculos)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Zona</th>
                        <th>Objetivo</th>
                        <th>Largura Cena</th>
                        <th>Distância</th>
                        <th>Resolução</th>
                        <th>PPM Real</th>
                        <th>PPM Nec.</th>
                        <th>Dist. Focal</th>
                        <th>FOV</th>
                        <th>Risco</th>
                        <th>Conforme</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculos as $c): ?>
                    <?php
                        $ppm = $c['ppm_calculado'];
                        $nec = $c['ppm_necessario'];
                        $conforme = $ppm >= $nec;
                    ?>
                    <tr>
                        <td><a href="index.php?p=projetos&action=view&id=<?= $c['projeto_id'] ?>"><?= e($c['nome_projeto']) ?></a></td>
                        <td>
                            <strong><?= e($c['nome_zona']) ?></strong>
                            <?php if ($c['eq_local']): ?><br><small style="color:var(--text-muted)"><?= e($c['eq_local']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge badge-primary"><?= e(ucfirst($c['objetivo'])) ?></span></td>
                        <td><?= fmtNumero($c['largura_cena_m'],2) ?> m</td>
                        <td><?= fmtNumero($c['distancia_camera_m'],2) ?> m</td>
                        <td><?= $c['resolucao_horizontal'] ?> px</td>
                        <td><strong style="font-size:1.1rem"><?= fmtNumero($ppm,0) ?></strong> ppm</td>
                        <td><?= $nec ?> ppm</td>
                        <td><?= fmtNumero($c['distancia_focal_recomendada_mm'],1) ?> mm</td>
                        <td><?= fmtNumero($c['fov_h_graus'],1) ?>°</td>
                        <td>
                            <span class="badge badge-<?= $c['nivel_risco'] === 'critico' ? 'danger' : ($c['nivel_risco'] === 'alto' ? 'warning' : 'info') ?>">
                                <?= e(ucfirst($c['nivel_risco'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($conforme): ?>
                                <span class="badge badge-success">✅ OK</span>
                            <?php else: ?>
                                <span class="badge badge-danger">❌</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a href="index.php?p=dori&action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar cálculo?')">
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
            <div class="empty-icon">🔬</div>
            <h3>Nenhum cálculo DORI</h3>
            <?php if ($projeto_id): ?>
            <p><a href="index.php?p=dori&action=add&projeto_id=<?= $projeto_id ?>">Adicionar o primeiro cálculo</a></p>
            <?php else: ?>
            <p>Selecione um projeto para começar</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($projeto_id && count($calculos)): ?>
    <!-- Resumo DORI do projeto -->
    <?php
        $total = count($calculos);
        $ok = 0; $nok = 0;
        foreach ($calculos as $c) { if ($c['ppm_calculado'] >= $c['ppm_necessario']) $ok++; else $nok++; }
    ?>
    <div class="card">
        <div class="card-header">📊 Resumo do Projeto</div>
        <div class="stats-grid" style="margin-bottom:0">
            <div class="stat-card"><div class="stat-value" style="color:#81C784"><?= $ok ?></div><div class="stat-label">Conformes</div></div>
            <div class="stat-card"><div class="stat-value" style="color:#EF5350"><?= $nok ?></div><div class="stat-label">Não Conformes</div></div>
            <div class="stat-card"><div class="stat-value"><?= fmtNumero($total ? ($ok/$total)*100 : 0,0) ?>%</div><div class="stat-label">Taxa Conformidade</div></div>
            <div class="stat-card"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Zonas</div></div>
        </div>
    </div>
    <?php endif; ?>
<?php
}

// ===== ADD / EDIT =====
if ($action === 'add' || ($action === 'edit' && $id)) {
    $d = [
        'projeto_id'=>$projeto_id,'equipamento_id'=>0,'nome_zona'=>'',
        'objetivo'=>'reconhecimento','nivel_risco'=>'medio',
        'largura_cena_m'=>8,'distancia_camera_m'=>10,
        'resolucao_horizontal'=>1920,'sensor_largura_mm'=>5.60,
        'altura_montagem_m'=>3.0,'angulo_inclinacao'=>45.0
    ];
    $titulo = 'Novo Cálculo DORI';
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM dori_calculos WHERE id=?");
        $stmt->execute([$id]);
        $d = $stmt->fetch();
        if (!$d) { header('Location: index.php?p=dori'); exit; }
        $projeto_id = $d['projeto_id'];
        $titulo = 'Editar Cálculo DORI';
    }

    $projetos = $db->query("SELECT id, nome_projeto FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();
    $equips = $db->prepare("SELECT id, tipo, marca, modelo, localizacao FROM equipamentos WHERE projeto_id=? ORDER BY tipo");
    $equips->execute([$projeto_id ?: 0]);
    $equips = $equips->fetchAll();

    // Pre-visualização DORI com inputs do formulário
    $preview_objetivos = ['deteccao'=>25,'observacao'=>62,'reconhecimento'=>125,'identificacao'=>250];
?>
    <div class="page-header">
        <h1><?= $titulo ?></h1>
        <div class="page-actions">
            <a href="index.php?p=dori&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
        <div class="card">
            <form method="POST" id="dori-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $action ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Projeto *</label>
                        <select name="projeto_id" class="form-control" required>
                            <option value="">Selecionar projeto</option>
                            <?php foreach ($projetos as $pr): ?>
                            <option value="<?= $pr['id'] ?>" <?= $d['projeto_id'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['nome_projeto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nome da Zona *</label>
                        <input type="text" name="nome_zona" class="form-control" value="<?= e($d['nome_zona']) ?>" required placeholder="Ex: Entrada principal">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Objetivo DORI *</label>
                        <select name="objetivo" class="form-control" id="dori-objetivo" required>
                            <option value="identificacao" <?= $d['objetivo']==='identificacao'?'selected':'' ?>>I — Identificação (250 px/m)</option>
                            <option value="reconhecimento" <?= $d['objetivo']==='reconhecimento'?'selected':'' ?>>R — Reconhecimento (125 px/m)</option>
                            <option value="observacao" <?= $d['objetivo']==='observacao'?'selected':'' ?>>O — Observação (62 px/m)</option>
                            <option value="deteccao" <?= $d['objetivo']==='deteccao'?'selected':'' ?>>D — Detecção (25 px/m)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nível de Risco</label>
                        <select name="nivel_risco" class="form-control">
                            <option value="baixo" <?= $d['nivel_risco']==='baixo'?'selected':'' ?>>Baixo</option>
                            <option value="medio" <?= $d['nivel_risco']==='medio'?'selected':'' ?>>Médio</option>
                            <option value="alto" <?= $d['nivel_risco']==='alto'?'selected':'' ?>>Alto</option>
                            <option value="critico" <?= $d['nivel_risco']==='critico'?'selected':'' ?>>Crítico</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="largura_cena">Largura da Cena (metros) *</label>
                        <input type="number" name="largura_cena_m" id="largura_cena" class="form-control" value="<?= e($d['largura_cena_m'] ?: '8') ?>" step="0.1" min="0.5" max="100" required>
                        <div class="form-hint">Largura horizontal que a câmara cobre no plano do alvo</div>
                    </div>
                    <div class="form-group">
                        <label for="distancia_camera">Distância Câmara-Alvo (metros) *</label>
                        <input type="number" name="distancia_camera_m" id="distancia_camera" class="form-control" value="<?= e($d['distancia_camera_m'] ?: '10') ?>" step="0.1" min="0.5" max="500" required>
                        <div class="form-hint">Distância da câmara ao ponto mais distante na cena</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="altura_montagem">Altura de Montagem (metros)</label>
                        <input type="number" name="altura_montagem_m" id="altura_montagem" class="form-control" value="<?= e($d['altura_montagem_m'] ?? '3.0') ?>" step="0.1" min="0.5" max="50">
                        <div class="form-hint">Altura a que a câmara está instalada</div>
                    </div>
                    <div class="form-group">
                        <label for="angulo_inclinacao">Ângulo de Inclinação (graus)</label>
                        <input type="number" name="angulo_inclinacao" id="angulo_inclinacao" class="form-control" value="<?= e($d['angulo_inclinacao'] ?? '45') ?>" step="1" min="1" max="90">
                        <div class="form-hint">0°=horizontal, 90°=para baixo</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Resolução Horizontal (px)</label>
                        <select name="resolucao_horizontal" class="form-control" id="resolucao">
                            <option value="640" <?= $d['resolucao_horizontal']==640?'selected':'' ?>>640 px (VGA)</option>
                            <option value="1280" <?= $d['resolucao_horizontal']==1280?'selected':'' ?>>1280 px (1MP)</option>
                            <option value="1920" <?= $d['resolucao_horizontal']==1920?'selected':'' ?>>1920 px (2MP — 1080p)</option>
                            <option value="2560" <?= $d['resolucao_horizontal']==2560?'selected':'' ?>>2560 px (4MP)</option>
                            <option value="3072" <?= $d['resolucao_horizontal']==3072?'selected':'' ?>>3072 px (5MP)</option>
                            <option value="3840" <?= $d['resolucao_horizontal']==3840?'selected':'' ?>>3840 px (8MP — 4K)</option>
                            <option value="4096" <?= $d['resolucao_horizontal']==4096?'selected':'' ?>>4096 px (12MP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tamanho do Sensor</label>
                        <select name="sensor_largura_mm" class="form-control">
                            <option value="5.60" <?= $d['sensor_largura_mm']==5.60?'selected':'' ?>>1/2.7" (5.6mm)</option>
                            <option value="5.80" <?= $d['sensor_largura_mm']==5.80?'selected':'' ?>>1/2.5" (5.8mm)</option>
                            <option value="7.20" <?= $d['sensor_largura_mm']==7.20?'selected':'' ?>>1/1.8" (7.2mm)</option>
                            <option value="4.80" <?= $d['sensor_largura_mm']==4.80?'selected':'' ?>>1/3" (4.8mm)</option>
                            <option value="6.40" <?= $d['sensor_largura_mm']==6.40?'selected':'' ?>>1/2" (6.4mm)</option>
                            <option value="8.80" <?= $d['sensor_largura_mm']==8.80?'selected':'' ?>>2/3" (8.8mm)</option>
                            <option value="12.80" <?= $d['sensor_largura_mm']==12.80?'selected':'' ?>>1" (12.8mm)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Equipamento (opcional)</label>
                    <select name="equipamento_id" class="form-control">
                        <option value="0">— Sem equipamento associado —</option>
                        <?php foreach ($equips as $eq): ?>
                        <option value="<?= $eq['id'] ?>" <?= $d['equipamento_id'] == $eq['id'] ? 'selected' : '' ?>>
                            <?= e($eq['tipo']) ?> — <?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> <?= $eq['localizacao'] ? '(' . e($eq['localizacao']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar Cálculo</button>
                <a href="index.php?p=dori&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">Cancelar</a>
            </form>
        </div>

        <!-- Live Preview -->
        <div class="card" id="preview-card">
            <div class="card-header">📊 Pré-visualização</div>
            <div style="font-size:0.9rem">
                <?php
                    $tmp_dori = calcularDORI(
                        $d['resolucao_horizontal'] ?: 1920,
                        4.0,
                        $d['sensor_largura_mm'] ?: 5.60,
                        $d['altura_montagem_m'] ?: 3.0,
                        $d['angulo_inclinacao'] ?: 45.0,
                        $d['distancia_camera_m'] ?: 10.0
                    );
                    $tmp_ppm = $tmp_dori['ppm'];
                    $tmp_nec = $preview_objetivos[$d['objetivo']] ?? 125;
                ?>
                <div class="info-row"><span class="label">PPM Real</span><span class="value" style="font-size:1.3rem;font-weight:700;color:<?= $tmp_ppm >= $tmp_nec ? '#81C784' : '#EF5350' ?>"><?= fmtNumero($tmp_ppm,0) ?> px/m</span></div>
                <div class="info-row"><span class="label">PPM Necessário</span><span class="value"><?= $tmp_nec ?> px/m</span></div>
                <div class="info-row"><span class="label">Resultado</span><span class="value"><?= $tmp_ppm >= $tmp_nec ? '<span style="color:#81C784">✅ CONFORME</span>' : '<span style="color:#EF5350">❌ NÃO CONFORME</span>' ?></span></div>
                <div class="info-row"><span class="label">Altura / Ângulo</span><span class="value"><?= fmtNumero($d['altura_montagem_m'] ?: 3.0,1) ?> m @ <?= fmtNumero($d['angulo_inclinacao'] ?: 45,0) ?>°</span></div>
                <div class="info-row"><span class="label">Dist. Real (linha visão)</span><span class="value"><?= fmtNumero($tmp_dori['dist_real'],1) ?> m</span></div>
                <div class="info-row"><span class="label">Distância Focal Recomendada</span><span class="value"><?= fmtNumero($tmp_dori['focal_rec'],1) ?> mm</span></div>
                <div class="info-row"><span class="label">FOV Horizontal</span><span class="value"><?= fmtNumero($tmp_dori['fov_h'],1) ?>°</span></div>
                <div class="info-row"><span class="label">Largura da Cena</span><span class="value"><?= fmtNumero($tmp_dori['largura_cena'],2) ?> m</span></div>
                <div class="info-row"><span class="label">Nível DORI Alcançado</span><span class="value">
                    <?php
                        if ($tmp_ppm >= 250) echo '<span class="dori-badge I">I — Identificação</span>';
                        elseif ($tmp_ppm >= 125) echo '<span class="dori-badge R">R — Reconhecimento</span>';
                        elseif ($tmp_ppm >= 62) echo '<span class="dori-badge O">O — Observação</span>';
                        elseif ($tmp_ppm >= 25) echo '<span class="dori-badge D">D — Detecção</span>';
                        else echo '<span style="color:#EF5350">Abaixo do mínimo</span>';
                    ?>
                </span></div>
                <div style="margin-top:12px;padding:8px;background:rgba(255,255,255,0.03);border-radius:6px">
                    <strong>Legenda:</strong><br>
                    <span style="color:#EF5350">I ≥ 250 px/m</span><br>
                    <span style="color:#FFB74D">R ≥ 125 px/m</span><br>
                    <span style="color:#AED581">O ≥ 62 px/m</span><br>
                    <span style="color:#90CAF9">D ≥ 25 px/m</span>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Live preview DORI
    document.querySelectorAll('#dori-form input, #dori-form select').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    function updatePreview() {
        const largura = parseFloat(document.getElementById('largura_cena').value) || 1;
        const distancia = parseFloat(document.getElementById('distancia_camera').value) || 1;
        const resolucao = parseInt(document.getElementById('resolucao').value) || 1920;
        const sensor = parseFloat(document.querySelector('[name="sensor_largura_mm"]').value) || 5.6;
        const altura = parseFloat(document.querySelector('[name="altura_montagem_m"]').value) || 3.0;
        const angulo = parseFloat(document.querySelector('[name="angulo_inclinacao"]').value) || 45;

        // Cálculo com altura e ângulo
        const angRad = Math.max(angulo, 1) * Math.PI / 180;
        const distReal = altura / Math.sin(angRad);
        const fov = 2 * Math.atan2(sensor, 2 * 4.0) * 180 / Math.PI;
        const larguraCena = 2 * distReal * Math.tan(fov * Math.PI / 360);
        const ppm = larguraCena > 0 ? resolucao / larguraCena : 0;
        const focalRec = (sensor * distReal) / (resolucao / 125);

        let nivel = '';
        if (ppm >= 250) nivel = '<span style="color:#EF5350">I — Identificação</span>';
        else if (ppm >= 125) nivel = '<span style="color:#FFB74D">R — Reconhecimento</span>';
        else if (ppm >= 62) nivel = '<span style="color:#AED581">O — Observação</span>';
        else if (ppm >= 25) nivel = '<span style="color:#90CAF9">D — Detecção</span>';
        else nivel = '<span style="color:#EF5350">Abaixo do mínimo</span>';

        document.getElementById('preview-card').querySelector('div:last-child').innerHTML = `
            <div class="card-header" style="border:none;padding:0 0 12px 0">📊 Pré-visualização</div>
            <div class="info-row"><span class="label">PPM Real</span><span class="value" style="font-size:1.3rem;font-weight:700;color:${ppm >= 125 ? '#81C784' : '#EF5350'}">${ppm.toFixed(0)} px/m</span></div>
            <div class="info-row"><span class="label">Altura / Ângulo</span><span class="value">${altura.toFixed(1)} m @ ${angulo.toFixed(0)}°</span></div>
            <div class="info-row"><span class="label">Dist. Real</span><span class="value">${distReal.toFixed(1)} m</span></div>
            <div class="info-row"><span class="label">Dist. Focal Recomendada</span><span class="value">${focalRec.toFixed(1)} mm</span></div>
            <div class="info-row"><span class="label">FOV Horizontal</span><span class="value">${fov.toFixed(1)}°</span></div>
            <div class="info-row"><span class="label">Largura Cena</span><span class="value">${larguraCena.toFixed(2)} m</span></div>
            <div class="info-row"><span class="label">Nível Alcançado</span><span class="value">${nivel}</span></div>
            <div style="margin-top:12px;padding:8px;background:rgba(255,255,255,0.03);border-radius:6px">
                <strong>Legenda:</strong><br>
                <span style="color:#EF5350">I ≥ 250 px/m</span><br>
                <span style="color:#FFB74D">R ≥ 125 px/m</span><br>
                <span style="color:#AED581">O ≥ 62 px/m</span><br>
                <span style="color:#90CAF9">D ≥ 25 px/m</span>
            </div>
        `;
    }
    </script>
<?php
}
