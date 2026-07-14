<?php
/**
 * Dashboard — Gestor de Projeto CCTV
 */
$db = getDB();

// Estatísticas
$stats = [];
$stats['projetos_ativos'] = $db->query("SELECT COUNT(*) FROM projetos_cctv WHERE estado NOT IN ('encerrado','concluído')")->fetchColumn();
$stats['projetos_total'] = $db->query("SELECT COUNT(*) FROM projetos_cctv")->fetchColumn();
$stats['clientes'] = $db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$stats['cameras'] = $db->query("SELECT COUNT(*) FROM equipamentos WHERE tipo = 'camera'")->fetchColumn();
$stats['equipamentos'] = $db->query("SELECT COUNT(*) FROM equipamentos")->fetchColumn();
$stats['conformes'] = $db->query("SELECT COUNT(*) FROM projetos_cctv WHERE conformidade_dori = 1")->fetchColumn();
$stats['nao_conformes'] = $db->query("SELECT COUNT(*) FROM projetos_cctv WHERE conformidade_dori = 0")->fetchColumn();

// Projetos recentes
$recentes = $db->query("
    SELECT p.*, c.nome AS cliente_nome
    FROM projetos_cctv p
    JOIN clientes c ON p.cliente_id = c.id
    ORDER BY p.updated_at DESC
    LIMIT 5
")->fetchAll();

// Alertas DORI
$alertas = $db->query("
    SELECT dc.*, p.nome_projeto, c.nome AS cliente_nome
    FROM dori_calculos dc
    JOIN projetos_cctv p ON dc.projeto_id = p.id
    JOIN clientes c ON p.cliente_id = c.id
    WHERE dc.conforme = 0
    ORDER BY dc.nivel_risco DESC
    LIMIT 5
")->fetchAll();
?>
<div class="page-header">
    <h1>📊 Dashboard</h1>
    <div class="page-actions">
        <a href="index.php?p=projetos&action=add" class="btn btn-primary">+ Novo Projeto</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= $stats['projetos_ativos'] ?></div>
        <div class="stat-label">Projetos Ativos</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= $stats['clientes'] ?></div>
        <div class="stat-label">Clientes</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📹</div>
        <div class="stat-value"><?= $stats['cameras'] ?></div>
        <div class="stat-label">Câmaras Instaladas</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $stats['conformes'] ?></div>
        <div class="stat-label">Projetos Conformes (DORI)</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Projetos Recentes -->
    <div class="card">
        <div class="card-header">📋 Projetos Recentes</div>
        <?php if (count($recentes)): ?>
        <table>
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Atualizado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentes as $r): ?>
                <tr>
                    <td><a href="index.php?p=projetos&action=view&id=<?= $r['id'] ?>"><?= e($r['nome_projeto']) ?></a></td>
                    <td><?= e($r['cliente_nome']) ?></td>
                    <td><span class="status-badge" style="background:rgba(21,101,192,0.15);color:var(--primary-light)"><?= e($r['estado']) ?></span></td>
                    <td><?= fmtData($r['updated_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>Nenhum projeto ainda</h3>
            <p>Crie o primeiro projeto CCTV</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Alertas DORI -->
    <div class="card">
        <div class="card-header">🚨 Alertas DORI — Não Conformes</div>
        <?php if (count($alertas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Zona</th>
                    <th>PPM</th>
                    <th>Risco</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alertas as $a): ?>
                <tr>
                    <td><a href="index.php?p=dori&projeto_id=<?= $a['projeto_id'] ?>"><?= e($a['nome_projeto']) ?></a></td>
                    <td><?= e($a['nome_zona']) ?></td>
                    <td>
                        <span class="badge badge-danger">
                            ❌ <?= fmtNumero($a['ppm_calculado']) ?> ppm
                            (objetivo: <?= $a['ppm_necessario'] ?>)
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $a['nivel_risco'] === 'critico' ? 'danger' : ($a['nivel_risco'] === 'alto' ? 'warning' : 'info') ?>">
                            <?= e(ucfirst($a['nivel_risco'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">✅</div>
            <h3>Tudo conforme</h3>
            <p>Todos os cálculos DORI estão dentro dos parâmetros</p>
        </div>
        <?php endif; ?>
    </div>
</div>
