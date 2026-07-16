<?php
/**
 * Clientes — CRUD
 */
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action_post = $_POST['action'] ?? '';

    if ($action_post === 'add' || $action_post === 'edit') {
        $nome = validar_texto($_POST['nome'] ?? '', 200);
        $nif = validar_nif($_POST['nif'] ?? '');
        $morada = validar_texto($_POST['morada'] ?? '', 255);
        $localidade = validar_texto($_POST['localidade'] ?? '', 100);
        $codigo_postal = validar_texto($_POST['codigo_postal'] ?? '', 20);
        $telefone = validar_telefone($_POST['telefone'] ?? '');
        $email = validar_email($_POST['email'] ?? '');
        $contato_nome = validar_texto($_POST['contato_nome'] ?? '', 100);
        $contato_telefone = validar_telefone($_POST['contato_telefone'] ?? '');
        $contato_email = validar_email($_POST['contato_email'] ?? '');
        $notas = $_POST['notas'] ?? '';

        if (!$nome) { $_SESSION['flash'] = 'Nome do cliente é obrigatório.'; header('Location: index.php?p=clientes'); exit; }

        try {
            if ($action_post === 'add') {
                $stmt = $db->prepare("INSERT INTO clientes (nome, nif, morada, localidade, codigo_postal, telefone, email, contato_nome, contato_telefone, contato_email, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$nome, $nif, $morada, $localidade, $codigo_postal, $telefone, $email, $contato_nome, $contato_telefone, $contato_email, $notas]);
                logAuditoria('cliente_add', "Nome: $nome, NIF: $nif");
                $_SESSION['flash'] = 'Cliente adicionado com sucesso.';
            } else {
                $stmt = $db->prepare("UPDATE clientes SET nome=?, nif=?, morada=?, localidade=?, codigo_postal=?, telefone=?, email=?, contato_nome=?, contato_telefone=?, contato_email=?, notas=? WHERE id=?");
                $stmt->execute([$nome, $nif, $morada, $localidade, $codigo_postal, $telefone, $email, $contato_nome, $contato_telefone, $contato_email, $notas, $id]);
                logAuditoria('cliente_edit', "ID: $id, Nome: $nome");
                $_SESSION['flash'] = 'Cliente atualizado com sucesso.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
        }
        header('Location: index.php?p=clientes'); exit;
    }

    if ($action_post === 'delete') {
        if (!$id) { header('Location: index.php?p=clientes'); exit; }
        try {
            $db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
            logAuditoria('cliente_delete', "ID: $id");
            $_SESSION['flash'] = 'Cliente eliminado.';
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: Cliente tem projetos associados.';
        }
        header('Location: index.php?p=clientes'); exit;
    }
}

// List
if ($action === 'list') {
    $clientes = $db->query("SELECT c.*, (SELECT COUNT(*) FROM projetos_cctv WHERE cliente_id = c.id) AS total_projetos FROM clientes c ORDER BY c.nome")->fetchAll();
?>
    <div class="page-header">
        <h1>👥 Clientes</h1>
        <div class="page-actions">
            <a href="index.php?p=clientes&action=add" class="btn btn-primary">+ Novo Cliente</a>
        </div>
    </div>

    <div class="card">
        <?php if (count($clientes)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>NIF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Localidade</th>
                        <th>Projetos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><strong><?= e($c['nome']) ?></strong></td>
                        <td><?= e($c['nif'] ?? '-') ?></td>
                        <td><?= e($c['telefone'] ?? '-') ?></td>
                        <td><?= e($c['email'] ?? '-') ?></td>
                        <td><?= e($c['localidade'] ?? '-') ?></td>
                        <td><span class="badge badge-info"><?= $c['total_projetos'] ?></span></td>
                        <td class="table-actions">
                            <a href="index.php?p=clientes&action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                            <a href="index.php?p=projetos&cliente_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">📋</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar cliente?')">
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
            <div class="empty-icon">👥</div>
            <h3>Nenhum cliente registado</h3>
            <p>Adicione o primeiro cliente para começar</p>
        </div>
        <?php endif; ?>
    </div>
<?php
}

// Add / Edit form
if ($action === 'add' || $action === 'edit') {
    $c = ['nome'=>'','nif'=>'','morada'=>'','localidade'=>'','codigo_postal'=>'','telefone'=>'','email'=>'','contato_nome'=>'','contato_telefone'=>'','contato_email'=>'','notas'=>''];
    $titulo = 'Novo Cliente';
    if ($action === 'edit' && $id) {
        $c = $db->prepare("SELECT * FROM clientes WHERE id=?")->execute([$id]) ? $db->prepare("SELECT * FROM clientes WHERE id=?") : null;
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id=?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) { header('Location: index.php?p=clientes'); exit; }
        $titulo = 'Editar Cliente';
    }
?>
    <div class="page-header">
        <h1><?= $titulo ?></h1>
        <div class="page-actions">
            <a href="index.php?p=clientes" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" id="nome" name="nome" class="form-control" value="<?= e($c['nome']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="nif">NIF</label>
                    <input type="text" id="nif" name="nif" class="form-control" value="<?= e($c['nif']) ?>" placeholder="123456789" maxlength="9">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="morada">Morada</label>
                    <input type="text" id="morada" name="morada" class="form-control" value="<?= e($c['morada']) ?>">
                </div>
                <div class="form-group">
                    <label for="localidade">Localidade</label>
                    <input type="text" id="localidade" name="localidade" class="form-control" value="<?= e($c['localidade']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="codigo_postal">Código Postal</label>
                    <input type="text" id="codigo_postal" name="codigo_postal" class="form-control" value="<?= e($c['codigo_postal']) ?>" placeholder="1000-001">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" class="form-control" value="<?= e($c['telefone']) ?>" placeholder="+351 912345678">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($c['email']) ?>">
            </div>

            <h3 style="color:var(--text-heading);margin:20px 0 12px">Contacto Principal</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="contato_nome">Nome</label>
                    <input type="text" id="contato_nome" name="contato_nome" class="form-control" value="<?= e($c['contato_nome']) ?>">
                </div>
                <div class="form-group">
                    <label for="contato_telefone">Telefone</label>
                    <input type="text" id="contato_telefone" name="contato_telefone" class="form-control" value="<?= e($c['contato_telefone']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="contato_email">Email</label>
                <input type="email" id="contato_email" name="contato_email" class="form-control" value="<?= e($c['contato_email']) ?>">
            </div>

            <div class="form-group">
                <label for="notas">Notas</label>
                <textarea id="notas" name="notas" class="form-control"><?= e($c['notas']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">💾 Guardar</button>
            <a href="index.php?p=clientes" class="btn btn-outline">Cancelar</a>
        </form>
    </div>
<?php
}
