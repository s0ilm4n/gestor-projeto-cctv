/**
 * Editor de Plantas CCTV — Fabric.js
 * Gestor de Projeto CCTV
 */

let canvas, ferramentaAtiva = 'select';
let objetosCamera = {};
let objetosAcesso = {};
let objetosZona = {};
let objetosCabo = {};
let objetosParede = {};
let escala = 1;
let plantaId = 0;
let projetoId = 0;
let pontosMedicao = [];
let linhaMedicao = null;
let desenhandoCabo = false;
let pontosCabo = [];
let linhaCabo = null;       // preview temporário do cabo
let gId = 0;

// Estado da ferramenta de paredes
let desenhandoParede = false;
let pontosParede = [];
let linhaParedeTemp = null;

const CORES_ZONA = {
    'baixo': 'rgba(76, 175, 80, 0.15)',
    'medio': 'rgba(255, 193, 7, 0.15)',
    'alto': 'rgba(255, 152, 0, 0.2)',
    'critico': 'rgba(244, 67, 54, 0.2)'
};

function novoId() { return ++gId; }

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

    desenharGrid();
    desenharReguas();

    if (plantData.dados_json) {
        try {
            const dados = JSON.parse(plantData.dados_json);
            if (dados.objects) {
                canvas.loadFromJSON(dados.objects, function() {
                    canvas.renderAll();
                    reconstruirReferencias();
                    desenharReguas();
                });
            }
        } catch(e) {
            console.warn('Erro ao carregar dados_json:', e);
        }
    }

    // Eventos (tudo num unico handler para evitar conflitos com zoom/pan)
    let isPanning = false, panStartX = 0, panStartY = 0, panVpt = null;

    function cenaXY(e) {
        // Calcular coordenadas CANVAS a partir do evento do rato,
        // aplicando manualmente a inversa do viewportTransform.
        // NOTA: nao usar opt.pointer porque pode ficar desfasado apos zoom.
        var vpt = canvas.viewportTransform;
        var rect = canvas.upperCanvasEl.getBoundingClientRect();
        var vpx = e.clientX - rect.left;
        var vpy = e.clientY - rect.top;
        return {
            x: (vpx - vpt[4]) / vpt[0],
            y: (vpy - vpt[5]) / vpt[3]
        };
    }

    canvas.on('mouse:down', function(opt) {
        if (!opt.e) return;
        // Pan: Alt+click ou middle button
        if (opt.e.button === 1 || (opt.e.altKey && opt.e.button === 0)) {
            isPanning = true;
            panStartX = opt.e.clientX;
            panStartY = opt.e.clientY;
            panVpt = canvas.viewportTransform.slice();
            canvas.selection = false;
            canvas.defaultCursor = 'grabbing';
            opt.e.preventDefault();
            return;
        }
        if (opt.e.button === 2) return; // ignorar right-click

        // Coordenadas de cena (corrige zoom/pan manualmente)
        var c = cenaXY(opt.e);
        if (ferramentaAtiva === 'camera') { adicionarCamera(c.x, c.y); return; }
        if (ferramentaAtiva === 'access') { adicionarAcesso(c.x, c.y); return; }
        if (ferramentaAtiva === 'wall')  { adicionarPontoParede(c.x, c.y); return; }
        if (ferramentaAtiva === 'medir') { adicionarPontoMedicao(c.x, c.y); return; }
        if (ferramentaAtiva === 'texto') { adicionarTexto(c.x, c.y); return; }
        if (ferramentaAtiva === 'cabo')  { adicionarPontoCabo(c.x, c.y); return; }
        if (ferramentaAtiva === 'select') { setTimeout(mostrarPropriedades, 100); return; }
    });

    canvas.on('mouse:move', function(opt) {
        if (!opt.e) return;
        var c = cenaXY(opt.e);
        document.getElementById('cursor-pos').textContent = Math.round(c.x) + ', ' + Math.round(c.y);

        // Pan
        if (isPanning && panVpt) {
            const vpt = canvas.viewportTransform;
            vpt[4] = panVpt[4] + (opt.e.clientX - panStartX);
            vpt[5] = panVpt[5] + (opt.e.clientY - panStartY);
            canvas.requestRenderAll();
            return;
        }

        // Previews
        if (ferramentaAtiva === 'wall' && desenhandoParede) {
            atualizarPreviewParede(c.x, c.y);
        }
        if (ferramentaAtiva === 'cabo' && desenhandoCabo) {
            atualizarPreviewCabo(c.x, c.y);
        }
    });

    canvas.on('mouse:up', function(opt) {
        if (isPanning) {
            isPanning = false;
            panVpt = null;
            canvas.selection = (ferramentaAtiva === 'select');
            canvas.defaultCursor = 'default';
        }
    });

    canvas.on('mouse:wheel', function(opt) {
        const delta = opt.e.deltaY;
        let zoom = canvas.getZoom();
        const factor = 0.999 ** delta;
        if ((zoom >= 20 && factor >= 1) || (zoom <= 0.05 && factor <= 1)) return;
        // Zoom centrado na posicao do rato em coordenadas de cena
        var c = cenaXY(opt.e);
        canvas.zoomToPoint(c, zoom * factor);
        canvas.calcOffset();
        opt.e.preventDefault();
        opt.e.stopPropagation();
        atualizarZoomDisplay();
    });

    canvas.on('selection:created', mostrarPropriedades);
    canvas.on('selection:updated', mostrarPropriedades);
    canvas.on('selection:cleared', function() {
        document.getElementById('propriedades-panel').innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem">Nenhum objeto selecionado</p>';
    });
    canvas.on('object:modified', atualizarDoriCamera);

    // Carregar dados existentes
    camerasData.forEach(c => criarObjetoCamera(c));
    acessosData.forEach(a => criarObjetoAcesso(a));
    zonasData.forEach(z => criarObjetoZona(z));
    cabosData.forEach(cb => criarObjetoCabo(cb));

    canvas.renderAll();

    // Auto-fit canvas ao ecra (com delay para layout estabilizar)
    setTimeout(zoomFit, 100);
}

// Resize handler
window.addEventListener('resize', function() {
    if (!desenhandoParede && !desenhandoCabo) {
        zoomFit();
    }
});

// ── ZOOM (funcoes globais chamadas pelos botoes) ──

function atualizarZoomDisplay() {
    const el = document.getElementById('zoom-level');
    if (el) el.textContent = Math.round(canvas.getZoom() * 100) + '%';
}

function zoomIn() {
    let zoom = canvas.getZoom();
    zoom = Math.min(zoom * 1.3, 20);
    canvas.setZoom(zoom);
    canvas.calcOffset();
    atualizarZoomDisplay();
}

function zoomOut() {
    let zoom = canvas.getZoom();
    zoom = Math.max(zoom / 1.3, 0.05);
    canvas.setZoom(zoom);
    canvas.calcOffset();
    atualizarZoomDisplay();
}

function zoomOneToOne() {
    canvas.setZoom(1);
    canvas.viewportTransform[4] = 0;
    canvas.viewportTransform[5] = 0;
    canvas.calcOffset();
    canvas.requestRenderAll();
    atualizarZoomDisplay();
}

function zoomFit() {
    const cw = canvas.width, ch = canvas.height;
    // Usar o container real (#canvas-container) em vez do wrapper interno do Fabric
    const container = document.getElementById('canvas-container');
    if (!container) return;
    const w = container.clientWidth - 20;
    const h = container.clientHeight - 20;
    if (w <= 0 || h <= 0) return;
    const sx = w / cw;
    const sy = h / ch;
    const zoom = Math.min(sx, sy, 2);
    canvas.setZoom(zoom);
    canvas.viewportTransform[4] = (w - cw * zoom) / 2;
    canvas.viewportTransform[5] = (h - ch * zoom) / 2;
    canvas.calcOffset();
    canvas.requestRenderAll();
    atualizarZoomDisplay();
}

// ── GRID ──

function desenharGrid() {
    const gs = 50;
    for (let x = 0; x < canvas.width; x += gs) {
        canvas.add(new fabric.Line([x, 0, x, canvas.height], {
            stroke: 'rgba(255,255,255,0.03)', selectable: false, evented: false
        }));
    }
    for (let y = 0; y < canvas.height; y += gs) {
        canvas.add(new fabric.Line([0, y, canvas.width, y], {
            stroke: 'rgba(255,255,255,0.03)', selectable: false, evented: false
        }));
    }
}

// ── RÉGUAS DE ESCALA (topo + lateral + canto inferior direito) ──

function desenharReguas() {
    canvas.getObjects().filter(o => o.objectType === 'scalebar').forEach(o => canvas.remove(o));
    if (!escala || escala <= 0) return;

    const addR = (obj) => { obj.objectType = 'scalebar'; obj.selectable = false; obj.evented = false; canvas.add(obj); };

    // --- Intervalo fixo de 10m (com fallback para escalas pequenas) ---
    let intervalo = 50;
    // Se 50m * escala nao couber com pelo menos 3 marcas, desce para 20m, 10m, 5m
    const marcas50m = Math.floor(canvas.width / (intervalo * escala));
    if (marcas50m < 3) intervalo = 20;
    const marcas20m = Math.floor(canvas.width / (intervalo * escala));
    if (marcas20m < 3) intervalo = 10;
    const marcas10m = Math.floor(canvas.width / (intervalo * escala));
    if (marcas10m < 3) intervalo = 5;
    const intervaloPx = intervalo * escala;

    // ─── RÉGUA HORIZONTAL (TOPO) ───
    const topY = 12;
    const rulerH = 8;
    const maxX = canvas.width;
    const startX = 20;
    for (let x = startX; x < maxX; x += intervaloPx) {
        addR(new fabric.Line([x, topY - rulerH, x, topY + rulerH], {
            stroke: 'rgba(255,255,255,0.5)', strokeWidth: 1
        }));
        const metros = Math.round(((x - startX) / intervaloPx) * intervalo);
        const lbl = metros >= 1000 ? (metros / 1000) + 'km' : metros + 'm';
        addR(new fabric.Text(lbl, {
            fontSize: 8, fontFamily: 'monospace', fill: 'rgba(255,255,255,0.6)',
            left: x - lbl.length * 3, top: topY + rulerH + 2
        }));
    }
    addR(new fabric.Line([startX, topY, maxX - 10, topY], {
        stroke: 'rgba(255,255,255,0.35)', strokeWidth: 1
    }));

    // ─── RÉGUA VERTICAL (LATERAL ESQUERDA) ───
    const leftX = 12;
    const rulerW = 8;
    const maxY = canvas.height;
    const startY = 20;
    for (let y = startY; y < maxY; y += intervaloPx) {
        addR(new fabric.Line([leftX - rulerW, y, leftX + rulerW, y], {
            stroke: 'rgba(255,255,255,0.5)', strokeWidth: 1
        }));
        const metros = Math.round(((y - startY) / intervaloPx) * intervalo);
        const lbl = metros >= 1000 ? (metros / 1000) + 'km' : metros + 'm';
        const lblW = lbl.length * 4;
        addR(new fabric.Text(lbl, {
            fontSize: 8, fontFamily: 'monospace', fill: 'rgba(255,255,255,0.6)',
            left: leftX - rulerW - lblW - 2, top: y - 5
        }));
    }
    addR(new fabric.Line([leftX, startY, leftX, maxY - 10], {
        stroke: 'rgba(255,255,255,0.35)', strokeWidth: 1
    }));

    // ─── BARRA CANTO INFERIOR DIREITO (compacta) ───
    const margin = 30;
    const bw = intervalo * escala;
    const bx = canvas.width - margin - bw;
    const by = canvas.height - margin;
    addR(new fabric.Line([bx, by, bx + bw, by], { stroke: 'rgba(255,255,255,0.6)', strokeWidth: 2 }));
    addR(new fabric.Line([bx, by - 5, bx, by + 5], { stroke: 'rgba(255,255,255,0.6)', strokeWidth: 2 }));
    addR(new fabric.Line([bx + bw, by - 5, bx + bw, by + 5], { stroke: 'rgba(255,255,255,0.6)', strokeWidth: 2 }));
    addR(new fabric.Line([bx + bw / 2, by - 3, bx + bw / 2, by + 3], { stroke: 'rgba(255,255,255,0.4)', strokeWidth: 1 }));
    addR(new fabric.Text('0', { fontSize: 10, fontFamily: 'monospace', fill: 'rgba(255,255,255,0.7)', left: bx - 8, top: by + 5 }));
    const lblBarra = intervalo >= 1000 ? (intervalo / 1000) + ' km' : intervalo + ' m';
    addR(new fabric.Text(lblBarra, { fontSize: 10, fontFamily: 'monospace', fill: 'rgba(255,255,255,0.7)', left: bx + bw - lblBarra.length * 6, top: by + 5 }));
    addR(new fabric.Text(escala.toFixed(1) + ' px/m', { fontSize: 9, fontFamily: 'monospace', fill: 'rgba(255,255,255,0.35)', left: bx, top: by - 16 }));
}

// ── SELECIONAR FERRAMENTA ──

function ativarFerramenta(tool) {
    ferramentaAtiva = tool;
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector(`[data-tool="${tool}"]`);
    if (btn) btn.classList.add('active');

    // Limpar estados
    if (tool !== 'medir') { pontosMedicao = []; if (linhaMedicao) { canvas.remove(linhaMedicao); linhaMedicao = null; } }
    if (tool !== 'cabo') { desenhandoCabo = false; pontosCabo = []; if (linhaCabo) { canvas.remove(linhaCabo); linhaCabo = null; } }
    if (tool !== 'wall') { if (desenhandoParede) cancelarParede(); }

    canvas.isDrawingMode = false;
    canvas.selection = (tool === 'select');
}

// ── PAREDES ──

function adicionarPontoParede(x, y) {
    if (!desenhandoParede) {
        desenhandoParede = true;
        pontosParede = [{x, y}];
        pinParede(x, y, 4);
        canvas.renderAll();
    } else {
        const ultimo = pontosParede[pontosParede.length - 1];
        if (Math.abs(ultimo.x - x) < 3 && Math.abs(ultimo.y - y) < 3) return;
        pontosParede.push({x, y});
        if (linhaParedeTemp) { canvas.remove(linhaParedeTemp); linhaParedeTemp = null; }

        const pid = 'parede_' + novoId();
        const seg = new fabric.Line([ultimo.x, ultimo.y, x, y], {
            stroke: '#455A64', strokeWidth: 5, strokeLineCap: 'round',
            selectable: true, evented: true, objectType: 'parede', paredeId: pid
        });
        canvas.add(seg);
        objetosParede[pid] = seg;
        pinParede(x, y, 3);
        canvas.renderAll();
    }
}

function pinParede(x, y, r) {
    canvas.add(new fabric.Circle({
        radius: r, fill: '#78909C', stroke: '#B0BEC5', strokeWidth: 1,
        left: x, top: y, originX: 'center', originY: 'center',
        evented: false, objectType: 'wall_dot'
    }));
}

function atualizarPreviewParede(mx, my) {
    if (linhaParedeTemp) canvas.remove(linhaParedeTemp);
    const ultimo = pontosParede[pontosParede.length - 1];
    if (!ultimo) return;
    linhaParedeTemp = new fabric.Line([ultimo.x, ultimo.y, mx, my], {
        stroke: 'rgba(69, 90, 100, 0.5)', strokeWidth: 5,
        strokeDashArray: [4, 4], selectable: false, evented: false, objectType: 'wall_preview'
    });
    canvas.add(linhaParedeTemp);
    canvas.renderAll();
}

function cancelarParede() {
    desenhandoParede = false;
    pontosParede = [];
    if (linhaParedeTemp) { canvas.remove(linhaParedeTemp); linhaParedeTemp = null; }
    canvas.getObjects().filter(o => o.objectType === 'wall_dot').forEach(d => canvas.remove(d));
    canvas.renderAll();
}

// ── CABOS (clique-a-clique com finalização a cada 2 cliques) ──

function adicionarPontoCabo(x, y) {
    if (!desenhandoCabo) {
        // 1º clique
        desenhandoCabo = true;
        pontosCabo = [{x, y}];
        pinCabo(x, y, 3);
        canvas.renderAll();
    } else {
        // 2º clique → cria segmento e finaliza
        const ultimo = pontosCabo[pontosCabo.length - 1];
        if (Math.abs(ultimo.x - x) < 2 && Math.abs(ultimo.y - y) < 2) return;
        pontosCabo.push({x, y});
        if (linhaCabo) { canvas.remove(linhaCabo); linhaCabo = null; }

        const pts = [ultimo.x, ultimo.y, x, y];
        const cId = 'cabo_' + novoId();
        const cabo = new fabric.Line(pts, {
            stroke: '#F57C00', strokeWidth: 2.5, strokeLineCap: 'round',
            selectable: true, evented: true, objectType: 'cabo', caboId: cId
        });
        canvas.add(cabo);
        objetosCabo[cId] = cabo;
        pinCabo(x, y, 3);
        canvas.renderAll();

        // Auto-finalizar: volta a select
        desenhandoCabo = false;
        pontosCabo = [];
    }
}

function pinCabo(x, y, r) {
    canvas.add(new fabric.Circle({
        radius: r, fill: '#F57C00', stroke: '#FFB74D', strokeWidth: 1,
        left: x, top: y, originX: 'center', originY: 'center',
        evented: false, objectType: 'cabo_dot'
    }));
}

function atualizarPreviewCabo(mx, my) {
    if (linhaCabo) canvas.remove(linhaCabo);
    const ultimo = pontosCabo[pontosCabo.length - 1];
    if (!ultimo) return;
    linhaCabo = new fabric.Line([ultimo.x, ultimo.y, mx, my], {
        stroke: 'rgba(245, 124, 0, 0.4)', strokeWidth: 2.5,
        strokeDashArray: [3, 3], selectable: false, evented: false, objectType: 'cabo_preview'
    });
    canvas.add(linhaCabo);
    canvas.renderAll();
}

function criarObjetoCabo(data) {
    try {
        const pts = JSON.parse(data.caminho_json);
        if (pts.length < 2) return;
        const flat = [];
        pts.forEach(p => flat.push(p[0], p[1]));
        const pl = new fabric.Line(flat, {
            stroke: '#F57C00', strokeWidth: 2.5, fill: null,
            objectType: 'cabo', caboId: 'cabo_' + data.id, evented: false
        });
        canvas.add(pl);
        objetosCabo['cabo_' + data.id] = pl;
    } catch(e) {}
}

// ── CÂMARAS ──

function adicionarCamera(x, y) {
    const id = 'cam_' + novoId();
    const group = criarGrupoCamera(x, y, 1920, 4.0, 5.6, 0, id);
    group.objectType = 'camera';
    group.cameraId = id;
    group.camData = {
        nome: 'Câmara ' + gId, resolucao_h: 1920, resolucao_v: 1080,
        distancia_focal_mm: 4.0, sensor_largura_mm: 5.6, objetivo_dori: 'R',
        ppm_calculado: 0, nivel_dori: null, conforme: null, equipamento_id: 0,
        destino_x: x + 100, destino_y: y, db_id: 0
    };
    canvas.add(group);
    canvas.setActiveObject(group);
    canvas.renderAll();
    setTimeout(() => { selecionarObjetoPorId(id); abrirModalCamera(id); }, 200);
}

function criarGrupoCamera(x, y, res_h, focal, sensor, angle, id) {
    const circle = new fabric.Circle({
        radius: 12, fill: '#1565C0', stroke: '#42A5F5', strokeWidth: 2,
        originX: 'center', originY: 'center', top: y, left: x, name: 'corpo_' + id
    });
    const fov = 2 * Math.atan2(sensor, 2 * focal) * 180 / Math.PI;
    const angRad = fov * Math.PI / 180 / 2;
    const cone = new fabric.Triangle({
        width: 2 * 80 * Math.tan(angRad), height: 80,
        fill: 'rgba(66, 165, 245, 0.15)', stroke: 'rgba(66, 165, 245, 0.5)',
        strokeWidth: 1, originX: 'center', originY: 'top',
        top: y, left: x, angle: angle + 180, name: 'fov_' + id, evented: false
    });
    const label = new fabric.Text('📹', {
        fontSize: 16, originX: 'center', originY: 'center',
        top: y, left: x, name: 'label_' + id, evented: false
    });
    return new fabric.Group([cone, circle, label], {
        left: x - 12, top: y - 12, objectType: 'camera', cameraId: id, hasControls: true, hasBorders: true
    });
}

function criarObjetoCamera(data) {
    const id = 'cam_' + data.id;
    if (data.id >= gId) gId = data.id + 1;
    const group = criarGrupoCamera(
        parseFloat(data.pos_x), parseFloat(data.pos_y),
        data.resolucao_h, parseFloat(data.distancia_focal_mm || 4.0),
        parseFloat(data.sensor_largura_mm || 5.6), parseFloat(data.orientacao_graus || 0), id
    );
    group.cameraId = id;
    group.camData = {
        nome: data.nome, resolucao_h: data.resolucao_h, resolucao_v: data.resolucao_v,
        distancia_focal_mm: parseFloat(data.distancia_focal_mm) || 4.0,
        sensor_largura_mm: parseFloat(data.sensor_largura_mm) || 5.6,
        objetivo_dori: data.objetivo_dori || 'R', ppm_calculado: data.ppm_calculado,
        nivel_dori: data.nivel_dori, conforme: data.conforme, equipamento_id: data.equipamento_id || 0,
        destino_x: parseFloat(data.destino_x) || (parseFloat(data.pos_x) + 100),
        destino_y: parseFloat(data.destino_y) || parseFloat(data.pos_y), db_id: data.id
    };
    group.angle = parseFloat(data.orientacao_graus) || 0;
    group.setCoords();
    canvas.add(group);
    objetosCamera[id] = group;
}

// ── ACESSOS ──

function adicionarAcesso(x, y) {
    const id = 'acc_' + novoId();
    const circle = new fabric.Circle({
        radius: 10, fill: '#F57C00', stroke: '#FFB74D', strokeWidth: 2,
        originX: 'center', originY: 'center', left: x, top: y
    });
    const label = new fabric.Text('🔐', {
        fontSize: 14, originX: 'center', originY: 'center', left: x, top: y, evented: false
    });
    const group = new fabric.Group([circle, label], {
        left: x - 10, top: y - 10, objectType: 'access', accessId: id,
        accData: { nome: 'Acesso ' + gId, tipo: 'leitor', db_id: 0 }
    });
    canvas.add(group); canvas.setActiveObject(group); canvas.renderAll();
}

function criarObjetoAcesso(data) {
    const id = 'acc_' + data.id;
    const circle = new fabric.Circle({
        radius: 10, fill: '#F57C00', stroke: '#FFB74D', strokeWidth: 2,
        originX: 'center', originY: 'center', left: parseFloat(data.pos_x), top: parseFloat(data.pos_y)
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
    canvas.add(group); objetosAcesso[id] = group;
}

// ── TEXTO ──

function adicionarTexto(x, y) {
    const text = new fabric.IText('Texto', {
        left: x, top: y, fontFamily: 'Arial', fontSize: 14, fill: '#E0E0E0', objectType: 'text'
    });
    canvas.add(text); canvas.setActiveObject(text); canvas.renderAll();
}

// ── ZONAS ──

function adicionarZona(poligono, nome, nivel, cor) {
    canvas.add(new fabric.Polygon(poligono, {
        fill: cor || CORES_ZONA[nivel] || 'rgba(76,175,80,0.15)',
        stroke: '#4CAF50', strokeWidth: 1, objectType: 'zona', selectable: true, hasControls: true
    }));
    canvas.renderAll();
}

function criarObjetoZona(data) {
    try {
        const coords = JSON.parse(data.poligono_json);
        const polygon = new fabric.Polygon(coords, {
            fill: data.cor || CORES_ZONA[data.nivel_seguranca] || 'rgba(76,175,80,0.15)',
            stroke: data.cor || '#4CAF50', strokeWidth: 1, objectType: 'zona',
            zonaId: 'zona_' + data.id, zonaData: data
        });
        canvas.add(polygon); objetosZona['zona_' + data.id] = polygon;
    } catch(e) {}
}

// ── MEDIÇÃO ──

function adicionarPontoMedicao(x, y) {
    pontosMedicao.push({x, y});
    canvas.add(new fabric.Circle({
        radius: 3, fill: '#FFB74D', left: x - 3, top: y - 3, evented: false, originX: 'center', originY: 'center'
    }));
    if (pontosMedicao.length === 2) {
        const p1 = pontosMedicao[0], p2 = pontosMedicao[1];
        const dist = Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
        const distReal = escala > 0 ? dist / escala : dist;
        canvas.add(new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
            stroke: '#FFB74D', strokeWidth: 2, strokeDashArray: [5, 5], evented: false
        }));
        canvas.add(new fabric.Text(
            dist.toFixed(0) + ' px' + (escala > 0 ? '\n' + (distReal).toFixed(2) + ' m' : ''), {
                fontSize: 11, fill: '#FFB74D', left: (p1.x + p2.x) / 2 + 5, top: (p1.y + p2.y) / 2 - 10, evented: false
            }
        ));
        canvas.renderAll();
        setTimeout(() => {
            pontosMedicao = []; ferramentaAtiva = 'select';
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        }, 2000);
    }
    canvas.renderAll();
}

// ── PROPRIEDADES ──

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
            <button class="btn btn-sm btn-primary" style="width:100%;margin-top:8px" onclick="abrirModalCamera('${obj.cameraId}')">⚙️ Configurar</button>`;
    } else if (obj.objectType === 'access') {
        panel.innerHTML = `<div class="info-row"><span class="label">🔐 Nome</span><span class="value">${obj.accData?.nome || '-'}</span></div>`;
    } else if (obj.objectType === 'zona') {
        panel.innerHTML = `<div class="info-row"><span class="label">📐 Zona</span><span class="value">${obj.zonaData?.nome || '-'}</span></div>`;
    } else if (obj.type === 'i-text') {
        panel.innerHTML = `<button class="btn btn-sm btn-primary" style="width:100%" onclick="editarTexto()">✏️ Editar Texto</button>`;
    } else if (obj.objectType === 'cabo') {
        panel.innerHTML = `<div class="info-row"><span class="label">🔌 Cabo</span><span class="value">UTP/FTP</span></div>`;
    } else if (obj.objectType === 'parede') {
        const comp = Math.sqrt(Math.pow(obj.x2 - obj.x1, 2) + Math.pow(obj.y2 - obj.y1, 2));
        const compMetros = escala > 0 ? comp / escala : 0;
        panel.innerHTML = `
            <div class="info-row"><span class="label">🧱 Parede</span><span class="value">${obj.paredeId || '-'}</span></div>
            <div class="info-row"><span class="label">Comprimento</span><span class="value">${compMetros > 0 ? compMetros.toFixed(2) + ' m' : '-'}</span></div>
            <button class="btn btn-sm btn-danger" style="width:100%;margin-top:8px" onclick="eliminarSelecionado()">🗑️ Eliminar</button>`;
    }
}

function editarTexto() {
    const obj = canvas.getActiveObject();
    if (obj && obj.type === 'i-text') obj.enterEditing();
}

// ── MODAL CÂMARA ──

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

function fecharModal(id) { document.getElementById(id).style.display = 'none'; }

function guardarCamera() {
    const id = document.getElementById('cam-object-id').value;
    const obj = objetosCamera[id] || canvas.getObjects().find(o => o.cameraId === id);
    if (!obj) return;
    const d = obj.camData;
    d.nome = document.getElementById('cam-nome').value || 'Câmara';
    d.resolucao_h = parseInt(document.getElementById('cam-res-h').value);
    d.resolucao_v = parseInt(document.getElementById('cam-res-v').value);
    d.distancia_focal_mm = parseFloat(document.getElementById('cam-focal').value);
    d.sensor_largura_mm = parseFloat(document.getElementById('cam-sensor').value);
    d.objetivo_dori = document.getElementById('cam-objetivo').value;
    d.equipamento_id = parseInt(document.getElementById('cam-equipamento').value);
    atualizarDoriCamera(); canvas.renderAll(); fecharModal('modal-camera'); mostrarPropriedades();
}

function atualizarDoriCamera() {
    canvas.getObjects().forEach(function(obj) {
        if (obj.objectType === 'camera' && obj.camData) {
            const d = obj.camData;
            const nec = { 'D': 25, 'O': 62, 'R': 125, 'I': 250 }[d.objetivo_dori] || 125;
            const fov = 2 * Math.atan2(d.sensor_largura_mm, 2 * d.distancia_focal_mm);
            const larguraCena = 2 * 50 * Math.tan(fov / 2);
            const ppm = larguraCena > 0 ? d.resolucao_h / (larguraCena / escala) : 0;
            d.ppm_calculado = ppm;
            d.nivel_dori = ppm >= 250 ? 'I' : ppm >= 125 ? 'R' : ppm >= 62 ? 'O' : ppm >= 25 ? 'D' : null;
            d.conforme = ppm >= nec;
        }
    });
}

function atualizarPreviewDori() {
    const resH = parseInt(document.getElementById('cam-res-h').value) || 1920;
    const focal = parseFloat(document.getElementById('cam-focal').value) || 4;
    const sensor = parseFloat(document.getElementById('cam-sensor').value) || 5.6;
    const objetivo = document.getElementById('cam-objetivo').value;
    const fov = 2 * Math.atan2(sensor, 2 * focal) * 180 / Math.PI;
    const larguraCena = 2 * 10 * Math.tan(fov * Math.PI / 360);
    const ppm = larguraCena > 0 ? resH / larguraCena : 0;
    const nec = { 'D': 25, 'O': 62, 'R': 125, 'I': 250 }[objetivo] || 125;
    document.getElementById('cam-dori-info').innerHTML = `
        <div class="info-row"><span class="label">FOV</span><span class="value">${fov.toFixed(1)}°</span></div>
        <div class="info-row"><span class="label">PPM (10m)</span><span class="value" style="color:${ppm >= nec ? '#81C784' : '#EF5350'}">${ppm.toFixed(0)} px/m</span></div>
        <div class="info-row"><span class="label">Conforme</span><span class="value">${ppm >= nec ? '✅ Sim' : '❌ Não'}</span></div>`;
}

document.addEventListener('input', function(e) {
    if (e.target.closest('#form-camera')) atualizarPreviewDori();
});

// ── ELIMINAR ──

function eliminarSelecionado() {
    const obj = canvas.getActiveObject();
    if (!obj || !confirm('Eliminar objeto selecionado?')) return;
    if (obj.objectType === 'parede' && obj.paredeId) delete objetosParede[obj.paredeId];
    if (obj.objectType === 'camera' && obj.cameraId) delete objetosCamera[obj.cameraId];
    if (obj.objectType === 'access' && obj.accessId) delete objetosAcesso[obj.accessId];
    if (obj.objectType === 'cabo' && obj.caboId) delete objetosCabo[obj.caboId];
    canvas.remove(obj); canvas.renderAll();
    document.getElementById('propriedades-panel').innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem">Nenhum objeto selecionado</p>';
}

function selecionarObjetoPorId(id) {
    const obj = canvas.getObjects().find(o => o.cameraId === id || o.accessId === id || o.zonaId === id || o.paredeId === id);
    if (obj) { canvas.setActiveObject(obj); canvas.renderAll(); mostrarPropriedades(); }
}

function limparCanvas() {
    if (!confirm('Tem a certeza? Isto elimina todos os objetos visuais.')) return;
    canvas.clear(); canvas.backgroundColor = '#1A1F2E';
    desenharGrid(); desenharReguas(); canvas.renderAll();
    objetosCamera = {}; objetosAcesso = {}; objetosZona = {}; objetosCabo = {}; objetosParede = {};
}

// ── GUARDAR ──

function guardarPlanta() {
    const dados = canvas.toJSON(['objectType', 'cameraId', 'accessId', 'zonaId', 'caboId', 'paredeId', 'camData', 'accData', 'zonaData']);
    fetch('ajax/guardar-planta.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            planta_id: plantaId, dados_json: JSON.stringify({ objects: dados }),
            cameras: extrairDadosCameras(), acessos: extrairDadosAcessos(),
            zonas: extrairDadosZonas(), cabos: extrairDadosCabos()
        })
    })
    .then(r => r.json())
    .then(res => { alert(res.success ? '✅ Planta guardada!' : '❌ Erro: ' + (res.error || 'desconhecido')); })
    .catch(err => alert('❌ Erro de rede: ' + err));
}

function extrairDadosCameras() {
    const cameras = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType !== 'camera' || !obj.camData) return;
        cameras.push({
            id: obj.cameraId, db_id: obj.camData.db_id, nome: obj.camData.nome,
            pos_x: Math.round(obj.left), pos_y: Math.round(obj.top), orientacao_graus: Math.round(obj.angle),
            resolucao_h: obj.camData.resolucao_h, resolucao_v: obj.camData.resolucao_v,
            distancia_focal_mm: obj.camData.distancia_focal_mm, sensor_largura_mm: obj.camData.sensor_largura_mm,
            objetivo_dori: obj.camData.objetivo_dori, ppm_calculado: obj.camData.ppm_calculado,
            nivel_dori: obj.camData.nivel_dori, conforme: obj.camData.conforme, equipamento_id: obj.camData.equipamento_id
        });
    });
    return cameras;
}

function extrairDadosAcessos() {
    const acessos = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType !== 'access' || !obj.accData) return;
        acessos.push({
            db_id: obj.accData.db_id, nome: obj.accData.nome, tipo: obj.accData.tipo,
            pos_x: Math.round(obj.left), pos_y: Math.round(obj.top)
        });
    });
    return acessos;
}

function extrairDadosZonas() {
    const zonas = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType !== 'zona' || !obj.zonaData || !obj.points) return;
        zonas.push({
            db_id: obj.zonaData.id, nome: obj.zonaData.nome, nivel_seguranca: obj.zonaData.nivel_seguranca,
            poligono_json: JSON.stringify(obj.points.map(p => [p.x, p.y])), cor: obj.fill
        });
    });
    return zonas;
}

function extrairDadosCabos() {
    const cabos = [];
    canvas.getObjects().forEach(obj => {
        if (obj.objectType !== 'cabo' || !obj.caboId) return;
        // Cabo é fabric.Line, temos x1/y1, x2/y2
        cabos.push({ caminho_json: JSON.stringify([[obj.x1, obj.y1], [obj.x2, obj.y2]]) });
    });
    return cabos;
}

function reconstruirReferencias() {
    canvas.getObjects().forEach(obj => {
        if (obj.objectType === 'camera' && obj.cameraId) objetosCamera[obj.cameraId] = obj;
        if (obj.objectType === 'access' && obj.accessId) objetosAcesso[obj.accessId] = obj;
        if (obj.objectType === 'zona' && obj.zonaId) objetosZona[obj.zonaId] = obj;
        if (obj.objectType === 'cabo' && obj.caboId) objetosCabo[obj.caboId] = obj;
        if (obj.objectType === 'parede' && obj.paredeId) objetosParede[obj.paredeId] = obj;
    });
}

// ── EXPORTAR PNG ──

function exportarPNG() {
    const link = document.createElement('a');
    link.download = 'planta_' + plantaId + '.png';
    link.href = canvas.toDataURL({ format: 'png', multiplier: 2 });
    link.click();
}

// ── IMPORTAR DXF ──

function importarDXF(input) {
    if (!input.files.length) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        fetch('ajax/importar-dxf.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'planta_id=' + plantaId + '&dxf_content=' + encodeURIComponent(e.target.result)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.objects) {
                res.objects.forEach(obj => {
                    if (obj.type === 'line') {
                        canvas.add(new fabric.Line([obj.x1, obj.y1, obj.x2, obj.y2], {
                            stroke: '#546E7A', strokeWidth: 2, selectable: false
                        }));
                    } else if (obj.type === 'circle') {
                        canvas.add(new fabric.Circle({
                            left: obj.x, top: obj.y, radius: obj.r, stroke: '#546E7A', strokeWidth: 1, fill: null
                        }));
                    }
                });
                canvas.renderAll();
                alert('✅ DXF importado com ' + res.objects.length + ' objetos');
            } else {
                alert('❌ Erro: ' + (res.error || 'desconhecido'));
            }
        });
    };
    reader.readAsText(input.files[0]);
}

// ── IMPORTAR IMAGEM ──

function colocarImagemFundo(input) {
    if (!input.files.length) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        fabric.Image.fromURL(e.target.result, function(img) {
            img.set({ left: 0, top: 0, selectable: false, evented: false, opacity: 0.5, objectType: 'background' });
            canvas.add(img); canvas.sendToBack(img); canvas.renderAll();
        });
    };
    reader.readAsDataURL(input.files[0]);
}

// ── EVENTOS GLOBAIS ──

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) e.target.style.display = 'none';
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (!document.querySelector('.modal-overlay[style*="flex"]')) eliminarSelecionado();
    }
    if (e.key === 'Escape') {
        if (ferramentaAtiva === 'wall' && desenhandoParede) cancelarParede();
        if (ferramentaAtiva === 'cabo' && desenhandoCabo) { desenhandoCabo = false; pontosCabo = []; if (linhaCabo) { canvas.remove(linhaCabo); linhaCabo = null; } canvas.getObjects().filter(o=>o.objectType==='cabo_dot').forEach(d=>canvas.remove(d)); canvas.renderAll(); }
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});

document.addEventListener('contextmenu', function(e) {
    if (e.target.closest('#plantaCanvas')) e.preventDefault();
});
