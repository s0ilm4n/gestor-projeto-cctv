<?php
/**
 * Gestão de Utilizadores — Apenas Admin
 */
requireAdmin();
$db = getDB();
$msg = '';

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        $username = validar_texto($_POST['username'] ?? '', 50);
        $nome = validar_texto($_POST['nome'] ?? '', 100);
        $role = $_POST['role'] ?? 'tecnico';
        $email = validar_email($_POST['email'] ?? '');
        $telefone = validar_telefone($_POST['telefone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$nome) { $_SESSION['flash'] = 'Username e nome são obrigatórios.'; header('Location: index.php?p=users'); exit; }

        try {
            if ($post_action === 'add') {
                if (!$password) { $_SESSION['flash'] = 'Password é obrigatória.'; header('Location: index.php?p=users'); exit; }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, nome, email, telefone, role) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$username, $hash, $nome, $email, $telefone, $role]);
                $_SESSION['flash'] = 'Utilizador adicionado.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, nome=?, email=?, telefone=?, role=? WHERE id=?");
                    $stmt->execute([$username, $hash, $nome, $email, $telefone, $role, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username=?, nome=?, email=?, telefone=?, role=? WHERE id=?");
                    $stmt->execute([$username, $nome, $email, $telefone, $role, $id]);
                }
                $_SESSION['flash'] = 'Utilizador atualizado.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Erro: ' . $e->getMessage();
        }
        header('Location: index.php?p=users'); exit;
    }

    if ($post_action === 'toggle' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['user_id']) { $_SESSION['flash'] = 'Não pode desativar a si próprio.'; header('Location: index.php?p=users'); exit; }
        $user = $db->prepare("SELECT ativo FROM users WHERE id=?");
        $user->execute([$id]);
        $u = $user->fetch();
        if ($u) {
            $novo = $u['ativo'] ? 0 : 1;
            $db->prepare("UPDATE users SET ativo=? WHERE id=?")->execute([$novo, $id]);
            $_SESSION['flash'] = $novo ? 'Utilizador ativado.' : 'Utilizador desativado.';
        }
        header('Location: index.php?p=users'); exit;
    }
}

$users = $db->query("SELECT id, username, nome, email, role, ativo, created_at FROM users ORDER BY nome")->fetchAll();
?>
<div class="page-header">
    <h1>👤 Utilizadores</h1>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="abrirModalUser()">+ Novo Utilizador</button>
        <a href="index.php" class="btn btn-outline">← Dashboard</a>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Nome</th><th>Username</th><th>Email</th><th>Função</th><th>Estado</th><th>Criado em</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['nome']) ?></strong></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email'] ?? '-') ?></td>
                    <td><span class="badge badge-<?= $u['role']==='admin' ? 'primary' : 'info' ?>"><?= e($u['role']) ?></span></td>
                    <td><?= $u['ativo'] ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>' ?></td>
                    <td><?= fmtData($u['created_at']) ?></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-outline" onclick="editarUser(<?= $u['id'] ?>, '<?= e($u['username']) ?>', '<?= e($u['nome']) ?>', '<?= e($u['email']) ?>', '<?= e($u['telefone']) ?>', '<?= $u['role'] ?>')">✏️</button>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit" class="btn btn-sm btn-<?= $u['ativo'] ? 'warning' : 'success' ?>"><?= $u['ativo'] ? '🔇' : '🔊' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Utilizador -->
<div class="modal-overlay" id="modal-user">
    <div class="modal-box">
        <h2 id="modal-user-title">Novo Utilizador</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="user-action" value="add">
            <input type="hidden" name="id" id="user-id" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="user-username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="user-nome" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="user-email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="user-telefone" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password <span id="password-hint" class="form-hint">* obrigatória para novo</span></label>
                    <input type="password" name="password" id="user-password" class="form-control" placeholder="deixar vazio para manter">
                </div>
                <div class="form-group">
                    <label>Função</label>
                    <select name="role" id="user-role" class="form-control">
                        <option value="admin">Admin</option>
                        <option value="tecnico" selected>Técnico</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">💾 Guardar</button>
            <button type="button" class="btn btn-outline" onclick="fecharModalUser()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModalUser() {
    document.getElementById('modal-user-title').textContent = 'Novo Utilizador';
    document.getElementById('user-action').value = 'add';
    document.getElementById('user-id').value = '0';
    document.getElementById('user-username').value = '';
    document.getElementById('user-nome').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-telefone').value = '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = true;
    document.getElementById('password-hint').textContent = '* obrigatória';
    document.getElementById('user-role').value = 'tecnico';
    document.getElementById('modal-user').style.display = 'flex';
}

function editarUser(id, username, nome, email, telefone, role) {
    document.getElementById('modal-user-title').textContent = 'Editar Utilizador';
    document.getElementById('user-action').value = 'edit';
    document.getElementById('user-id').value = id;
    document.getElementById('user-username').value = username;
    document.getElementById('user-nome').value = nome;
    document.getElementById('user-email').value = email;
    document.getElementById('user-telefone').value = telefone;
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('password-hint').textContent = '(deixar vazio para manter)';
    document.getElementById('user-role').value = role;
    document.getElementById('modal-user').style.display = 'flex';
}

function fecharModalUser() {
    document.getElementById('modal-user').style.display = 'none';
}
</script>
