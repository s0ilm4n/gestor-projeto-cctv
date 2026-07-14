# 📹 Gestor de Projeto CCTV

Sistema de gestão e planeamento para projetos de **videovigilância (CCTV)**, **controlo de acessos** e **segurança eletrónica**, com cálculos **DORI (EN 62676-4)** e **editor de plantas interativo**.

---

## ✨ Funcionalidades

### 📋 Gestão de Projetos
- Projetos CCTV completos (cliente, local, equipamentos, datas)
- Nível de risco global (baixo → crítico)
- Dados do gravador (DVR/NVR/HVR/Cloud), canais, armazenamento, retenção
- Conformidade legal (Lei 34/2013, RGPD, CNPD)

### 👥 Clientes
- Ficha completa (NIF, morada, contactos, contacto principal)
- Projetos associados a cada cliente

### 📹 Equipamentos
- Câmaras (domo, bullet, PTZ, fixa, multisensor, térmica)
- DVR/NVR, switches, access points, fontes, cabos
- Parâmetros técnicos: resolução, MP, lente, sensor, FOV, IR, áudio
- IP/MAC, número de série, canal DVR

### 🔬 DORI (EN 62676-4)
- Cálculos automáticos de **pixels por metro (ppm)**
- 4 níveis: **D**etecção (25), **O**bservação (62), **R**econhecimento (125), **I**dentificação (250)
- Distância focal recomendada e FOV calculados automaticamente
- Pré-visualização ao vivo no formulário
- Semáforo de conformidade (✅/❌)
- Alertas de zonas não conformes no dashboard

### 🗺️ Editor de Plantas Interativo
- **Canvas** com Fabric.js — zoom, drag & drop, grid
- **🧱 Paredes** — desenho livre diretamente no canvas
- **📹 Câmaras** — posicionamento, orientação, cone FOV ajustável
- **🔐 Controlo de Acessos** — leitores, fechaduras, biométricos, barreiras
- **📐 Zonas de Segurança** — polígonos com cor por nível de risco
- **📏 Medição** — distâncias em px e metros na planta
- **🔌 Cabos/Rede** — traçado de percursos UTP, fibra, coaxial
- **📂 Import DXF** — parsing de ficheiros AutoCAD (formato ASCII)
- **🖼️ Import Imagem** — colocar planta digital como fundo
- **🖼️ Export PNG** — exportar planta com resolução 2x
- **💾 Guardar** — estado completo do canvas na BD

### ⚙️ Configurações
- Dados da empresa (nome, NIF, morada, alvará PSP)
- SMTP para notificações por email
- Retenção padrão (dias)

### 👤 Utilizadores
- 3 níveis: Admin, Técnico, Visualizador
- Ativar/desativar contas
- Autenticação segura com CSRF

---

## 🚀 Instalação

### Requisitos
- PHP 8.0+
- MySQL 8.0+
- Apache/Nginx
- Extensões PHP: PDO, mbstring

### Passos

```bash
# 1. Criar base de dados
mysql -u root -p < config/schema.sql

# 2. Configurar acesso BD
#    Editar config/database.php com as credenciais

# 3. Colocar no servidor web
sudo cp -r gestor_cctv /var/www/html/
sudo chown -R www-data:www-data /var/www/html/gestor_cctv
sudo chmod -R 755 /var/www/html/gestor_cctv

# 4. Aceder
#    http://localhost/gestor_cctv/
#    Login: admin / admin123
```

### Docker (em breve)
```bash
docker compose up -d
```

---

## 🏗️ Arquitetura

```
gestor_cctv/
├── index.php              # Router principal
├── login.php              # Página de login
├── logout.php             # Logout
├── config/
│   ├── database.php       # Ligação à BD
│   └── schema.sql         # Schema SQL completo
├── includes/
│   ├── auth.php           # Autenticação + CSRF + validação
│   ├── header.php         # Navbar + sidebar
│   └── footer.php         # Fechamento HTML
├── pages/
│   ├── dashboard.php      # Estatísticas e alertas
│   ├── projetos.php       # CRUD projetos CCTV
│   ├── clientes.php       # CRUD clientes
│   ├── equipamentos.php   # Gestão de equipamentos
│   ├── dori.php           # Cálculos DORI EN 62676-4
│   ├── plantas.php        # Lista de plantas
│   ├── editor-planta.php  # Editor visual de plantas
│   ├── config.php         # Configurações da empresa
│   └── users.php          # Gestão de utilizadores
├── ajax/
│   ├── guardar-planta.php # Guardar estado do editor
│   └── importar-dxf.php   # Parser de ficheiros DXF
├── js/
│   └── editor-planta.js   # Editor Fabric.js
├── assets/
│   └── style.css          # Tema escuro
└── uploads/               # Anexos e documentos
```

---

## 📜 Normas e Legislação

| Norma | Descrição | Área |
|---|---|---|
| **EN 62676-4** | DORI — Deteção, Observação, Reconhecimento, Identificação | CCTV |
| **Lei 34/2013** | Segurança Privada — alvará PSP, técnico registado | Geral |
| **RGPD (UE 2016/679)** | Privacidade — sinalética, consentimento, direito acesso | Dados |
| **Delib. CNPD 61/2012** | Comunicação obrigatória de sistemas CCTV | CCTV |
| **EN 50131** | Sistemas de alarme (referência para integração) | Alarme |

---

## 🛠️ Tecnologias

- **Backend:** PHP 8.3 + MySQL 8.0
- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Editor:** Fabric.js 5.3.1 (canvas interativo)
- **Autenticação:** Session-based com CSRF tokens
- **Segurança:** Prepared statements, XSS protection, validação server-side

---

## 📄 Licença

MIT © s0ilm4n
