<?php $editing = !empty($colab); ?>

<div class="table-container">
    <div class="table-header">
        <span class="table-title"><?= $editing ? 'Editar' : 'Novo' ?> Colaborador</span>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="<?= $editing ? "/colaboradores/{$colab['id']}/atualizar" : '/colaboradores/salvar' ?>">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="nome_completo">Nome Completo *</label>
                    <input type="text" id="nome_completo" name="nome_completo" class="form-control" required
                           value="<?= htmlspecialchars($colab['nome_completo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" name="cpf" class="form-control" data-mask="cpf"
                           value="<?= htmlspecialchars($colab['cpf_plain'] ?? '') ?>" placeholder="000.000.000-00">
                </div>

                <div class="form-group">
                    <label for="matricula">Matricula</label>
                    <input type="text" id="matricula" name="matricula" class="form-control"
                           value="<?= htmlspecialchars($colab['matricula'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="cargo">Cargo</label>
                    <input type="text" id="cargo" name="cargo" class="form-control"
                           value="<?= htmlspecialchars($colab['cargo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="função">Função</label>
                    <input type="text" id="função" name="funcao" class="form-control"
                           value="<?= htmlspecialchars($colab['funcao'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="setor">Setor</label>
                    <input type="text" id="setor" name="setor" class="form-control"
                           value="<?= htmlspecialchars($colab['setor'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="cliente_id">Cliente</label>
                    <select id="cliente_id" name="cliente_id" class="form-control">
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($colab['cliente_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['nome_fantasia'] ?? $cl['razao_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="obra_id">Obra</label>
                    <select id="obra_id" name="obra_id" class="form-control">
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($obras as $ob): ?>
                        <option value="<?= $ob['id'] ?>" <?= ($colab['obra_id'] ?? '') == $ob['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ob['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="data_admissao">Data Admissao</label>
                    <input type="date" id="data_admissao" name="data_admissao" class="form-control"
                           value="<?= $colab['data_admissao'] ?? '' ?>">
                </div>

                <?php if ($editing): ?>
                <div class="form-group">
                    <label for="data_demissao">Data Demissao</label>
                    <input type="date" id="data_demissao" name="data_demissao" class="form-control"
                           value="<?= $colab['data_demissao'] ?? '' ?>">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="data_nascimento">Data Nascimento</label>
                    <input type="date" id="data_nascimento" name="data_nascimento" class="form-control"
                           value="<?= $colab['data_nascimento'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" class="form-control"
                           value="<?= htmlspecialchars($colab['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($colab['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="ativo" <?= ($colab['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($colab['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="afastado" <?= ($colab['status'] ?? '') === 'afastado' ? 'selected' : '' ?>>Afastado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="unidade">Unidade</label>
                    <input type="text" id="unidade" name="unidade" class="form-control"
                           value="<?= htmlspecialchars($colab['unidade'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Salvar' : 'Cadastrar' ?></button>
                <a href="/colaboradores" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
