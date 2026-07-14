-- ==============================================================
-- GESTOR DE PROJECTO CCTV — Schema Completo
-- Normas: EN 62676-4 (DORI), Lei 34/2013, RGPD, CNPD
-- ==============================================================

CREATE DATABASE IF NOT EXISTS gestor_cctv
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestor_cctv;

-- ==============================================================
-- UTILIZADORES
-- ==============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    role ENUM('admin','tecnico','visualizador') DEFAULT 'tecnico',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==============================================================
-- CLIENTES
-- ==============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    nif VARCHAR(20) UNIQUE,
    morada VARCHAR(255),
    localidade VARCHAR(100),
    codigo_postal VARCHAR(20),
    telefone VARCHAR(20),
    email VARCHAR(255),
    contato_nome VARCHAR(100),
    contato_telefone VARCHAR(20),
    contato_email VARCHAR(255),
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==============================================================
-- PROJECTOS CCTV
-- ==============================================================
CREATE TABLE IF NOT EXISTS projetos_cctv (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome_projeto VARCHAR(200) NOT NULL,
    local_instalacao VARCHAR(255),
    referencia_interna VARCHAR(50),
    estado ENUM('orçamento','planeamento','instalação','concluído','manutenção','encerrado') DEFAULT 'orçamento',
    nivel_risco_global ENUM('baixo','medio','alto','critico') DEFAULT 'medio',
    objetivo_dori_principal ENUM('D','O','R','I') DEFAULT 'R',

    -- Dados da empresa/alvará
    alvara_psp VARCHAR(50),
    tecnico_responsavel VARCHAR(100),
    tecnico_registo VARCHAR(50),

    -- Datas
    data_inicio DATE,
    data_conclusao DATE,
    data_ultima_manutencao DATE,

    -- DVR/NVR
    tipo_gravador ENUM('DVR','NVR','HVR','cloud') DEFAULT 'NVR',
    gravador_marca VARCHAR(100),
    gravador_modelo VARCHAR(100),
    num_canais INT DEFAULT 0,
    capacidade_armazenamento_gb INT DEFAULT 0,
    retencao_dias INT DEFAULT 30,

    -- Observações
    observacoes TEXT,

    -- Conformidade
    sinaletica_colocada BOOLEAN DEFAULT FALSE,
    comunicacao_cnpd BOOLEAN DEFAULT FALSE,
    conformidade_dori BOOLEAN DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==============================================================
-- EQUIPAMENTOS (câmaras, acessos, servidores, etc.)
-- ==============================================================
CREATE TABLE IF NOT EXISTS equipamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    tipo ENUM('camera','dvr','nvr','servidor','switch','access_point','fonte','cabo','outro') DEFAULT 'camera',
    subtipo VARCHAR(50),  -- ex: 'domo','bullet','ptz','fixa','multisensor'
    marca VARCHAR(100),
    modelo VARCHAR(100),
    numero_serie VARCHAR(100),
    mac_address VARCHAR(17),
    ip_address VARCHAR(15),

    -- Parâmetros CCTV
    resolucao_h INT DEFAULT 1920,
    resolucao_v INT DEFAULT 1080,
    megapixels DECIMAL(3,1) DEFAULT 2.0,
    tipo_lente ENUM('fixa','varifocal','motorizada') DEFAULT 'fixa',
    distancia_focal_mm_min DECIMAL(5,2),
    distancia_focal_mm_max DECIMAL(5,2),
    sensor_tamanho VARCHAR(20) DEFAULT '1/2.7"',
    fov_h_graus DECIMAL(5,1),
    fov_v_graus DECIMAL(5,1),
    vision_nocturna BOOLEAN DEFAULT FALSE,
    audio BOOLEAN DEFAULT FALSE,

    -- Controlo Acessos (quando tipo='leitor'/'bio'/'fechadura')
    tecnologia_acesso VARCHAR(50),  -- RFID, biometrico, codigo, mobile
    protocolo VARCHAR(50),  -- wiegand, osdp, rs485

    -- DORI
    ppm_calculado DECIMAL(10,2),
    nivel_dori ENUM('D','O','R','I') DEFAULT NULL,

    -- Estado
    localizacao VARCHAR(200),
    canal_dvr INT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (projeto_id) REFERENCES projetos_cctv(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- CHECKLIST DE CONFORMIDADE (por projeto)
-- ==============================================================
CREATE TABLE IF NOT EXISTS checklist_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    secao VARCHAR(50) NOT NULL,       -- legislacao, tecnico, rgpd, cnpd
    item_codigo VARCHAR(20) NOT NULL,
    item_descricao TEXT NOT NULL,
    verificado BOOLEAN DEFAULT FALSE,
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (projeto_id) REFERENCES projetos_cctv(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- CÁLCULOS DORI (EN 62676-4)
-- ==============================================================
CREATE TABLE IF NOT EXISTS dori_calculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    equipamento_id INT DEFAULT NULL,        -- opcional: associar a equipamento
    nome_zona VARCHAR(100) NOT NULL,
    objetivo ENUM('deteccao','observacao','reconhecimento','identificacao') NOT NULL,
    nivel_risco ENUM('baixo','medio','alto','critico') NOT NULL,

    -- Inputs do cálculo
    largura_cena_m DECIMAL(10,2) NOT NULL,
    distancia_camera_m DECIMAL(10,2) NOT NULL,
    resolucao_horizontal INT DEFAULT 1920,
    sensor_largura_mm DECIMAL(5,2) DEFAULT 5.60,

    -- Resultados calculados
    ppm_necessario INT GENERATED ALWAYS AS (
        CASE objetivo
            WHEN 'identificacao' THEN 250
            WHEN 'reconhecimento' THEN 125
            WHEN 'observacao' THEN 62
            WHEN 'deteccao' THEN 25
        END
    ) STORED,

    ppm_calculado DECIMAL(10,2) GENERATED ALWAYS AS (
        ROUND(resolucao_horizontal / largura_cena_m, 2)
    ) STORED,

    distancia_focal_recomendada_mm DECIMAL(10,2) GENERATED ALWAYS AS (
        ROUND((sensor_largura_mm * distancia_camera_m) / largura_cena_m, 2)
    ) STORED,

    fov_h_graus DECIMAL(5,1) GENERATED ALWAYS AS (
        ROUND(2 * ATAN2(sensor_largura_mm, 2 * NULLIF(distancia_camera_m, 0)) * 180 / PI(), 1)
    ) STORED,

    conforme BOOLEAN GENERATED ALWAYS AS (
        (resolucao_horizontal / largura_cena_m) >=
        CASE objetivo
            WHEN 'identificacao' THEN 250
            WHEN 'reconhecimento' THEN 125
            WHEN 'observacao' THEN 62
            WHEN 'deteccao' THEN 25
        END
    ) STORED,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (projeto_id) REFERENCES projetos_cctv(id) ON DELETE CASCADE,
    FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- PLANTAS (editor visual)
-- ==============================================================
CREATE TABLE IF NOT EXISTS plantas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) DEFAULT '',
    tipo ENUM('desenhada','dxf','imagem') DEFAULT 'desenhada',
    piso INT DEFAULT 0,                     -- número do piso (0 = rés-do-chão)
    ficheiro_original VARCHAR(255),         -- path do DXF/imagem original
    dimensao_x INT DEFAULT 2000,            -- largura canvas em px
    dimensao_y INT DEFAULT 1500,            -- altura canvas em px
    escala_px_por_metro DECIMAL(10,6) DEFAULT 1.0,
    dados_json LONGTEXT,                    -- estado completo do canvas (objetos)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (projeto_id) REFERENCES projetos_cctv(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- CÂMARAS POSICIONADAS NA PLANTA
-- ==============================================================
CREATE TABLE IF NOT EXISTS plantas_cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planta_id INT NOT NULL,
    equipamento_id INT DEFAULT NULL,
    nome VARCHAR(100) DEFAULT '',
    pos_x DECIMAL(10,2) NOT NULL,
    pos_y DECIMAL(10,2) NOT NULL,
    orientacao_graus DECIMAL(5,1) DEFAULT 0,
    fov_visivel BOOLEAN DEFAULT TRUE,

    -- Parâmetros da câmara para cálculo DORI
    resolucao_h INT DEFAULT 1920,
    resolucao_v INT DEFAULT 1080,
    sensor_largura_mm DECIMAL(5,2) DEFAULT 5.60,
    distancia_focal_mm DECIMAL(5,2) DEFAULT 4.0,

    fov_h_graus DECIMAL(5,1) GENERATED ALWAYS AS (
        ROUND(2 * ATAN2(sensor_largura_mm, 2 * NULLIF(distancia_focal_mm, 0)) * 180 / PI(), 1)
    ) STORED,

    largura_cena_alvo_m DECIMAL(10,2),      -- preenchido pelo editor
    ppm_calculado DECIMAL(10,2),
    nivel_dori ENUM('D','O','R','I') DEFAULT NULL,
    objetivo_dori ENUM('D','O','R','I') DEFAULT 'R',
    conforme BOOLEAN DEFAULT NULL,
    destino_x DECIMAL(10,2),                 -- ponto de foco na planta
    destino_y DECIMAL(10,2),
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE CASCADE,
    FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- CONTROLOS DE ACESSO NA PLANTA
-- ==============================================================
CREATE TABLE IF NOT EXISTS plantas_acessos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planta_id INT NOT NULL,
    equipamento_id INT DEFAULT NULL,
    tipo ENUM('porta','fechadura','leitor','sensor_porta','bio','intercom','barreira') NOT NULL,
    nome VARCHAR(100),
    pos_x DECIMAL(10,2) NOT NULL,
    pos_y DECIMAL(10,2) NOT NULL,
    orientacao_graus DECIMAL(5,1) DEFAULT 0,
    ligado_a_camera_id INT DEFAULT NULL,
    notas TEXT,

    FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE CASCADE,
    FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL,
    FOREIGN KEY (ligado_a_camera_id) REFERENCES plantas_cameras(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- ZONAS DE SEGURANÇA NA PLANTA
-- ==============================================================
CREATE TABLE IF NOT EXISTS plantas_zonas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planta_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('interior','exterior','perimetro','cofre','acesso_restrito') DEFAULT 'interior',
    nivel_seguranca ENUM('baixo','medio','alto','critico') DEFAULT 'medio',
    poligono_json TEXT NOT NULL,            -- coordenadas do polígono [[x1,y1],[x2,y2],...]
    cor VARCHAR(7) DEFAULT '#4CAF50',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- CABOS / REDE NA PLANTA
-- ==============================================================
CREATE TABLE IF NOT EXISTS plantas_cabos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planta_id INT NOT NULL,
    tipo ENUM('utp','ftp','coaxial','fibra','eletrico') DEFAULT 'utp',
    origem_id INT,                          -- plantas_cameras id
    destino_id INT,                         -- switch / outro
    origem_tipo ENUM('camera','acesso','switch','dvr') DEFAULT 'camera',
    caminho_json TEXT,                      -- [[x1,y1],[x2,y2],...]
    comprimento_m DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- ANEXOS (fotos, esquemas, PDFs)
-- ==============================================================
CREATE TABLE IF NOT EXISTS anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    tipo ENUM('foto','esquema','documento','pdf','planta') DEFAULT 'documento',
    nome_original VARCHAR(255),
    caminho_arquivo VARCHAR(500),
    tamanho_kb INT DEFAULT 0,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos_cctv(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- CONFIGURAÇÕES
-- ==============================================================
CREATE TABLE IF NOT EXISTS config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descricao VARCHAR(255)
) ENGINE=InnoDB;

INSERT IGNORE INTO config (chave, valor, descricao) VALUES
('app_nome', 'Gestor de Projeto CCTV', 'Nome da aplicação'),
('app_versao', '1.0.0', 'Versão'),
('empresa_nome', '', 'Nome da empresa'),
('empresa_nif', '', 'NIF da empresa'),
('empresa_morada', '', 'Morada da empresa'),
('empresa_telefone', '', 'Telefone da empresa'),
('empresa_email', '', 'Email da empresa'),
('alvara_psp', '', 'Nº Alvará PSP'),
('smtp_host', '', 'Servidor SMTP'),
('smtp_port', '587', 'Porta SMTP'),
('smtp_user', '', 'Utilizador SMTP'),
('smtp_pass', '', 'Password SMTP'),
('smtp_from', '', 'Email remetente'),
('retencao_padrao_dias', '30', 'Retenção padrão em dias (RGPD/CNPD)');

-- ==============================================================
-- ADMIN DEFAULT (password: admin123)
-- ==============================================================
INSERT IGNORE INTO users (username, password_hash, nome, role)
VALUES ('admin', '$2y$10$JS4hZsxuYZXRLggAMCmj7OF113vwRr0CXRbvddPa84K96HH5VQU5a', 'Administrador', 'admin');
