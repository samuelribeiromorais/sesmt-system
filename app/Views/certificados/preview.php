<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado - <?= htmlspecialchars($cert['nome_completo'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/css/certificados.css">
    <script src="/assets/js/images-data.js"></script>
    <style>
        body { margin: 0; background: #f2f2ed; }
        .toolbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: #005e4e; color: white; padding: 12px 24px;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100;
        }
        .toolbar a, .toolbar button {
            color: white; text-decoration: none; background: rgba(255,255,255,0.2);
            border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;
            font-size: 14px;
        }
        .toolbar a:hover, .toolbar button:hover { background: rgba(255,255,255,0.3); }
        .cert-container { padding-top: 60px; }
        @media print { .toolbar { display: none !important; } .cert-container { padding-top: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span><?= htmlspecialchars($cert['nome_completo'] ?? '') ?> - <?= htmlspecialchars($cert['codigo'] ?? '') ?></span>
        <div>
            <button onclick="window.print()">Imprimir</button>
            <a href="/colaboradores/<?= $cert['colaborador_id'] ?? '' ?>">Voltar</a>
        </div>
    </div>

    <div class="cert-container" id="preview-area">
        <!-- Rendered by JS -->
    </div>

    <script src="/assets/js/certificados.js"></script>
    <script>
        const certData = <?= json_encode($cert) ?>;
        const tipoCert = {
            titulo: certData.titulo,
            codigo: certData.codigo,
            duracao: certData.duracao,
            tem_anuencia: parseInt(certData.tem_anuencia),
            tem_diego: parseInt(certData.tem_diego),
            tem_diego_responsavel: parseInt(certData.tem_diego_responsavel || 0),
            conteudo_no_verso: parseInt(certData.conteudo_no_verso),
            conteudo_programatico: certData.conteudo_programatico,
        };
        const colabData = {
            nome: certData.nome_completo,
            cpf: <?= json_encode($cpfFormatado ?? '***.***.***-**') ?>,
            função: certData.função || certData.cargo || '',
            cargo: certData.cargo || '',
            data_admissao: '',
            data_realizacao: certData.data_realizacao,
            data_emissao: certData.data_emissao,
        };
        renderPreview(colabData, tipoCert);
    </script>
</body>
</html>
