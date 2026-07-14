/**
 * Editor de Plantas CCTV — Fabric.js
 * Gestor de Projeto CCTV
 */

let canvas, ferramentaAtiva = 'select';
let objetosCamera = {};
let objetosAcesso = {};
let objetosZona = {};
let objetosCabo = {};
let escala = 1;
let plantaId = 0;
let projetoId = 0;
let pontosMedicao = [];
let linhaMedicao = null;
let desenhandoCabo = false;
let pontosCabo = [];
let objetoCounter = 0;

// Cores para zonas de segurança
const CORES_ZONA = {
    'baixo': 'rgba(76, 175, 80, 0.15)',
    'medio': 'rgba(255, 193, 7, 0.15)',
    'alto': 'rgba(255, 152, 0, 0.2)',
    'critico': 'rgba(244, 67, 54, 0.2)'
};

function inicializarEditor(plantData, camerasData, acessosData, zonasData, cabosData) {
    plantaId = plantData.id;
    projetoId = plantData.projeto_id;
    escala = plantData.escala_px_por_metro;

    canvas = new fabric.Canvas('plantaCanvas', {
        width: plantData.dimensao_x,
        height: plantData.dimensao_y,
        backgroundColor: '#1A1F2E',
        selection: true,
        preserveObjectStacking: true
    });

    // Grid
    desenharGrid();

    // Carregar dados guardados
    if (plantData.dados_json) {
        try {
            const dados = JSON.parse(plantData.dados_json);
            if (dados.objects) {
                canvas.loadFromJSON(dados.objects, function() {
                    canvas.renderAll();
                    reconstruirReferencias();
                });
            }
        } catch(e) {
            console.warn('Erro ao carregar dados_json:', e);
        }
    }

    // Eventos
    canvas.on('mouse:move', function(opt) {
        const p = opt.pointer;
        document.getElementById('cursor-pos').textContent = Math.round(p.x) + ', ' + Math.round(p.y);
    });

    canvas.on('mouse:down', function(opt) {
        if (ferramentaAtiva === 'camera') {
            adicionarCamera(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'access') {
            adicionarAcesso(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'wall') {
            adicionarParede(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'medir') {
            adicionarPontoMedicao(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'texto') {
            adicionarTexto(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'cabo') {
            adicionarPontoCabo(opt.pointer.x, opt.pointer.y);
        } else if (ferramentaAtiva === 'select') {
            // Selecionar objeto -> mostrar propriedades
            setTimeout(mostrarPropriedades, 100);
        }
    });

    canvas.on('selection:created', mostrarPropriedades);
    canvas.on('selection:updated', mostrarPropriedades);
    canvas.on('selection:cleared', function() {
        document.getElementById('propriedades-panel').innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem">Nenhum objeto selecionado</p>';
    });

    canvas.on('object:modified', function() {
        atualizarDoriCamera();
    });

    // Carregar dados
    camerasData.forEach(c => criarObjetoCamera(c));
    acessosData.forEach(a => criarObjetoAcesso(a));
    zonasData.forEach(z => criarObjetoZona(z));
    cabosData.forEach(cb => criarObjetoCabo(cb));

    canvas.renderAll();
}

function desenharGrid() {
    const gridSize = 50;
    for (let x = 0; x < canvas.width; x += gridSize) {
        canvas.add(new fabric.Line([x, 0, x, canvas.height], {
            stroke: 'rgba(255,255,255,0.03)', selectable: false, evented: false
        }));
    }
    for (let y = 0; y < canvas.height; y += gridSize) {
        canvas.add(new fabric.Line([0, y, canvas.width, y], {
            stroke: 'rgba(255,255,255,0.03)', selectable: false, evented: false
        }));
    }
}

function ativarFerramenta(tool) {
    ferramentaAtiva = tool;
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector(`[data-tool="${tool}"]`);
    if (btn) btn.classList.add('active');

    if (tool === 'medir') {
        pontosMedicao = [];
        if (linhaMedicao) canvas.remove(linhaMedicao);
    }
    if (tool === 'cabo') {
        desenhandoCabo = false;
        pontosCabo = [];
    }

    canvas.isDrawingMode = (tool === 'wall');
    if (tool === 'wall') {
        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
        canvas.freeDrawingBrush.color = '#546E7A';
        canvas.freeDrawingBrush.width = 3;
        canvas.freeDrawingBrush.strokeLineCap = 'round';
        canvas.freeDrawingBrush.strokeLineJoin = 'round';
    }
}

function adicionarParede(x, y) {
    // Paredes são adicionadas via desenho livre (freeDrawingMode)
    // Este método cria uma parede retangular simples com 2 cliques
}

function adicionarCamera(x, y) {
    const id = 'cam_' + (++objetoCouter);
    const angle = 0;

    // Ícone câmara (círculo + cone FOV)
    const group = criarGrupoCamera(x, y, 1920, 4.0, 5.6, 0, id);
    group.objectType = 'camera';
    group.cameraId = id;
    group.camData = {
        nome: 'Câmara ' + objetoCounter,
        resolucao_h: 1920,
        resolucao_v: 1080,
        distancia_focal_mm: 4.0,
        sensor_largura_mm: 5.6,
        objetivo_dori: 'R',
        ppm_calculado: 0,
        nivel_dori: null,
        conforme: null,
        equipamento_id: 0,
        destino_x: x + 100,
        destino_y: y,
        db_id: 0
    };

    canvas.add(group);
    canvas.setActiveObject(group);
    canvas.renderAll();

    // Abrir modal para configurar
    setTimeout(() => {
        selecionarObjetoPorId(id);
        abrirModalCamera(id);
    }, 200);
}

function criarGrupoCamera(x, y, resolucao_h, focal, sensor, angle, id) {
    // Círculo câmara
    const circle = new fabric.Circle({
        radius: 12,
        fill: '#1565C0',
        stroke: '#42A5F5',
        strokeWidth: 2,
        originX: 'center',
        originY: 'center',
        top: y,
        left: x,
        name: 'corpo_' + id
    });

    // Cone FOV
    const fov = 2 * Math.atan2(sensor, 2 * focal) * 180 / Math.PI;
    const comprimento = 80;
    const angRad = fov * Math.PI / 180 / 2;

    const cone = new fabric.Triangle({
        width: 2 * comprimento * Math.tan(angRad),
        height: comprimento,
        fill: 'rgba(66, 165, 245, 0.15)',
        stroke: 'rgba(66, 165, 245, 0.5)',
        strokeWidth: 1,
        originX: 'center',
        originY: 'top',
        top: y,
        left: x,
        angle: angle + 180,
        name: 'fov_' + id,
        evented: false
    });

    // Label
    const label = new fabric.Text('📹', {
        fontSize: 16,
        originX: 'center',
        originY: 'center',
        top: y,
        left: x,
        name: 'label_' + id,
        evented: false
    });

    return new fabric.Group([cone, circle, label], {
        left: x - 12,
        top: y - 12,
        objectType: 'camera',
        cameraId: id,
        hasControls: true,
        hasBorders: true
    });
}

function criarObjetoCamera(data) {
    const id = 'cam_' + data.id;
    objetoCounter = Math.max(objetoCouter, data.id + 1);

    const group = criarGrupoCamera(
        parseFloat(data.pos_x),
        parseFloat(data.pos_y),
        data.resolucao_h,
        parseFloat(data.distancia_focal_mm || 4.0),
        parseFloat(data.sensor_largura_mm || 5.6),
        parseFloat(data.orientacao_graus || 0),
        id
    );

    group.cameraId = id;
    group.camData = {
        nome: data.nome,
        resolucao_h: data.resolucao_h,
        resolucao_v: data.resolucao_v,
        distancia_focal_mm: parseFloat(data.distancia_focal_mm) || 4.0,
        sensor_largura_mm: parseFloat(data.sensor_largura_mm) || 5.6,
        objetivo_dori: data.objetivo_dori || 'R',
        ppm_calculado: data.ppm_calculado,
        nivel_dori: data.nivel_dori,
        conforme: data.conforme,
        equipamento_id: data.equipamento_id || 0,
        destino_x: parseFloat(data.destino_x) || (parseFloat(data.pos_x) + 100),
        destino_y: parseFloat(data.destino_y) || parseFloat(data.pos_y),
        db_id: data.id
    };

    group.angle = parseFloat(data.orientacao_graus) || 0;
    group.setCoords();
    canvas.add(group);
    objetosCamera[id] = group;
}

function adicionarAcesso(x, y) {
    const id = 'acc_' + (++objetoCouter);
    const circle = new fabric.Circle({
        radius: 10,
        fill: '#F57C00',
        stroke: '#FFB74D',
        strokeWidth: 2,
        originX: 'center',
        originY: 'center',
        left: x,
        top: y
    });
    const label = new fabric.Text('🔐', {
        fontSize: 14,
        originX: 'center',
        originY: 'center',
        left: x,
        top: y,
        evented: false
    });

    const group = new fabric.Group([circle, label], {
        left: x - 10,
        top: y - 10,
        objectType: 'access',
        accessId: id,
        accData: {
            nome: 'Acesso ' + objetoCounter,
            tipo: 'leitor',
            db_id: 0
        }
    });

    canvas.add(group);
    canvas.setActiveObject(group);
    canvas.renderAll();
}

function criarObjetoAcesso(data) {
    const id = 'acc_' + data.id;
    const circle = new fabric.Circle({
        radius: 10, fill: '#F57C00', stroke: '#FFB74D',
        strokeWidth: 2, originX: 'center', originY: 'center',
        left: parseFloat(data.pos_x), top: parseFloat(data.pos_y)
    });
    const label = new fabric.Text('🔐', {
        fontSize: 14, originX: 'center', originY: 'center',
        left: parseFloat(data.pos_x), top: parseFloat(data.pos_y), evented: false
    });
    const group = new fabric.Group([circle, label], {
        left: parseFloat(data.pos_x) - 10, top: parseFloat(data.pos_y) - 10,
        objectType: 'access', accessId: id,
        accData: { nome: data.nome, tipo: data.tipo, db_id: data.id }
    });
    canvas.add(group);
    objetosAcesso[id] = group;
}

function adicionarTexto(x, y) {
    const text = new fabric.IText('Texto', {
        left: x, top: y,
        fontFamily: 'Arial', fontSize: 14,
        fill: '#E0E0E0',
        objectType: 'text'
    });
    canvas.add(text);
    canvas.setActiveObject(text);
    canvas.renderAll();
}

// ZONAS
function adicionarZona(poligono, nome, nivel, cor) {
    const polygon = new fabric.Polygon(poligono, {
        fill: cor || CORES_ZONA[nivel] || 'rgba(76,175,80,0.15)',
        stroke: '#4CAF50',
        strokeWidth: 1,
        objectType: 'zona',
        selectable: true,
        hasControls: true
    });
    canvas.add(polygon);
    canvas.renderAll();
}

function criarObjetoZona(data) {
    try {
        const coords = JSON.parse(data.poligono_json);
        const polygon = new fabric.Polygon(coords, {
            fill: data.cor || CORES_ZONA[data.nivel_seguranca] || 'rgba(76,175,80,0.15)',
            stroke: data.cor || '#4CAF50',
            strokeWidth: 1,
            objectType: 'zona',
            zonaId: 'zona_' + data.id,
            zonaData: data
        });
        canvas.add(polygon);
        objetosZona['zona_' + data.id] = polygon;
    } catch(e) {}
}

// CABOS
function adicionarPontoCabo(x, y) {
    if (!desenhandoCabo) {
        desenhandoCabo = true;
        pontosCabo = [{x, y}];
    } else {
        pontosCabo.push({x, y});
    }

    // Remover linha anterior se existir
    if (linhaMedicao) {
        canvas.remove(linhaMedicao);
    }

    if (pontosCabo.length >= 2) {
        const pts = [];
        pontosCabo.forEach(p => pts.push(p.x, p.y));
        linhaMedicao = new fabric.Polyline(pts, {
            stroke: '#F57C00',
            strokeWidth: 2,
            fill: null,
            objectType: 'cabo_temp',
            evented: false
        });
        canvas.add(linhaMedicao);
        canvas.renderAll();

        // Finalizar com duplo clique
        if (pontosCabo.length >= 3) {
            desenhandoCabo = false;
            const caboId = 'cabo_' + (++objetoCouter);
            const caboFinal = new fabric.Polyline(pts, {
                stroke: '#F57C00',
                strokeWidth: 2,
                fill: null,
                objectType: 'cabo',
                caboId: caboId,
                evented: true
            });
            canvas.remove(linhaMedicao);
            canvas.add(caboFinal);
            canvas.renderAll();
            objetosCabo[caboId] = caboFinal;
        }
    }
}

function criarObjetoCabo(data) {
    try {
        const pts = JSON.parse(data.caminho_json);
        const flat = [];
        pts.forEach(p => flat.push(p[0], p[1]));
        const polyline = new fabric.Polyline(flat, {
            stroke: '#F57C00',
            strokeWidth: 2,
            fill: null,
            objectType: 'cabo',
            caboId: 'cabo_' + data.id,
            evented: false
        });
        canvas.add(polyline);
        objetosCabo['cabo_' + data.id] = polyline;
    } catch(e) {}
}

// MEDIÇÃO
function adicionarPontoMedicao(x, y) {
    pontosMedicao.push({x, y});

    const dot = new fabric.Circle({
        radius: 3,
        fill: '#FFB74D',
        left: x - 3,
        top: y - 3,
        evented: false,
        originX: 'center',
        originY: 'center'
    });
    canvas.add(dot);

    if (pontosMedicao.length === 2) {
        const p1 = pontosMedicao[0];
        const p2 = pontosMedicao[1];
        const dist = Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
        const distReal = escala > 0 ? dist / escala : dist;

        const line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
            stroke: '#FFB74D',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            evented: false
        });
        canvas.add(line);

        const midX = (p1.x + p2.x) / 2;
        const midY = (p1.y + p2.y) / 2;
        const label = new fabric.Text(
            dist.toFixed(0) + ' px' + (escala > 0 ? '\n' + (distReal).toFixed(2) + ' m' : ''),
            {
                fontSize: 11,
                fill: '#FFB74D',
                left: midX + 5,
                top: midY - 10,
                evented: false
            }
        );
        canvas.add(label);
        canvas.renderAll();

        // Reset após 2 segundos
        setTimeout(() => {
            pontosMedicao = [];
            ferramentaAtiva = 'select';
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        }, 2000);
    }
    canvas.renderAll();
}

// PROPRIEDADES
function mostrarPropriedades() {
    const obj = canvas.getActiveObject();
    if (!obj) return;

    const panel = document.getElementById('propriedades-panel');

    if (obj.objectType === 'camera') {
        const d = obj.camData;
        const ppm = d.ppm_calculado || 0;
        const nec = { 'D': 25, 'O': 62, 'R': 125, 'I': 250 }[d.objetivo_dori] || 125;
        panel.innerHTML = `
            <div class="info-row"><span class="label">📹 Nome</span><span class="value">${d.nome}</span></div>
            <div class="info-row"><span class="label">Resolução</span><span class="value">${d.resolucao_h}×${d.resolucao_v}</span></div>
            <div class="info-row"><span class="label">Focal</span><span class="value">${d.distancia_focal_mm} mm</span></div>
            <div class="info-row"><span class="label">Sensor</span><span class="value">${d.sensor_largura_mm} mm</span></div>
            <div class="info-row"><span class="label">DORI</span><span class="value">${d.nivel_dori ? '<span class="dori-badge '+d.nivel_dori+'">'+d.nivel_dori+'</span>' : '-'}</span></div>
            <div class="info-row"><span class="label">PPM</span><span class="value" style="color:${d.conforme ? '#81C784' : '#EF5350'}">${ppm > 0 ? ppm.toFixed(0) + ' ppm' : '-'}</span></div>
            <button class="btn btn-sm btn-primary" style="width:100%;margin-top:8px" onclick="abrirModalCamera('${obj.cameraId}')">⚙️ Configurar</button>
        `;
    } else if (obj.objectType === 'access') {
        const d = obj.accData;
        panel.innerHTML = `
            <div class="info-row"><span class="label">🔐 Nome</span><span class="value">${d.nome || '-'}</span></div>
            <div class="info-row"><span class="label">Tipo</span><span class="value">${d.tipo || '-'}</span></div>
        `;
    } else if (obj.objectType === 'zona') {
        panel.innerHTML = `
            <div class="info-row"><span class="label">📐 Zona</span><span class="value">${obj.zonaData?.nome || '-'}</span></div>
        `;
    } else if (obj.type === 'i-text') {
        panel.innerHTML = `<button class="btn btn-sm btn-primary" style="width:100%" onclick="editarTexto()">✏️ Editar Texto</button>`;
    } else if (obj.type === 'polyline' && obj.objectType === 'cabo') {
        panel.innerHTML = `<div class="info-row"><span class="label">🔌 Cabo</span><span class="value">UTP/FTP</span></div>`;
    }
}

function editarTexto() {
    const obj = canvas.getActiveObject();
    if (obj && obj.type === 'i-text') {
        obj.enterEditing();
    }
}

// MODAL CÂMARA
function abrirModalCamera(id) {
    const obj = objetosCamera[id] || canvas.getObjects().find(o => o.cameraId === id);
    if (!obj || !obj.camData) return;

    const d = obj.camData;
    document.getElementById('cam-object-id').value = id;
    document.getElementById('cam-nome').value = d.nome || '';
    document.getElementById('cam-res-h').value = d.resolucao_h || 1920;
    document.getElementById('cam-res-v').value = d.resolucao_v || 1080;
    document.getElementById('cam-focal').value = d.distancia_focal_mm || 4.0;
    document.getElementById('cam-sensor').value = d.sensor_largura_mm || 5.6;
    document.getElementById('cam-objetivo').value = d.objetivo_dori || 'R';
    document.getElementById('cam-equipamento').value = d.equipamento_id || 0;

    atualizarPreviewDori();
    document.getElementById('modal-camera').style.display = 'flex';
}

function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}

function guardarCamera() {
    const id = document.getElementById('cam-object-id').value;
    const obj = objetosCamera[id] || canvas.getObjects().find(o => o.cameraId === id);
    if (!obj) return;

    const nome = document.getElementById('cam-nome').value || 'Câmara';
    const resH = parseInt(document.getElementById('cam-res-h').value);
    const resV = parseInt(document.getElementById('cam-res-v').value);
    const focal = parseFloat(document.getElementById('cam-focal').value);
    const sensor = parseFloat(document.getElementById('cam-sensor').value);
    const objetivo = document.getElementById('cam-objetivo').value;
    const equipId = parseInt(document.getElementById('cam-equipamento').value);

    obj.camData.nome = nome;
    obj.camData.resolucao_h = resH;
    obj.camData.resolucao_v = resV;
    obj.camData.distancia_focal_mm = focal;
    obj.camData.sensor_largura_mm = sensor;
    obj.camData.objetivo_dori = objetivo;
    obj.camData.equipamento_id = equipId;

    // Atualizar FOV visual
    const fov = 2 * Math.atan2(sensor, 2 * focal) * 180 / Math.PI;
    const comprimento = 80;
    const angRad = fov * Math.PI / 180 / 2;

    // Recalcular PPM (assumindo destino)
    atualizarDoriCamera();
    canvas.renderAll();

    fecharModal('modal-camera');
    mostrarPropriedades();
}

function atualizarDoriCamera() {
    canvas.getObjects().forEach(function(obj) {
        if (obj.objectType === 'camera' && obj.camData) {
            const d = obj.camData;
            const nec = { 'D': 25, 'O': 62, 'R': 125, 'I': 250 }[d.objetivo_dori] || 125;

            // Calcular PPM baseado na largura da cena estimada
            // Usamos a distância focal + sensor + orientação para estimar
            const fov = 2 * Math.atan2(d.sensor_largura_mm, 2 * d.distancia_focal_mm);
            const distAlvo = 50; // distância estimada em pixels no canvas
            const larguraCena = 2 * distAlvo * Math.tan(fov / 2);
            const ppm = larguraCena > 0 ? d.resolucao_h / (larguraCena / escala) : 0;

            d.ppm_calculado = ppm;
            d.nivel_dori = ppm >= 250 ? 'I' : ppm >= 125 ? 'R' : ppm >= 62 ? 'O' : ppm >= 25 ? 'D' : null;
            d.conforme = ppm >= nec;
        }
    });
}

function atualizarPreviewDori() {
    const resH = parseInt(document.getElementById('cam-res-h').value);
    const focal = parseFloat(document.getElementById('cam-focal').value) || 4;
    const sensor = parseFloat(document.getElementById('cam-sensor').value) || 5.6;
    const objetivo = document.getElementById('cam-objetivo').value;

    const fov = 2 * Math.atan2(sensor, 2 * focal) * 180 / Math.PI;
    const distEstimada = 10; // metros
    const larguraCena = 2 * distEstimada * Math.tan(fov * Math.PI / 360);
    const ppm = larguraCena > 0 ? resH / larguraCena : 0;
    const nec = { 'D': 25, 'O': 62, 'R': 125, 'I': 250 }[objetivo] || 125;

    document.getElementById('cam-dori-info').innerHTML = `
        <div class="info-row"><span class="label">FOV</span><span class="value">${fov.toFixed(1)}°</span></div>
        <div class="info-row"><span class="label">PPM (10m)</span><span class="value" style="color:${ppm >= nec ? '#81C784' : '#EF5350'}">${ppm.toFixed(0)} px/m</span></div>
        <div class="info-row"><span class="label">Conforme</span><span class="value">${ppm >= nec ? '✅ Sim' : '❌ Não'}</span></div>
    `;
}

// Adicionar listeners ao modal
document.addEventListener('input', function(e) {
    if (e.target.closest('#form-camera')) {
        atualizarPreviewDori();
    }
});

function eliminarSelecionado() {
    const obj = canvas.getActiveObject();
    if (obj) {
        if (confirm('Eliminar objeto selecionado?')) {
            canvas.remove(obj);
            canvas.renderAll();
            document.getElementById('propriedades-panel').innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem">Nenhum objeto selecionado</p>';
        }
    }
}

function selecionarObjetoPorId(id) {
    const obj = canvas.getObjects().find(o => o.cameraId === id || o.accessId === id || o.zonaId === id);
    if (obj) {
        canvas.setActiveObject(obj);
        canvas.renderAll();
        mostrarPropriedades();
    }
}

function limparCanvas() {
    if (confirm('Tem a certeza? Isto elimina todos os objetos visuais.')) {
        canvas.clear();
        canvas.backgroundColor = '#1A1F2E';
        desenharGrid();
        canvas.renderAll();
        objetosCamera = {};
        objetosAcesso = {};
        objetosZona = {};
        objetosCabo = {};
    }
}

// GUARDAR
function guardarPlanta() {
    const dados = canvas.toJSON(['objectType', 'cameraId', 'accessId', 'zonaId', 'caboId', 'camData', 'accData', 'zonaData']);

    fetch('ajax/guardar-planta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            planta_id: plantaId,
            dados_json: JSON.stringify({ objects: dados }),
            cameras: extrairDadosCameras(),
            acessos: extrairDadosAcessos(),
            zonas: extrairDadosZonas(),
            cabos: extrairDadosCabos()
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('✅ Planta guardada com sucesso!');
        } else {
            alert('❌ Erro: ' + (res.error || 'desconhecido'));
        }
    })
    .catch(err => alert('❌ Erro de rede: ' + err));
}

function extrairDadosCameras() {
    const cameras = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'camera' && obj.camData) {
            cameras.push({
                id: obj.cameraId,
                db_id: obj.camData.db_id,
                nome: obj.camData.nome,
                pos_x: Math.round(obj.left),
                pos_y: Math.round(obj.top),
                orientacao_graus: Math.round(obj.angle),
                resolucao_h: obj.camData.resolucao_h,
                resolucao_v: obj.camData.resolucao_v,
                distancia_focal_mm: obj.camData.distancia_focal_mm,
                sensor_largura_mm: obj.camData.sensor_largura_mm,
                objetivo_dori: obj.camData.objetivo_dori,
                ppm_calculado: obj.camData.ppm_calculado,
                nivel_dori: obj.camData.nivel_dori,
                conforme: obj.camData.conforme,
                equipamento_id: obj.camData.equipamento_id
            });
        }
    });
    return cameras;
}

function extrairDadosAcessos() {
    const acessos = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'access' && obj.accData) {
            acessos.push({
                db_id: obj.accData.db_id,
                nome: obj.accData.nome,
                tipo: obj.accData.tipo,
                pos_x: Math.round(obj.left),
                pos_y: Math.round(obj.top)
            });
        }
    });
    return acessos;
}

function extrairDadosZonas() {
    const zonas = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'zona' && obj.zonaData && obj.points) {
            zonas.push({
                db_id: obj.zonaData.id,
                nome: obj.zonaData.nome,
                nivel_seguranca: obj.zonaData.nivel_seguranca,
                poligono_json: JSON.stringify(obj.points.map(p => [p.x, p.y])),
                cor: obj.fill
            });
        }
    });
    return zonas;
}

function extrairDadosCabos() {
    const cabos = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'cabo' && obj.points) {
            const pts = [];
            for (let i = 0; i < obj.points.length; i++) {
                pts.push([obj.points[i].x, obj.points[i].y]);
            }
            cabos.push({ caminho_json: JSON.stringify(pts) });
        }
    });
    return cabos;
}

function reconstruirReferencias() {
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'camera' && obj.cameraId) {
            objetosCamera[obj.cameraId] = obj;
        }
        if (obj.objectType === 'access' && obj.accessId) {
            objetosAcesso[obj.accessId] = obj;
        }
        if (obj.objectType === 'zona' && obj.zonaId) {
            objetosZona[obj.zonaId] = obj;
        }
        if (obj.objectType === 'cabo' && obj.caboId) {
            objetosCabo[obj.caboId] = obj;
        }
    });
}

// EXPORTAR PNG
function exportarPNG() {
    const dataURL = canvas.toDataURL({ format: 'png', multiplier: 2 });
    const link = document.createElement('a');
    link.download = 'planta_' + plantaId + '.png';
    link.href = dataURL;
    link.click();
}

// IMPORTAR DXF
function importarDXF(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const conteudo = e.target.result;
        fetch('ajax/importar-dxf.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'planta_id=' + plantaId + '&dxf_content=' + encodeURIComponent(conteudo)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.objects) {
                res.objects.forEach(obj => {
                    if (obj.type === 'line') {
                        const line = new fabric.Line([obj.x1, obj.y1, obj.x2, obj.y2], {
                            stroke: '#546E7A', strokeWidth: 2, selectable: false
                        });
                        canvas.add(line);
                    } else if (obj.type === 'circle') {
                        const circle = new fabric.Circle({
                            left: obj.x, top: obj.y, radius: obj.r,
                            stroke: '#546E7A', strokeWidth: 1, fill: null
                        });
                        canvas.add(circle);
                    }
                });
                canvas.renderAll();
                alert('✅ DXF importado com ' + res.objects.length + ' objetos');
            } else {
                alert('❌ Erro ao importar DXF: ' + (res.error || 'desconhecido'));
            }
        });
    };
    reader.readAsText(file);
}

// IMPORTAR IMAGEM
function colocarImagemFundo(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        fabric.Image.fromURL(e.target.result, function(img) {
            img.set({
                left: 0, top: 0,
                selectable: false,
                evented: false,
                opacity: 0.5,
                objectType: 'background'
            });
            canvas.add(img);
            canvas.sendToBack(img);
            canvas.renderAll();
        });
    };
    reader.readAsDataURL(file);
}

// Fechar modal com clique fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (!document.querySelector('.modal-overlay[style*="flex"]')) {
            eliminarSelecionado();
        }
    }
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});
