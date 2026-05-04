-- Migration 015: Módulo RH Reprotocolo — Fase 1 (Piloto)
-- Cria as tabelas do módulo de controle de protocolos no cliente.
-- Isolamento garantido por aplicação: escrita apenas via rh_* routes.

CREATE TABLE IF NOT EXISTS rh_protocolos (
    id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    documento_id     INT NOT NULL COMMENT 'Versão exata do doc protocolada',
    colaborador_id   INT NOT NULL COMMENT 'Denormalizado para queries rápidas',
    cliente_id       INT NOT NULL,
    obra_id          INT NULL    COMMENT 'NULL = vínculo primário do colaborador',
    tipo_documento_id INT NOT NULL COMMENT 'Denormalizado',
    status           ENUM('pendente_envio','enviado','confirmado','rejeitado')
                     NOT NULL DEFAULT 'pendente_envio',
    numero_protocolo VARCHAR(60)  NULL,
    protocolado_em   DATE         NULL COMMENT 'Data informada pelo RH',
    enviado_por      INT          NULL,
    enviado_em       DATETIME     NULL,
    confirmado_em    DATETIME     NULL,
    motivo_rejeicao  TEXT         NULL,
    observacoes      TEXT         NULL,
    sem_comprovante  TINYINT(1)   NOT NULL DEFAULT 0,
    prazo_sla        DATE         NULL,
    criado_em        DATETIME     NOT NULL DEFAULT NOW(),
    atualizado_em    DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),

    CONSTRAINT fk_rhp_documento        FOREIGN KEY (documento_id)      REFERENCES documentos(id)      ON DELETE RESTRICT,
    CONSTRAINT fk_rhp_colaborador      FOREIGN KEY (colaborador_id)    REFERENCES colaboradores(id),
    CONSTRAINT fk_rhp_cliente          FOREIGN KEY (cliente_id)        REFERENCES clientes(id),
    CONSTRAINT fk_rhp_obra             FOREIGN KEY (obra_id)           REFERENCES obras(id),
    CONSTRAINT fk_rhp_tipo_documento   FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id),
    CONSTRAINT fk_rhp_enviado_por      FOREIGN KEY (enviado_por)       REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Uma pendência por (documento, cliente)
CREATE UNIQUE INDEX IF NOT EXISTS idx_rhp_doc_cliente   ON rh_protocolos (documento_id, cliente_id);
CREATE INDEX IF NOT EXISTS idx_rhp_status_prazo         ON rh_protocolos (status, prazo_sla);
CREATE INDEX IF NOT EXISTS idx_rhp_cliente_status       ON rh_protocolos (cliente_id, status);
CREATE INDEX IF NOT EXISTS idx_rhp_colab_cliente        ON rh_protocolos (colaborador_id, cliente_id);

-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rh_protocolo_comprovantes (
    id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    protocolo_id     INT NOT NULL,
    arquivo_path     VARCHAR(255) NOT NULL,
    arquivo_nome     VARCHAR(255) NOT NULL,
    arquivo_hash     CHAR(64)     NULL,
    arquivo_tamanho  INT NOT NULL,
    enviado_por      INT NOT NULL,
    criado_em        DATETIME NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_rhpc_protocolo   FOREIGN KEY (protocolo_id) REFERENCES rh_protocolos(id) ON DELETE CASCADE,
    CONSTRAINT fk_rhpc_enviado_por FOREIGN KEY (enviado_por)  REFERENCES usuarios(id)
);

-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rh_alertas_config (
    id                              TINYINT NOT NULL DEFAULT 1,
    janela_60                       TINYINT(1) NOT NULL DEFAULT 1,
    janela_30                       TINYINT(1) NOT NULL DEFAULT 1,
    janela_15                       TINYINT(1) NOT NULL DEFAULT 1,
    janela_7                        TINYINT(1) NOT NULL DEFAULT 1,
    sla_reprotocolo_dias_uteis      TINYINT NOT NULL DEFAULT 5,
    email_digest_destinatarios      TEXT NULL,
    email_digest_horario            TIME NOT NULL DEFAULT '07:00:00',
    atualizado_por                  INT NULL,
    atualizado_em                   DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW(),

    PRIMARY KEY (id),
    CONSTRAINT fk_rhac_atualizado_por FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Linha singleton de configuração
INSERT IGNORE INTO rh_alertas_config (id) VALUES (1);
