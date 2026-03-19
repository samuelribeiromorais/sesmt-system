-- Migration 004: Campos adicionais no perfil do colaborador
-- data_nascimento, telefone, email

ALTER TABLE colaboradores
    ADD COLUMN data_nascimento DATE NULL AFTER cpf_hash,
    ADD COLUMN telefone VARCHAR(20) NULL AFTER data_nascimento,
    ADD COLUMN email VARCHAR(150) NULL AFTER telefone;
