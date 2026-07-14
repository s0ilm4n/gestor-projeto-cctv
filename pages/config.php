<?php
/**
 * Configurações — Apenas Admin
 */
requireAdmin();
$db = getDB();
$msg = '';

// Guardar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $chaves = array_keys($_POST);
    $excluir = ['csrf_token', 'action'];
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($chaves as $chave) {
            if (in_array($chave, $excluir)) continue;
            $stmt->execute([$chave, $_POST[$chave]]);
        }
        $db->commit();
        $msg = 'Configurações guardadas com sucesso.';
    } catch (PDOException $e) {
        $db->rollBack();
        $msg = 'Erro: ' . $e->getMessage();
    }
}

$configs = $db->query("SELECT * FROM config ORDER BY chave")->fetchAll();
$config_map = [];
foreach ($configs as $c) $config_map[$c['chave']] = $c['valor'];
?>
<div class="page-header">
    <h1>⚙️ Configurações</h1>
    <a href="index.php" class="btn btn-outline">← Dashboard</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <?= csrf_field() ?>

        <div class="card-header">🏢 Empresa</div>
        <div class="form-row">
            <div class="form-group">
                <label>Nome da Empresa</label>
                <input type="text" name="empresa_nome" class="form-control" value="<?= e($config_map['empresa_nome'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>NIF</label>
                <input type="text" name="empresa_nif" class="form-control" value="<?= e($config_map['empresa_nif'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Morada</label>
                <input type="text" name="empresa_morada" class="form-control" value="<?= e($config_map['empresa_morada'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input type="text" name="empresa_telefone" class="form-control" value="<?= e($config_map['empresa_telefone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="empresa_email" class="form-control" value="<?= e($config_map['empresa_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Alvará PSP</label>
                <input type="text" name="alvara_psp" class="form-control" value="<?= e($config_map['alvara_psp'] ?? '') ?>" placeholder="Ex: 1234/PSP">
            </div>
        </div>

        <div class="card-header" style="margin-top:24px">📧 Email (SMTP)</div>
        <div class="form-row">
            <div class="form-group">
                <label>Servidor SMTP</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= e($config_map['smtp_host'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Porta</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= e($config_map['smtp_port'] ?? '587') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Utilizador</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= e($config_map['smtp_user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="smtp_pass" class="form-control" value="<?= e($config_map['smtp_pass'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email Remetente</label>
            <input type="email" name="smtp_from" class="form-control" value="<?= e($config_map['smtp_from'] ?? '') ?>">
        </div>

        <div class="card-header" style="margin-top:24px">⚡ Gerais</div>
        <div class="form-row">
            <div class="form-group">
                <label>Nome da App</label>
                <input type="text" name="app_nome" class="form-control" value="<?= e($config_map['app_nome'] ?? 'Gestor de Projeto CCTV') ?>">
            </div>
            <div class="form-group">
                <label>Retenção Padrão (dias)</label>
                <input type="number" name="retencao_padrao_dias" class="form-control" value="<?= e($config_map['retencao_padrao_dias'] ?? '30') ?>" min="1" max="365">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:20px">💾 Guardar Configurações</button>
    </form>
</div>
