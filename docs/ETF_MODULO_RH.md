# Especificação Técnica e Funcional — Módulo RH (Gestão de Reprotocolo)

**Sistema:** TSESMT (Plataforma de SST da TSE Engenharia)
**Módulo:** Gestão Documental do RH — Reprotocolo em Clientes
**Versão do documento:** 1.0
**Data:** 02 de maio de 2026
**Autor:** Samuel Morais
**Aprovação pendente:** Allyff Sousa (Supervisor)

---

## 1. Resumo Executivo

A TSE Engenharia mantém ~900 colaboradores ativos prestando serviço em ~36 obras distribuídas entre 11 clientes corporativos. Cada um desses clientes opera com um portal próprio de gestão documental (SOC, GRSNet, Mandato Único etc.) onde a TSE precisa **reprotocolar manualmente** os documentos atualizados de cada colaborador integrado àquele cliente.

Hoje, esse reprotocolo é controlado por planilhas e memória dos colaboradores do RH. Com o crescimento do volume (~9.000 vínculos colaborador × cliente ativos, ~50.000 datas de vencimento simultâneas), o processo falha rotineiramente: documentos vencidos nos portais geram bloqueio de acesso de colaboradores, multas contratuais e retrabalho.

Este módulo se propõe a **eliminar a planilha** centralizando, no próprio TSESMT, o controle de "qual documento foi protocolado em qual cliente, quando, por quem, com qual número de protocolo". O diferencial é o **motor de detecção automática**: quando um documento é renovado no SESMT, o sistema identifica todos os clientes onde aquele colaborador está integrado e cria pendências de reprotocolo, com alertas configuráveis e dashboards gerenciais.

**Restrição arquitetural fundamental:** o módulo é construído **dentro** do TSESMT (mesmo banco, mesmo frontend, login único), mas tem **isolamento de aplicação**: o RH nunca consegue, pela UI ou rotas do sistema, alterar qualquer dado pertencente ao SESMT. Toda escrita do RH ocorre em tabelas novas com prefixo `rh_*`.

Investimento estimado: 5 sprints (~10 semanas) para o MVP completo.

---

## 2. Visão Geral do Produto

### 2.1. Objetivo

Garantir que todo documento atualizado no SESMT seja **rastreado até o protocolo no portal de cada cliente onde aquele colaborador está integrado**, eliminando perdas de prazo e bloqueios operacionais.

### 2.2. Proposta de valor

| Antes | Depois |
|---|---|
| Planilhas Excel desatualizadas | Painel único com pendências em tempo real |
| Reprotocolo lembrado por iniciativa pessoal | Pendência criada automaticamente quando documento é renovado |
| Sem visibilidade de "quantos clientes faltam" | KPI por cliente, colaborador e tipo de documento |
| Comprovante de protocolo perdido em e-mails | Comprovante anexado ao registro |
| Bloqueio descoberto pelo cliente | Alerta antes do bloqueio (60/30/15/7 dias) |

### 2.3. KPIs de sucesso (medidos 90 dias após go-live)

- **Conformidade documental nos clientes ≥ 95%**
- **Tempo médio entre renovação na TSE e protocolo no cliente ≤ 3 dias úteis**
- **Zero ocorrências de bloqueio de acesso por documento vencido em 60 dias**
- **Adoção: ≥ 90% das pendências geradas no sistema são tratadas pela tela** (não fora dele)

### 2.4. Métricas técnicas

- Dashboard RH carrega em < 3 segundos com volume completo
- Cron de detecção de pendências roda em < 5 minutos para 9.000 vínculos
- 99,5% de uptime (alinhado com o SESMT)

---

## 3. Personas e Perfis de Acesso

### 3.1. Personas

**Marcelo (RH operacional)** — opera o reprotocolo diariamente. Abre painel de pendências, baixa documento renovado do SESMT, faz upload no portal do cliente, volta no TSESMT e marca como "enviado".

**Aline (RH supervisora)** — acompanha conformidade por cliente, exporta relatórios para reuniões com o cliente, ajusta janelas de alerta.

**Mariana (SESMT)** — operadora do SESMT. Continua trabalhando exatamente como hoje. Tem visibilidade de leitura no que o RH faz (para entender contexto), mas não opera o módulo.

**Allyff (Admin)** — supervisor. Acessa tudo, configura tipos de documento exigidos por cliente, gerencia usuários.

### 3.2. Matriz de permissões

| Recurso | Admin | SESMT | RH |
|---|---|---|---|
| Tabelas SESMT (colaboradores, certificados, documentos, treinamentos, kits_pj) — leitura | ✓ | ✓ | ✓ |
| Tabelas SESMT — escrita | ✓ | ✓ | **✗ (bloqueado)** |
| `rh_vinculos_obra` — escrita | ✓ | ✗ | ✓ |
| `rh_protocolos` — escrita | ✓ | ✗ | ✓ |
| `rh_protocolo_comprovantes` — escrita | ✓ | ✗ | ✓ |
| `rh_alertas_config` — escrita | ✓ | ✗ | ✓ |
| Dashboard RH — leitura | ✓ | ✓ | ✓ |
| Dashboard SESMT — leitura | ✓ | ✓ | ✗ |
| Configuração de tipos exigidos por cliente | ✓ | ✓ | ✗ |
| Logs de auditoria | ✓ | ✗ | ✗ |

> **Crítico:** SESMT pode VER o painel RH em modo leitura para contexto, mas o RH não vê nem altera nada do SESMT além do que precisa para identificar o documento. Os indicadores do SESMT (conformidade, vencimentos) **não são afetados** pelas ações do RH.

### 3.3. 2FA

Já é obrigatório para os três perfis (admin, sesmt, rh) desde a rodada 4 de melhorias. Sem alteração.

---

## 4. Mapa de Funcionalidades

### Módulo 4.1 — Vínculos N:N

- **4.1.1.** Painel de vínculos do colaborador (`/colaboradores/{id}/vinculos`)
- **4.1.2.** Adicionar vínculo a uma obra adicional
- **4.1.3.** Encerrar vínculo (informar `ate_quando`)
- **4.1.4.** Histórico de vínculos do colaborador

> Reaproveita: tela do colaborador, dropdown de obras (já existem).
> Novo: tabela `rh_vinculos_obra`, controller `RhVinculoController`.

### Módulo 4.2 — Catálogo de exigências por cliente

- **4.2.1.** Reaproveita `config_cliente_docs` (já existe).
- **4.2.2.** Adiciona dimensão opcional `obra_id` para clientes que diferenciam exigências por site (ex.: Cargill Anápolis exige Anuência NR-33, Cargill Goiânia não).

> Reaproveita: tela `/clientes/{id}` que já tem aba Requisitos.
> Novo: campo `obra_id` em `config_cliente_docs` (nullable — null = vale para todas as obras do cliente).

### Módulo 4.3 — Motor de pendências

- **4.3.1.** Cron diário (`cron/rh_detectar_pendencias.php`) que recalcula pendências.
- **4.3.2.** Recálculo on-demand (botão "Recalcular agora" na tela do RH).
- **4.3.3.** Lógica: para cada (colaborador × cliente onde tem vínculo ativo × tipo de documento exigido pelo cliente), comparar:
  - última versão vigente do documento no SESMT,
  - último protocolo "confirmado" em `rh_protocolos`.
  - Se não há protocolo OU protocolo é mais antigo que a versão vigente → criar pendência.

> Novo: cron, services `RhPendenciaService`.

### Módulo 4.4 — Painel de pendências e workflow

- **4.4.1.** Painel `/rh` com filtros (cliente, obra, colaborador, tipo, status, prazo).
- **4.4.2.** Status de protocolo: `pendente_envio` → `enviado` → `confirmado` (ou `rejeitado` com motivo).
- **4.4.3.** Tela de marcação: anexar comprovante (PDF), digitar número de protocolo, data.
- **4.4.4.** Marcação em lote (selecionar 10 pendências do mesmo colaborador → "marcar como enviado para o cliente Cargill").
- **4.4.5.** Reabertura de pendência (caso o cliente rejeite).

> Reaproveita: tela `/rh` atual com checkbox de envio.
> Novo: substituir checkbox simples por modal completo de protocolo, tabela `rh_protocolos`.

### Módulo 4.5 — Alertas e notificações

- **4.5.1.** E-mail digest diário às 7h para todos os usuários do perfil RH.
- **4.5.2.** Notificação interna na barra superior do TSESMT.
- **4.5.3.** Janelas configuráveis: 60/30/15/7 dias antes do vencimento.
- **4.5.4.** Alerta "atrasado" para pendências em `pendente_envio` há mais de 5 dias úteis.

> Reaproveita: cron de notificações já existe em `cron/enviar_emails.php`.
> Novo: tabela `rh_alertas_config`, service `RhAlertaService`, conteúdo HTML do digest.

### Módulo 4.6 — Dashboard RH

- **4.6.1.** Cards de KPI: % conformidade global, pendências de envio, atrasados, próximos do vencimento.
- **4.6.2.** Mapa de calor cliente × tipo de documento (verde/amarelo/vermelho).
- **4.6.3.** Top 10 colaboradores com mais pendências.
- **4.6.4.** Gráfico de protocolos confirmados por mês (últimos 6 meses).
- **4.6.5.** Filtros globais (cliente, obra, período).

> Substitui o painel `/rh` atual.

### Módulo 4.7 — Relatórios

- **4.7.1.** Excel: pendências abertas por cliente.
- **4.7.2.** Excel: histórico de protocolos por colaborador.
- **4.7.3.** Excel: conformidade por obra (mensal).
- **4.7.4.** PDF: dossiê de protocolo (todos os documentos + comprovantes de um colaborador para um cliente específico).

> Reaproveita: ExportController e PhpSpreadsheet (já existem).
> Novo: nova entrada de menu, queries específicas.

### Módulo 4.8 — Configurações

- **4.8.1.** Janelas de alerta (60/30/15/7 — editáveis).
- **4.8.2.** SLA de reprotocolo (default 5 dias úteis — editável).
- **4.8.3.** E-mail destinatário do digest.

> Tela nova: `/rh/configuracoes`.

---

## 5. Especificação Funcional Detalhada

### 5.1. RF-01 — Cadastrar vínculo adicional do colaborador a uma obra

**Descrição:** o RH precisa registrar que um colaborador, embora tenha sua "obra primária" no GCO, também presta serviço pontualmente em outra obra. Esse vínculo gera necessidade de protocolar documentos no cliente da segunda obra.

**Atores:** RH, Admin.

**Pré-condição:** colaborador existe e está ativo.

**Fluxo principal:**
1. RH abre `/colaboradores/{id}` e clica em "Vínculos com obras".
2. Sistema mostra: vínculo primário (do GCO, somente leitura) + lista de vínculos adicionais.
3. RH clica "Adicionar vínculo".
4. Modal: dropdown de obra, data de início (default: hoje), função no site (texto), data de término (opcional).
5. RH confirma.
6. Sistema grava em `rh_vinculos_obra`, dispara `RhPendenciaService::recalcularColaborador($id)` para gerar pendências do novo cliente, e exibe toast "Vínculo criado. 7 pendências de protocolo geradas para o cliente Cargill."

**Regras de negócio:**
- **RN-01-01:** Não é permitido criar vínculo duplicado (mesmo colaborador × mesma obra × com `ate_quando IS NULL`). Sistema deve recusar com mensagem.
- **RN-01-02:** A data de início não pode ser anterior à `data_admissao` do colaborador.
- **RN-01-03:** A data de término, quando preenchida, não pode ser anterior à data de início.
- **RN-01-04:** Encerrar vínculo (preencher `ate_quando`) **não apaga** as pendências históricas; apenas para de gerar pendências novas a partir daquela data.

**Critérios de aceite:**
- Dado um colaborador sem vínculos adicionais, quando crio um vínculo com obra X, então aparece na lista e o contador de pendências aumenta.
- Dado um colaborador com vínculo ativo na obra X, quando tento criar outro idêntico, então sistema recusa com mensagem "Vínculo já existe".

---

### 5.2. RF-02 — Detectar pendências automaticamente após renovação de documento

**Descrição:** quando um documento é substituído no SESMT (via botão "Substituir"), o sistema identifica todos os clientes onde o colaborador está integrado, verifica se o tipo do documento é exigido por cada cliente, e cria pendências de reprotocolo.

**Atores:** sistema (gatilho automático).

**Gatilhos:**
- Substituição de documento (`DocumentoController::substituir`).
- Cron diário às 02:00.
- Botão "Recalcular agora" na tela do RH (admin).

**Algoritmo (pseudocódigo SQL):**

```sql
-- Para cada colaborador com vínculo ativo
FOR cada colaborador C COM vínculo ativo em algum cliente:
  FOR cada cliente Y onde C tem vínculo ativo:
    FOR cada tipo_documento T exigido por Y (config_cliente_docs):
      v_atual = última versão vigente do doc tipo T do colab C
      ultimo_protocolo = último rh_protocolos confirmado de (C, Y, T)

      IF v_atual existe E (ultimo_protocolo é NULL
                         OR ultimo_protocolo.documento_id != v_atual.id):
        UPSERT pendência em rh_protocolos (status='pendente_envio')
```

**Regras de negócio:**
- **RN-02-01:** Pendência só é criada se o documento atualmente vigente do colaborador é mais recente que o último protocolo confirmado.
- **RN-02-02:** Se já existe pendência aberta (`pendente_envio` ou `enviado`) para a mesma combinação, **não** duplica — atualiza a referência.
- **RN-02-03:** Documentos com `data_validade IS NULL` (ex.: Ordem de Serviço) **não** geram pendência automática (decisão do SESMT já implementada na conformidade).
- **RN-02-04:** Tipos de documento desativados (`tipos_documento.ativo = 0`) **não** geram pendência.
- **RN-02-05:** Colaboradores marcados como `isento = 1` **não** geram pendência.

**Critérios de aceite:**
- Dado colab C vinculado aos clientes A e B, ambos exigem ASO Periódico, quando substituo o ASO, então surgem 2 pendências (uma para cada cliente).
- Dado colab C com pendência aberta para cliente A, quando substituo o ASO de novo (segunda renovação), então a pendência aponta para a versão mais nova (não duplica).

---

### 5.3. RF-03 — Marcar protocolo como "enviado"

**Descrição:** RH faz o protocolo manual no portal do cliente e volta no TSESMT para registrar a ação.

**Fluxo principal:**
1. RH abre `/rh`, filtra por cliente "Cargill", vê 12 pendências de envio.
2. Para uma delas, clica "Marcar como enviado".
3. Modal abre com:
   - Documento (somente leitura): tipo, colaborador, validade.
   - Campo "Número de protocolo" (texto, opcional).
   - Campo "Data do protocolo" (data, default: hoje).
   - Upload de comprovante (PDF, JPG, PNG, máx 10 MB — opcional, mas recomendado).
   - Observações (textarea).
4. RH confirma.
5. Sistema grava em `rh_protocolos` (status `enviado`), `rh_protocolo_comprovantes`, registra log e remove a pendência da lista.

**Regras de negócio:**
- **RN-03-01:** Comprovante é opcional, mas se ausente, sistema marca o registro com flag `sem_comprovante = 1` e exibe alerta visual.
- **RN-03-02:** Data de protocolo não pode ser futura.
- **RN-03-03:** Só usuários do perfil RH ou Admin podem marcar como enviado.

**Critérios de aceite:**
- Dado uma pendência na tela, quando marco como enviado com comprovante PDF, então o registro fica visível em "Histórico" e some da lista de pendências.
- Dado um documento substituído depois de eu ter marcado como enviado, então surge nova pendência (a versão atual é mais recente).

---

### 5.4. RF-04 — Confirmar ou rejeitar protocolo

**Descrição:** após o cliente aprovar (ou rejeitar) o documento no portal, o RH atualiza o status no TSESMT.

**Fluxo:**
- "Confirmar": status `confirmado`, registra `confirmado_em`.
- "Rejeitar": status `rejeitado`, exige preenchimento de `motivo_rejeicao`. Cria nova pendência `pendente_envio` automaticamente para reprotocolo.

**Regras:**
- **RN-04-01:** Apenas registros em status `enviado` podem ser confirmados ou rejeitados.
- **RN-04-02:** Ao rejeitar, sistema preserva o registro original (histórico) e cria pendência nova vinculada à mesma versão do documento.

---

### 5.5. RF-05 — Dossiê de protocolo (PDF)

**Descrição:** gerar PDF consolidado para apresentar ao cliente em auditoria.

**Conteúdo:**
- Capa: Cliente, colaborador, período.
- Lista de documentos protocolados com: tipo, número de protocolo, data, comprovante (incorporado).
- Assinatura digital do RH responsável (a definir).

**Critérios de aceite:**
- PDF gera em < 5s para um colaborador com 15 documentos protocolados.
- Comprovantes PDF/imagem aparecem inline.

---

### 5.6. RF-06 — E-mail digest diário

**Descrição:** cron diário às 07:00 envia HTML formatado para os usuários do RH com:

- Pendências abertas no total
- Reprotocolos atrasados (>5 dias úteis em `pendente_envio`)
- Documentos vencendo em 30/15/7 dias por cliente
- Top 5 clientes com mais pendências

**Regras:**
- **RN-06-01:** Se não há nada para reportar (zero pendências, zero atrasos), e-mail **não** é enviado.
- **RN-06-02:** Janelas (60/30/15/7) lidas de `rh_alertas_config`.
- **RN-06-03:** Cada usuário do RH recebe sua cópia (não é mailing list compartilhada).

---

## 6. Modelo de Dados

> Apenas tabelas **novas** descritas. As tabelas existentes do SESMT (`colaboradores`, `clientes`, `obras`, `documentos`, `tipos_documento`, `config_cliente_docs`, `usuarios`, `logs_acesso`) são referenciadas via foreign key, não redefinidas.

### 6.1. `rh_vinculos_obra`

Vínculos adicionais do colaborador com obras (complementares ao vínculo primário do GCO em `colaboradores.obra_id`).

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| `id` | INT AI PK | | |
| `colaborador_id` | INT NOT NULL | FK `colaboradores(id)` ON DELETE CASCADE | |
| `obra_id` | INT NOT NULL | FK `obras(id)` ON DELETE RESTRICT | |
| `desde` | DATE NOT NULL | | Data de início do vínculo |
| `ate_quando` | DATE NULL | | NULL = vínculo aberto |
| `funcao_no_site` | VARCHAR(120) NULL | | Função específica neste site |
| `criado_por` | INT NOT NULL | FK `usuarios(id)` | |
| `criado_em` | DATETIME NOT NULL DEFAULT NOW() | | |
| `excluido_em` | DATETIME NULL | | Soft delete |

**Índices:**
- `UNIQUE (colaborador_id, obra_id, ate_quando)` — evita vínculo duplicado em aberto.
- `INDEX (colaborador_id, ate_quando)` — para listar vínculos ativos.

### 6.2. `rh_protocolos`

Registro de cada documento protocolado em cada cliente. **Coração do módulo.**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| `id` | INT AI PK | | |
| `documento_id` | INT NOT NULL | FK `documentos(id)` ON DELETE RESTRICT | Versão exata protocolada |
| `colaborador_id` | INT NOT NULL | FK `colaboradores(id)` | Denormalizado pra queries rápidas |
| `cliente_id` | INT NOT NULL | FK `clientes(id)` | |
| `obra_id` | INT NULL | FK `obras(id)` | Se exigência for por obra |
| `tipo_documento_id` | INT NOT NULL | FK `tipos_documento(id)` | Denormalizado |
| `status` | ENUM('pendente_envio', 'enviado', 'confirmado', 'rejeitado') NOT NULL DEFAULT 'pendente_envio' | | |
| `numero_protocolo` | VARCHAR(60) NULL | | Identificador no portal do cliente |
| `protocolado_em` | DATE NULL | | Data informada pelo RH |
| `enviado_por` | INT NULL | FK `usuarios(id)` | RH que marcou como enviado |
| `enviado_em` | DATETIME NULL | | Timestamp do registro |
| `confirmado_em` | DATETIME NULL | | |
| `motivo_rejeicao` | TEXT NULL | | Quando status = rejeitado |
| `observacoes` | TEXT NULL | | |
| `prazo_sla` | DATE NULL | | Calculado: `criado_em + N dias úteis` (do `rh_alertas_config`) |
| `criado_em` | DATETIME NOT NULL DEFAULT NOW() | | |
| `atualizado_em` | DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW() | | |

**Índices:**
- `UNIQUE (documento_id, cliente_id)` — uma pendência por (documento, cliente).
- `INDEX (status, prazo_sla)` — varredura de atrasados.
- `INDEX (cliente_id, status)` — dashboard por cliente.
- `INDEX (colaborador_id, cliente_id)` — dossiê.

### 6.3. `rh_protocolo_comprovantes`

Arquivo de comprovante anexo. Separado pra permitir múltiplos arquivos por protocolo no futuro.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | INT AI PK | |
| `protocolo_id` | INT NOT NULL | FK `rh_protocolos(id)` ON DELETE CASCADE |
| `arquivo_path` | VARCHAR(255) NOT NULL | Caminho relativo a `/storage/uploads/rh/` |
| `arquivo_nome` | VARCHAR(255) NOT NULL | Nome original do upload |
| `arquivo_hash` | CHAR(64) NULL | SHA-256 |
| `arquivo_tamanho` | INT NOT NULL | Bytes |
| `enviado_por` | INT NOT NULL | FK `usuarios(id)` |
| `criado_em` | DATETIME NOT NULL DEFAULT NOW() | |

### 6.4. `rh_alertas_config`

Configuração das janelas de alerta. Tabela de 1 linha (singleton).

| Coluna | Tipo | Default | Descrição |
|---|---|---|---|
| `id` | TINYINT PK | 1 | Sempre 1 |
| `janela_60` | TINYINT(1) NOT NULL | 1 | Alerta 60 dias antes ativo? |
| `janela_30` | TINYINT(1) NOT NULL | 1 | |
| `janela_15` | TINYINT(1) NOT NULL | 1 | |
| `janela_7` | TINYINT(1) NOT NULL | 1 | |
| `sla_reprotocolo_dias_uteis` | TINYINT NOT NULL | 5 | |
| `email_digest_destinatarios` | TEXT NULL | NULL | Lista separada por vírgula; se NULL, todos os perfis RH |
| `email_digest_horario` | TIME NOT NULL | '07:00:00' | |
| `atualizado_por` | INT NULL | | FK `usuarios(id)` |
| `atualizado_em` | DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW() | | |

### 6.5. Migration consolidada

A migration que cria essas tabelas será o arquivo `015_modulo_rh_reprotocolo.sql`, idempotente (todos os `IF NOT EXISTS`).

### 6.6. Diagrama relacional simplificado

```
                  colaboradores ──────── obras ────── clientes
                       │                    │            │
                       │ 1:N            1:N │       1:N  │
                       ▼                    ▼            ▼
                   documentos        rh_vinculos_obra   config_cliente_docs
                       │
                       │ 1:N
                       ▼
                  rh_protocolos ──────► clientes
                       │                    │
                       │ 1:N                │ 1:N
                       ▼                    │
              rh_protocolo_comprovantes     │
                                            │
                                  rh_alertas_config (singleton)
```

---

## 7. Arquitetura Técnica

### 7.1. Encaixe no monolito atual

```
app/
├── Controllers/
│   ├── (existentes do SESMT — intocados)
│   ├── RhVinculoController.php          ← NOVO
│   ├── RhProtocoloController.php        ← NOVO (substitui ações de /rh atual)
│   ├── RhDashboardController.php        ← NOVO
│   ├── RhRelatorioController.php        ← NOVO
│   └── RhConfiguracaoController.php     ← NOVO
├── Models/
│   ├── (existentes — intocados)
│   ├── RhVinculoObra.php                ← NOVO
│   ├── RhProtocolo.php                  ← NOVO
│   ├── RhProtocoloComprovante.php       ← NOVO
│   └── RhAlertasConfig.php              ← NOVO
├── Services/
│   ├── RhPendenciaService.php           ← NOVO (motor de detecção)
│   ├── RhDigestService.php              ← NOVO (geração de e-mail)
│   └── RhDossieService.php              ← NOVO (PDF do dossiê)
└── Views/
    └── rh/
        ├── dashboard.php
        ├── pendencias.php
        ├── vinculos-modal.php
        ├── protocolo-modal.php
        ├── relatorios.php
        └── configuracoes.php

cron/
└── rh_detectar_pendencias.php           ← NOVO (cron 02:00)
```

### 7.2. Stack — sem mudanças

- **PHP 8.x** (nenhum framework adicional)
- **MariaDB 10.11**
- **Apache + Docker Compose**
- **PhpSpreadsheet** já está em vendor para os exports
- **Dompdf** ou **TCPDF** para o dossiê PDF (avaliar — `[DECISÃO PENDENTE]`)

### 7.3. Padrão arquitetural

- **MVC** (já é o padrão do TSESMT)
- **Service Layer** para regras complexas (motor de pendências) — separa controller de lógica de negócio.
- **Repository implícito** via Models (já é como funciona).

### 7.4. Cron jobs

| Job | Frequência | Função |
|---|---|---|
| `rh_detectar_pendencias.php` | Diário 02:00 | Recalcula todas as pendências |
| `enviar_emails.php` (existente, estendido) | Diário 07:00 | Envia digest pra cada usuário RH |

---

## 8. Padrão de Isolamento (Interpretação B)

Esta seção descreve **como, na camada de aplicação**, garantimos que o módulo RH nunca grava em tabelas do SESMT — sem isolar no nível do banco.

### 8.1. Convenções de código

- **Controllers RH** (`Rh*Controller`) **só** instanciam Models do domínio `Rh*`.
- **Models RH** **só** acessam tabelas com prefixo `rh_*`.
- **Models do SESMT** seguem inalterados e podem ser lidos por qualquer controller.
- Nenhuma rota do RH chama método de escrita (`create`, `update`, `delete`) de Model do SESMT.

### 8.2. Middleware de autorização

Já existe `RoleMiddleware`. As rotas RH usam:

```php
$router->post('/rh/protocolos/{id}/marcar', ['RhProtocoloController', 'marcar'],
              ['AuthMiddleware', 'CsrfMiddleware', 'RhOnlyMiddleware']);
```

`RhOnlyMiddleware` (novo) checa: `$user['perfil'] IN ('rh', 'admin')`.

### 8.3. Code review checklist

Para cada PR que toca o módulo RH, revisor verifica:

- [ ] Nenhum `Documento::update()`, `Colaborador::update()`, `Cliente::update()` ou similar é chamado de controllers `Rh*`.
- [ ] Toda escrita feita pelo controller `Rh*` aponta para tabela `rh_*`.
- [ ] Logs registram a ação em `logs_acesso` com `acao` começando com `'rh_'`.

### 8.4. Teste de regressão automatizado

Adicionar ao `scripts/security_audit.py` um teste que:

1. Loga como usuário RH.
2. Tenta POST em `/colaboradores/{id}/atualizar`, `/documentos/{id}/excluir`, `/clientes/{id}/atualizar`, `/treinamentos/salvar`.
3. **Espera** HTTP 403 ou 302→`/login` em todos os casos.

Falhar este teste = bug crítico.

### 8.5. Por que NÃO isolamento de banco

**Decisão:** mesmo banco MariaDB, mesmo schema, foreign keys cruzadas permitidas.

**Justificativa:**
- Custo operacional de dois bancos seria alto (2× backups, 2× migrações).
- Foreign keys nativas garantem integridade referencial barata.
- O risco "dev escreve em tabela errada" é mitigado pelo middleware + convenção de nomes.
- Migration para banco separado pode ser feita depois se a empresa adotar microserviços (não está nos planos).

---

## 9. Estratégia de Reprotocolo Sem API

Como a TSE **não tem API** com nenhum dos portais dos clientes hoje (SOC, GRSNet, Mandato Único, Provider, Gerintel ou portais proprietários), e RPA está fora de escopo, a estratégia é **fluxo manual com checklist e comprovante**:

1. Sistema gera pendência (`rh_protocolos.status = 'pendente_envio'`).
2. RH abre o painel, identifica a pendência.
3. **Fora do TSESMT:** RH baixa o documento atualizado via "PDF" da ficha do colaborador, faz login no portal do cliente, sobe o arquivo, anota o número de protocolo (se houver).
4. **Volta ao TSESMT:** clica "Marcar como enviado", anexa o comprovante (print da tela de confirmação do portal), preenche número e data.
5. Quando o cliente aprovar (manualmente, comunicação por e-mail/portal), RH volta e clica "Confirmar".

### 9.1. Decisões pendentes

- **[DECISÃO PENDENTE]** Em uma versão futura (Fase 4 do roadmap), faz sentido construir conectores para os portais que oferecem API? Opções:
  - **(a)** Não — manter sempre manual.
  - **(b)** Sim, priorizando o portal mais usado (SOC?).
  - **(c)** Sim, via parceiro especializado em RPA (custo externo).

- **[DECISÃO PENDENTE]** O comprovante anexado é obrigatório ou opcional? Hoje proponho opcional com flag visual de risco. Validar com Allyff.

---

## 10. Requisitos Não Funcionais

### 10.1. Performance

| Operação | Meta | Como medir |
|---|---|---|
| Carregamento do dashboard RH | < 3s | Tempo de resposta HTTP no `crawler_fuzzer.py` |
| Cron de detecção de pendências | < 5min para 9.000 vínculos | Log do cron |
| Geração do dossiê PDF (15 docs) | < 5s | Profiler PHP |
| Marcação em lote de 50 pendências | < 2s | Tempo do POST |

### 10.2. Escalabilidade

Arquitetura preparada para 5.000+ colaboradores e 500+ clientes:

- Índices compostos em `rh_protocolos` (status + prazo, cliente + status, etc.).
- Paginação em todas as listagens (default 50 por página).
- Cron de pendências usa `LIMIT/OFFSET` para processar em chunks (não trava o servidor).
- Reuso da `Documento::latestSubquery()` (centralizada na rodada 4 de melhorias).

### 10.3. Segurança / LGPD

- **Em trânsito:** HTTPS já é exigido em produção.
- **Em repouso:** CPF criptografado AES-256-GCM (já existe). Nomes e dados pessoais não-sensíveis em texto plano (não há requisito legal específico).
- **Comprovantes:** salvos em `/storage/uploads/rh/` com permissão restrita (chmod 660). Não acessíveis publicamente.
- **2FA:** obrigatório para todos os perfis (já implementado).
- **LGPD:** dados de protocolo são internos. Nenhuma exportação automática para terceiros.

### 10.4. Auditoria

Toda ação de escrita gera registro em `logs_acesso`:
- `rh_protocolo_marcar_enviado`
- `rh_protocolo_confirmar`
- `rh_protocolo_rejeitar`
- `rh_vinculo_criar`
- `rh_vinculo_encerrar`
- `rh_config_atualizar`

### 10.5. Disponibilidade

99,5% de uptime (alinhado com o SESMT). Falha do módulo RH **não pode** derrubar o SESMT.

### 10.6. Backup

Alinhado com a política atual: backup diário do MariaDB completo, retenção 7 dias (já configurado no compose).

Comprovantes em `/storage/uploads/rh/` entram no `tar.gz` diário do sesmt-backup.

---

## 11. Roadmap de Implementação

### Fase 1 — Piloto operacional (1 sprint, 2 semanas)

**Entregáveis:**
- Migration `015_modulo_rh_reprotocolo.sql`
- Tabelas: `rh_protocolos`, `rh_protocolo_comprovantes`, `rh_alertas_config` (com defaults).
- Tela `/rh` reformulada: lista de pendências (cálculo on-the-fly, sem cron ainda).
- Modal de marcar protocolo (RF-03) com upload de comprovante.
- Workflow básico: `pendente_envio` → `enviado` → `confirmado`.

**Critério de "pronto":** RH consegue substituir hoje a planilha por essa tela.

**Não inclui:** vínculos N:N, motor automático, alertas por e-mail, dashboard novo, relatórios.

### Fase 2 — Vínculos N:N e motor automático (2 sprints, 4 semanas)

- Tabela `rh_vinculos_obra`.
- Tela de vínculos do colaborador (RF-01).
- `RhPendenciaService::recalcular()` + cron diário.
- Trigger automático em `DocumentoController::substituir()` chamando o service.
- Marcação em lote.

**Critério de pronto:** ao substituir um documento, surgem automaticamente as pendências para todos os clientes do colaborador.

### Fase 3 — Dashboards, alertas e relatórios (2 sprints, 4 semanas)

- Dashboard novo do RH (cards, mapa de calor, gráfico).
- E-mail digest diário.
- Relatórios Excel (3 tipos).
- Dossiê PDF.
- Tela `/rh/configuracoes`.

**Critério de pronto:** Aline (supervisora RH) consegue gerar relatório mensal por cliente sem ajuda de TI.

### Fase 4 — Integrações futuras (não programado)

- Conectores API para portais que oferecerem.
- Integração com WhatsApp (Twilio) para alertas críticos.
- App mobile somente-leitura.

### Esforço total MVP (Fases 1+2+3): **5 sprints (~10 semanas)** com 1 dev.

---

## 12. Riscos e Mitigações

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R-01 | Bug de permissão deixa RH alterar dado do SESMT | Média | Crítico | Code review obrigatório + teste automatizado de regressão (seção 8.4) + middleware `RhOnlyMiddleware`. |
| R-02 | Cron de detecção trava com 9k vínculos | Baixa | Alto | Processar em chunks de 500 + timeout configurado + log de início/fim. |
| R-03 | RH esquece de anexar comprovante | Alta | Médio | Flag visual `sem_comprovante` + alerta no dashboard + relatório mensal de "protocolos sem comprovante". |
| R-04 | Disco cheio com comprovantes acumulados | Média | Médio | Monitor `/health` já alerta < 10% livre. Política de retenção: comprovantes >2 anos arquivados em volume frio. |
| R-05 | Adoção pelo time RH demora | Média | Alto | Treinamento de 2h + manual ilustrado + acompanhamento semanal de Allyff nos primeiros 2 meses. |
| R-06 | Cliente bloqueia colaborador antes do alerta | Baixa | Crítico | Alerta de 60 dias + e-mail digest diário com top 5 clientes em risco. |
| R-07 | Migração de dados de planilha existente para o sistema | Alta | Médio | Importador one-shot (script PHP) + validação manual amostral pelo RH. Estimar 1 sprint adicional pra isso. |
| R-08 | Conflito com o vínculo primário do GCO (sobrescrita acidental) | Baixa | Alto | `colaboradores.obra_id` continua read-only para o RH. Vínculos extras só em `rh_vinculos_obra`. |
| R-09 | Performance do dashboard cai com volume | Média | Médio | Índices definidos na seção 6 + paginação + materialização de KPIs em job se necessário. |
| R-10 | Decisão pendente sobre RPA/API atrasa Fase 4 | Baixa | Baixo | MVP entrega valor sem essa decisão. Postergar discussão. |

---

## Anexos

### Anexo A — Mockup textual da tela `/rh` (Fase 1)

```
┌─────────────────────────────────────────────────────────────────┐
│ Painel RH — Pendências de Reprotocolo                          │
│                                                                 │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐               │
│ │  142    │ │   12    │ │   5     │ │   89%   │               │
│ │ Pend.   │ │Atrasados│ │Vencendo │ │Conform. │               │
│ │ envio   │ │  >5d    │ │ <30d    │ │  Geral  │               │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘               │
│                                                                 │
│ Filtros: [Cliente▼] [Obra▼] [Tipo▼] [Status▼] [Buscar______]  │
│                                                                 │
│ ☑ Selecionar todos                  [▼ Ações em lote]          │
│ ┌──┬──────────────┬─────────┬──────┬──────────┬───────────┐  │
│ │☐ │ Colaborador  │ Cliente │ Tipo │ Validade │ Ações     │  │
│ ├──┼──────────────┼─────────┼──────┼──────────┼───────────┤  │
│ │☐ │ João Silva   │ Cargill │ NR-10│ 30/06/26 │ [Marcar]  │  │
│ │☐ │ Maria Souza  │ Unilever│ ASO  │ 15/05/26 │ [Marcar]  │  │
│ │  │ ...          │         │      │          │           │  │
│ └──┴──────────────┴─────────┴──────┴──────────┴───────────┘  │
│ [Carregar mais]                          Página 1 de 7        │
└─────────────────────────────────────────────────────────────────┘
```

### Anexo B — Exemplo de payload de marcação

```json
POST /rh/protocolos/12345/marcar
Content-Type: multipart/form-data

{
  "_csrf_token": "abc123...",
  "numero_protocolo": "PROT-2026-04-987654",
  "protocolado_em": "2026-05-02",
  "observacoes": "Reprotocolado após renovação do ASO",
  "comprovante": <arquivo PDF>
}

Resposta esperada:
HTTP 200
{
  "success": true,
  "protocolo_id": 12345,
  "novo_status": "enviado",
  "comprovante_id": 5678
}
```

### Anexo C — Exemplo de regra de detecção (SQL)

```sql
-- Pendências geradas pelo motor de detecção
INSERT IGNORE INTO rh_protocolos (
    documento_id, colaborador_id, cliente_id, tipo_documento_id, status, criado_em
)
SELECT
    d.id, c.id, ccd.cliente_id, d.tipo_documento_id, 'pendente_envio', NOW()
FROM colaboradores c
JOIN (
    -- vínculo primário do GCO + vínculos adicionais do RH
    SELECT colaborador_id, obra_id FROM colaboradores
        WHERE obra_id IS NOT NULL AND status='ativo' AND excluido_em IS NULL
    UNION
    SELECT colaborador_id, obra_id FROM rh_vinculos_obra
        WHERE excluido_em IS NULL AND ate_quando IS NULL
) v ON v.colaborador_id = c.id
JOIN obras o ON v.obra_id = o.id
JOIN config_cliente_docs ccd
    ON ccd.cliente_id = o.cliente_id
    AND (ccd.obra_id IS NULL OR ccd.obra_id = o.id)
JOIN (SELECT * FROM <Documento::latestSubquery()>) d
    ON d.colaborador_id = c.id AND d.tipo_documento_id = ccd.tipo_documento_id
WHERE c.isento = 0
  AND c.excluido_em IS NULL
  AND d.status IN ('vigente', 'proximo_vencimento')
  AND NOT EXISTS (
    SELECT 1 FROM rh_protocolos p
    WHERE p.documento_id = d.id
      AND p.cliente_id = ccd.cliente_id
      AND p.status IN ('pendente_envio', 'enviado', 'confirmado')
  );
```

---

**Fim do documento.**

*Próximo passo: aprovação por Allyff Sousa, então abertura da Fase 1.*
