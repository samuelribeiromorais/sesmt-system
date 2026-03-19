-- ============================================
-- SESMT System - TSE Engenharia e Automacao
-- Schema MariaDB (Docker init)
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE sesmt_tse;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin','sesmt','rh') NOT NULL DEFAULT 'rh',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    tentativas_login INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultimo_login DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_perfil (perfil)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200) NULL,
    cnpj VARCHAR(18) NULL,
    contato_nome VARCHAR(150) NULL,
    contato_email VARCHAR(150) NULL,
    contato_telefone VARCHAR(20) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS obras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    local_obra VARCHAR(200) NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    status ENUM('ativa','concluida','suspensa') NOT NULL DEFAULT 'ativa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS colaboradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(200) NOT NULL,
    cpf_encrypted VARCHAR(255) NULL,
    cpf_hash VARCHAR(64) NULL UNIQUE,
    matricula VARCHAR(50) NULL,
    cargo VARCHAR(100) NULL,
    funcao VARCHAR(100) NULL,
    setor VARCHAR(100) NULL,
    cliente_id INT NULL,
    obra_id INT NULL,
    data_admissao DATE NULL,
    data_demissao DATE NULL,
    status ENUM('ativo','inativo','afastado') NOT NULL DEFAULT 'ativo',
    unidade VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE SET NULL,
    INDEX idx_nome (nome_completo),
    INDEX idx_status (status),
    INDEX idx_cliente (cliente_id),
    INDEX idx_obra (obra_id),
    INDEX idx_cpf_hash (cpf_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ministrantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    cargo_titulo VARCHAR(200) NOT NULL,
    registro VARCHAR(100) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipos_certificado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    titulo VARCHAR(500) NOT NULL,
    duracao VARCHAR(20) NOT NULL DEFAULT '8h',
    validade_meses INT NOT NULL DEFAULT 12,
    tem_anuencia TINYINT(1) NOT NULL DEFAULT 0,
    tem_diego TINYINT(1) NOT NULL DEFAULT 0,
    conteudo_no_verso TINYINT(1) NOT NULL DEFAULT 0,
    conteudo_programatico TEXT NULL,
    ministrante_id INT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ministrante_id) REFERENCES ministrantes(id) ON DELETE SET NULL,
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo_certificado_id INT NOT NULL,
    data_realizacao DATE NOT NULL,
    data_realizacao_fim DATE NULL,
    data_emissao DATE NOT NULL,
    data_validade DATE NOT NULL,
    status ENUM('vigente','vencido','proximo_vencimento') NOT NULL DEFAULT 'vigente',
    ministrante_id INT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_certificado_id) REFERENCES tipos_certificado(id) ON DELETE RESTRICT,
    FOREIGN KEY (ministrante_id) REFERENCES ministrantes(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo_certificado_id),
    INDEX idx_validade (data_validade),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipos_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    categoria ENUM('aso','epi','os','treinamento','anuencia','outro') NOT NULL,
    validade_meses INT NULL,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 1,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo_documento_id INT NOT NULL,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(500) NOT NULL,
    arquivo_hash VARCHAR(64) NOT NULL,
    arquivo_tamanho INT NOT NULL DEFAULT 0,
    data_emissao DATE NOT NULL,
    data_validade DATE NULL,
    status ENUM('vigente','vencido','proximo_vencimento','obsoleto') NOT NULL DEFAULT 'vigente',
    observacoes TEXT NULL,
    enviado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id) ON DELETE RESTRICT,
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo_documento_id),
    INDEX idx_validade (data_validade),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS config_cliente_docs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    tipo_documento_id INT NULL,
    tipo_certificado_id INT NULL,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 1,
    observacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_certificado_id) REFERENCES tipos_certificado(id) ON DELETE CASCADE,
    INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NULL,
    certificado_id INT NULL,
    colaborador_id INT NOT NULL,
    tipo ENUM('vencimento_proximo','vencido','documento_faltante') NOT NULL,
    dias_restantes INT NULL,
    notificado TINYINT(1) NOT NULL DEFAULT 0,
    notificado_em DATETIME NULL,
    email_enviado TINYINT(1) NOT NULL DEFAULT 0,
    email_enviado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (certificado_id) REFERENCES certificados(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_notificado (notificado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logs_acesso (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB;
