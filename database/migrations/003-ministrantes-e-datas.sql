-- ============================================
-- Migração: Ministrantes + Datas multi-dia
-- ============================================

USE sesmt_tse;

-- Tabela de ministrantes (instrutores cadastrados)
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

-- Seed ministrantes conhecidos
INSERT INTO ministrantes (nome, cargo_titulo, registro) VALUES
('Mariana Toscano Rios', 'Eng. de Segurança do Trabalho', 'CREA - 5071365203/SP'),
('Diego Costa Rodrigues', 'Engenheiro Eletricista', 'CREA - 1018746617D/GO');

-- Adicionar ministrante padrão a tipos_certificado
ALTER TABLE tipos_certificado ADD COLUMN ministrante_id INT NULL;
ALTER TABLE tipos_certificado ADD CONSTRAINT fk_tipo_cert_ministrante FOREIGN KEY (ministrante_id) REFERENCES ministrantes(id) ON DELETE SET NULL;

-- Setar Mariana como ministrante padrão de todos por enquanto
UPDATE tipos_certificado SET ministrante_id = 1;

-- Adicionar ministrante e data_realizacao_fim a certificados
ALTER TABLE certificados ADD COLUMN data_realizacao_fim DATE NULL AFTER data_realizacao;
ALTER TABLE certificados ADD COLUMN ministrante_id INT NULL;
ALTER TABLE certificados ADD CONSTRAINT fk_cert_ministrante FOREIGN KEY (ministrante_id) REFERENCES ministrantes(id) ON DELETE SET NULL;
