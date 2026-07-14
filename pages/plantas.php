<?php
/**
 * Plantas — Lista e gestão de plantas do projeto
 */
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$projeto_id = (int)($_GET['projeto_id'] ?? 0);

// POST: create/edit plant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        $projeto_id = (int)($_POST['projeto_id'] ?? 0);
        $nome = validar_texto($_POST['nome'] ?? '', 100);
        $tipo = $_POST['tipo'] ?? 'desenhada';
        $piso = (int)($_POST['piso'] ?? 0);
        $descricao = validar_texto($_POST['descricao'] ?? '', 255);

        try {
            if ($post_action === 'add') {
                $stmt = $db->prepare("INSERT INTO plantas (projeto_id, nome, tipo, piso, descricao) VALUES (?,?,?,?,?)");
                $stmt->execute([$projeto_id, $nome, $tipo, $piso, $descricao]);
                $novo_id = $db->lastInsertId();
                $_SESSION['flash'] = 'Planta criada.';
                header("Location: pages/editor-planta.php?id=$novo_id"); exit;
            } else {
                $stmt = $db->prepare("UPDATE plantas SET nome=?, tipo=?, piso=?, descricao=? WHERE id=?");
                $stmt->execute([$nome, $tipo, $piso, $descricao, $id]);
                $_SESSION['flash'] = 'Planta atualizada.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
        }
        header("Location: index.php?p=plantas&projeto_id=$projeto_id"); exit;
    }

    if ($post_action === 'delete' && $id) {
        $q = $db->prepare("SELECT projeto_id FROM plantas WHERE id=?");
        $q->execute([$id]);
        $pl = $q->fetch();
        $pid = $pl ? $pl['projeto_id'] : 0;
        try {
            $db->prepare("DELETE FROM plantas WHERE id=?")->execute([$id]);
            $_SESSION['flash'] = 'Planta eliminada.';
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro ao eliminar.';
        }
        header("Location: index.php?p=plantas&projeto_id=$pid"); exit;
    }
}

// ===== LIST =====
if ($action === 'list') {
    $projetos = $db->query("SELECT id, nome_projeto FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();

    $where = '1=1';
    $params = [];
    if ($projeto_id) { $where .= ' AND pl.projeto_id = ?'; $params[] = $projeto_id; }

    $plantas = $db->prepare("
        SELECT pl.*, p.nome_projeto,
            (SELECT COUNT(*) FROM plantas_cameras WHERE planta_id = pl.id) AS total_cameras,
            (SELECT COUNT(*) FROM plantas_acessos WHERE planta_id = pl.id) AS total_acessos
        FROM plantas pl
        JOIN projetos_cctv p ON pl.projeto_id = p.id
        WHERE $where
        ORDER BY pl.piso, pl.nome
    ");
    $plantas->execute($params);
    $plantas = $plantas->fetchAll();
?>
    <div class="page-header">
        <h1>🗺️ Plantas</h1>
        <div class="page-actions">
            <?php if ($projeto_id): ?>
            <a href="index.php?p=plantas&action=add&projeto_id=<?= $projeto_id ?>" class="btn btn-primary">+ Nova Planta</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="filters">
        <form method="GET" style="display:contents">
            <input type="hidden" name="p" value="plantas">
            <div class="form-group">
                <label>Projeto</label>
                <select name="projeto_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos os projetos</option>
                    <?php foreach ($projetos as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $projeto_id === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['nome_projeto']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if (count($plantas)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <?php foreach ($plantas as $pl): ?>
            <div style="background:var(--bg-input);border-radius:8px;border:1px solid var(--border);overflow:hidden">
                <div style="padding:16px">
                    <h3 style="color:var(--text-heading);margin-bottom:8px"><?= e($pl['nome']) ?></h3>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px">
                        📋 <?= e($pl['nome_projeto']) ?><br>
                        🏗️ <?= e($pl['tipo']) ?> | Piso: <?= $pl['piso'] > 0 ? $pl['piso'].'º' : 'R/C' ?><br>
                        📹 <?= $pl['total_cameras'] ?> câmaras | 🔐 <?= $pl['total_acessos'] ?> acessos
                    </div>
                    <?php if ($pl['descricao']): ?>
                    <p style="font-size:0.85rem;color:var(--text-muted)"><?= e($pl['descricao']) ?></p>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:4px;padding:8px 16px;border-top:1px solid var(--border)">
                    <a href="pages/editor-planta.php?id=<?= $pl['id'] ?>" class="btn btn-sm btn-primary">🗺️ Abrir Editor</a>
                    <a href="index.php?p=plantas&action=edit&id=<?= $pl['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar planta e todos os dados associados?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🗺️</div>
            <h3>Nenhuma planta</h3>
            <?php if ($projeto_id): ?>
            <p><a href="index.php?p=plantas&action=add&projeto_id=<?= $projeto_id ?>">Criar a primeira planta</a></p>
            <?php else: ?>
            <p>Selecione um projeto para gerir plantas</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php
}

// ===== ADD / EDIT =====
if ($action === 'add' || ($action === 'edit' && $id)) {
    $pl = ['projeto_id'=>$projeto_id,'nome'=>'','tipo'=>'desenhada','piso'=>0,'descricao'=>''];
    $titulo = 'Nova Planta';
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM plantas WHERE id=?");
        $stmt->execute([$id]);
        $pl = $stmt->fetch();
        if (!$pl) { header('Location: index.php?p=plantas'); exit; }
        $projeto_id = $pl['projeto_id'];
        $titulo = 'Editar Planta';
    }

    $projetos = $db->query("SELECT id, nome_projeto FROM projetos_cctv ORDER BY nome_projeto")->fetchAll();
?>
    <div class="page-header">
        <h1><?= $titulo ?></h1>
        <div class="page-actions">
            <a href="index.php?p=plantas&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">← Voltar</a>
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
                        <option value="">Selecionar</option>
                        <?php foreach ($projetos as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= $pl['projeto_id'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['nome_projeto']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nome da Planta *</label>
                    <input type="text" name="nome" class="form-control" value="<?= e($pl['nome']) ?>" required placeholder="Ex: Planta R/C, 1º Piso...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo" class="form-control">
                        <option value="desenhada" <?= $pl['tipo']==='desenhada'?'selected':'' ?>>Desenhada no editor</option>
                        <option value="dxf" <?= $pl['tipo']==='dxf'?'selected':'' ?>>Importada de DXF</option>
                        <option value="imagem" <?= $pl['tipo']==='imagem'?'selected':'' ?>>Imagem de fundo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Piso</label>
                    <input type="number" name="piso" class="form-control" value="<?= $pl['piso'] ?>" min="0" max="50">
                    <div class="form-hint">0 = Rés-do-chão</div>
                </div>
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <input type="text" name="descricao" class="form-control" value="<?= e($pl['descricao']) ?>" placeholder="Identificação da área/zona">
            </div>

            <button type="submit" class="btn btn-primary">💾 Guardar</button>
            <a href="index.php?p=plantas&projeto_id=<?= $projeto_id ?>" class="btn btn-outline">Cancelar</a>
        </form>
    </div>
<?php
}
