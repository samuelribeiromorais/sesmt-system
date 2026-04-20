-- Migration 007: NRs obrigatórias por Função + suporte ao fluxo RH

-- 1) Tabela de NRs obrigatórias por função (free-text, igual ao campo colaboradores.funcao)
CREATE TABLE IF NOT EXISTS config_funcao_certs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcao VARCHAR(100) NOT NULL,
    tipo_certificado_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_certificado_id) REFERENCES tipos_certificado(id) ON DELETE CASCADE,
    UNIQUE KEY uq_funcao_cert (funcao, tipo_certificado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Campo para rastrear se doc foi enviado pelo RH ou SESMT
ALTER TABLE documentos
    ADD COLUMN enviado_por_perfil ENUM('admin','sesmt','rh') NULL AFTER enviado_por;
