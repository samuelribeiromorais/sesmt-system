<div class="page-header">
    <h1>Importar Colaboradores</h1>
    <a href="/importar/colaboradores/template" class="btn btn-outline btn-sm">
        Baixar Template XLSX
    </a>
</div>

<div style="max-width:640px;">
    <div style="padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
        <form method="POST" action="/importar/colaboradores/preview" enctype="multipart/form-data">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-group">
                <label>Arquivo Excel (.xlsx)</label>
                <input type="file" name="arquivo" class="form-control" accept=".xlsx,.xls" required>
                <small style="color:#6b7280;">
                    O arquivo deve seguir o formato do template. A primeira linha deve conter os cabecalhos.
                </small>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Visualizar Preview</button>
                <a href="/colaboradores" class="btn btn-outline" style="margin-left:8px;">Cancelar</a>
            </div>
        </form>
    </div>

    <div style="margin-top:24px;padding:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
        <h3 style="margin:0 0 8px;color:#0369a1;">Instruções</h3>
        <ul style="margin:0;padding-left:20px;color:#475569;font-size:14px;line-height:1.8;">
            <li>Baixe o template clicando no botao acima</li>
            <li>Preencha os dados dos colaboradores (um por linha)</li>
            <li>Campos obrigatórios: <strong>nome_completo</strong></li>
            <li>O CPF deve conter apenas números (11 digitos)</li>
            <li>Datas no formato: AAAA-MM-DD (ex: 2024-01-15)</li>
            <li>Status validos: ativo, inativo, afastado, ferias</li>
            <li>cliente_id e obra_id devem ser IDs numericos existentes no sistema</li>
            <li>CPFs duplicados serao rejeitados automaticamente</li>
        </ul>
    </div>
</div>
