<?php
/**
 * Editor de Plantas Interativo — Fabric.js
 * Gestor de Projeto CCTV
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Anti-cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?p=plantas'); exit; }

$planta = $db->prepare("SELECT pl.*, p.nome_projeto FROM plantas pl JOIN projetos_cctv p ON pl.projeto_id = p.id WHERE pl.id=?");
$planta->execute([$id]);
$planta = $planta->fetch();
if (!$planta) { header('Location: index.php?p=plantas'); exit; }

// Carregar câmaras, acessos e zonas da planta
$cameras = $db->prepare("SELECT * FROM plantas_cameras WHERE planta_id=? ORDER BY id");
$cameras->execute([$id]);
$cameras = $cameras->fetchAll();

$acessos = $db->prepare("SELECT * FROM plantas_acessos WHERE planta_id=? ORDER BY id");
$acessos->execute([$id]);
$acessos = $acessos->fetchAll();

$zonas = $db->prepare("SELECT * FROM plantas_zonas WHERE planta_id=?");
$zonas->execute([$id]);
$zonas = $zonas->fetchAll();

$cabos = $db->prepare("SELECT * FROM plantas_cabos WHERE planta_id=?");
$cabos->execute([$id]);
$cabos = $cabos->fetchAll();

$equipamentos = $db->prepare("SELECT id, tipo, marca, modelo, localizacao, resolucao_h, resolucao_v, distancia_focal_mm_min, distancia_focal_mm_max, sensor_tamanho FROM equipamentos WHERE projeto_id=? AND tipo='camera' ORDER BY localizacao");
$equipamentos->execute([$planta['projeto_id']]);
$equipamentos = $equipamentos->fetchAll();

// Carregar dados_json se existir
$dados_json = $planta['dados_json'];

$pageTitle = 'Editor: ' . $planta['nome'] . ' — ' . $planta['nome_projeto'];
$page = 'plantas';
$baseHref = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
    <h1>🗺️ <?= e($planta['nome']) ?> <small style="color:var(--text-muted);font-size:0.9rem">— <?= e($planta['nome_projeto']) ?></small></h1>
    <div class="page-actions">
        <button class="btn btn-success" onclick="guardarPlanta()">💾 Guardar</button>
        <button class="btn btn-outline" onclick="exportarPNG()">🖼 Exportar PNG</button>
        <a href="index.php?p=plantas&projeto_id=<?= $planta['projeto_id'] ?>" class="btn btn-outline">← Voltar</a>
    </div>
</div>

<div class="editor-layout">
    <!-- Coluna Principal: toolbar + canvas -->
    <div class="editor-main">
        <!-- Barra de Ferramentas (topo) -->
        <div class="editor-toolbar">
            <div class="tool-btn" data-tool="select" onclick="ativarFerramenta('select')">
                <span class="tool-icon">👆</span><span class="tool-label">Selecionar</span>
            </div>
            <div class="tool-btn" data-tool="wall" onclick="ativarFerramenta('wall')">
                <span class="tool-icon">🧱</span><span class="tool-label">Parede</span>
            </div>
            <div class="tool-btn" data-tool="camera" onclick="ativarFerramenta('camera')">
                <span class="tool-icon">📹</span><span class="tool-label">Câmara</span>
            </div>
            <div class="tool-btn" data-tool="access" onclick="ativarFerramenta('access')">
                <span class="tool-icon">🔐</span><span class="tool-label">Acesso</span>
            </div>
            <div class="tool-btn" data-tool="zona" onclick="ativarFerramenta('zona')">
                <span class="tool-icon">📐</span><span class="tool-label">Zona</span>
            </div>
            <div class="tool-btn" data-tool="medir" onclick="ativarFerramenta('medir')">
                <span class="tool-icon">📏</span><span class="tool-label">Medir</span>
            </div>
            <div class="tool-btn" data-tool="cabo" onclick="ativarFerramenta('cabo')">
                <span class="tool-icon">🔌</span><span class="tool-label">Cabo</span>
            </div>
            <div class="tool-btn" data-tool="texto" onclick="ativarFerramenta('texto')">
                <span class="tool-icon">A</span><span class="tool-label">Texto</span>
            </div>
            <div class="tool-sep"></div>
            <div class="tool-btn tool-btn-danger" onclick="eliminarSelecionado()">
                <span class="tool-icon">🗑️</span><span class="tool-label">Eliminar</span>
            </div>
            <div class="tool-sep"></div>
            <div class="toolbar-zoom-group">
                <button class="btn btn-sm btn-outline" onclick="zoomOut()" title="Diminuir zoom">➖</button>
                <span class="zoom-level" id="zoom-level" style="font-size:0.85rem;color:var(--text-muted);min-width:40px;text-align:center">100%</span>
                <button class="btn btn-sm btn-outline" onclick="zoomIn()" title="Aumentar zoom">➕</button>
                <button class="btn btn-sm btn-outline" onclick="zoomFit()" title="Ajustar">⊞</button>
                <button class="btn btn-sm btn-outline" onclick="zoomOneToOne()" title="100%">1:1</button>
            </div>
        </div>

        <!-- Canvas -->
        <div class="editor-canvas-container" id="canvas-container">
            <canvas id="plantaCanvas" width="<?= $planta['dimensao_x'] ?>" height="<?= $planta['dimensao_y'] ?>"></canvas>
        </div>
    </div>

    <!-- Painel Lateral (propriedades, cameras, zonas, acoes, info) -->
    <div class="editor-panel" id="editor-panel">
        <!-- Propriedades do Objeto -->
        <h3>⚙️ Propriedades</h3>
        <div id="propriedades-panel">
            <p style="color:var(--text-muted);font-size:0.85rem">Selecione um objeto para editar</p>
        </div>

        <!-- Câmaras na Planta -->
        <h3>📹 Câmaras</h3>
        <div id="camera-list">
            <?php if (count($cameras)): ?>
                <?php foreach ($cameras as $cam): ?>
                <div class="info-row" style="cursor:pointer" onclick="selecionarObjetoPorId('cam_<?= $cam['id'] ?>')">
                    <span class="label">📹 <?= e($cam['nome'] ?: 'Câmara #'.$cam['id']) ?></span>
                    <span class="value"><?= $cam['nivel_dori'] ?? '-' ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p style="color:var(--text-muted);font-size:0.85rem">Nenhuma câmara colocada</p>
            <?php endif; ?>
        </div>

        <!-- Zonas -->
        <h3>📐 Zonas</h3>
        <div id="zona-list">
            <?php if (count($zonas)): ?>
                <?php foreach ($zonas as $z): ?>
                <div class="zona-item" onclick="selecionarObjetoPorId('zona_<?= $z['id'] ?>')">
                    <span class="zona-cor" style="background:<?= e($z['cor']) ?>"></span>
                    <?= e($z['nome']) ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p style="color:var(--text-muted);font-size:0.85rem">Nenhuma zona definida</p>
            <?php endif; ?>
        </div>

        <!-- Ações Rápidas -->
        <h3>⚡ Ações</h3>
        <div style="display:flex;flex-direction:column;gap:8px">
            <button class="btn btn-sm btn-outline" onclick="document.getElementById('upload-dxf').click()">📂 Importar DXF</button>
            <input type="file" id="upload-dxf" accept=".dxf" style="display:none" onchange="importarDXF(this)">
            <button class="btn btn-sm btn-outline" onclick="document.getElementById('upload-image').click()">🖼 Colocar Imagem Fundo</button>
            <input type="file" id="upload-image" accept="image/*" style="display:none" onchange="colocarImagemFundo(this)">
            <button class="btn btn-sm btn-outline" onclick="limparCanvas()">🗑️ Limpar Tudo</button>
        </div>

        <!-- Info -->
        <h3>📊 Info</h3>
        <div style="font-size:0.85rem">
            <div class="info-row"><span class="label">Escala</span><span class="value">1 px = <?= $planta['escala_px_por_metro'] > 0 ? fmtNumero(1/$planta['escala_px_por_metro'],4) : '?' ?> m</span></div>
            <div class="info-row"><span class="label">Canvas</span><span class="value"><?= $planta['dimensao_x'] ?>×<?= $planta['dimensao_y'] ?> px</span></div>
            <div class="info-row"><span class="label">Cursor</span><span class="value" id="cursor-pos">—</span></div>
        </div>
    </div>
</div>

<!-- Modal Câmara -->
<div class="modal-overlay" id="modal-camera">
    <div class="modal-box">
        <h2>📹 Configurar Câmara</h2>
        <form id="form-camera">
            <input type="hidden" id="cam-object-id">
            <div class="form-group">
                <label>Nome</label>
                <input type="text" id="cam-nome" class="form-control" placeholder="Entrada principal">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Resolução H</label>
                    <select id="cam-res-h" class="form-control">
                        <option value="1920">1920 (1080p)</option>
                        <option value="2560">2560 (4MP)</option>
                        <option value="3840">3840 (4K)</option>
                        <option value="4096">4096 (12MP)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Resolução V</label>
                    <select id="cam-res-v" class="form-control">
                        <option value="1080">1080</option>
                        <option value="1440">1440</option>
                        <option value="2160">2160</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Distância Focal (mm)</label>
                    <input type="number" id="cam-focal" class="form-control" value="4.0" step="0.1" min="1" max="50">
                </div>
                <div class="form-group">
                    <label>Sensor (mm)</label>
                    <select id="cam-sensor" class="form-control">
                        <option value="5.60">1/2.7" (5.6mm)</option>
                        <option value="7.20">1/1.8" (7.2mm)</option>
                        <option value="12.80">1" (12.8mm)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Objetivo DORI</label>
                    <select id="cam-objetivo" class="form-control">
                        <option value="I">I — Identificação</option>
                        <option value="R" selected>R — Reconhecimento</option>
                        <option value="O">O — Observação</option>
                        <option value="D">D — Detecção</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Equipamento (BD)</label>
                    <select id="cam-equipamento" class="form-control">
                        <option value="0">— Novacâmara —</option>
                        <?php foreach ($equipamentos as $eq): ?>
                        <option value="<?= $eq['id'] ?>"><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> <?= $eq['localizacao'] ? '(' . e($eq['localizacao']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="cam-dori-preview" style="background:var(--bg-input);border-radius:6px;padding:12px;margin-bottom:16px">
                <strong>Pré-visualização DORI:</strong>
                <div id="cam-dori-info">Preencha os campos para calcular</div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-primary" onclick="guardarCamera()">💾 Aplicar</button>
                <button type="button" class="btn btn-outline" onclick="fecharModal('modal-camera')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="js/editor-planta.js?v=2"></script>
<script>
// CSRF token para AJAX
const CSRF_TOKEN = '<?= csrf_token() ?>';

// Inicializar editor com dados da BD
const PLANT_DATA = <?= json_encode([
    'id' => $planta['id'],
    'projeto_id' => $planta['projeto_id'],
    'nome' => $planta['nome'],
    'dimensao_x' => (int)$planta['dimensao_x'],
    'dimensao_y' => (int)$planta['dimensao_y'],
    'escala_px_por_metro' => (float)$planta['escala_px_por_metro'],
    'dados_json' => $dados_json
]) ?>;

const CAMERAS_DATA = <?= json_encode($cameras) ?>;
const ACESSOS_DATA = <?= json_encode($acessos) ?>;
const ZONAS_DATA = <?= json_encode($zonas) ?>;
const CABOS_DATA = <?= json_encode($cabos) ?>;

document.addEventListener('DOMContentLoaded', function() {
    inicializarEditor(PLANT_DATA, CAMERAS_DATA, ACESSOS_DATA, ZONAS_DATA, CABOS_DATA);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
