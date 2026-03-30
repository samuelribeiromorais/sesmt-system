<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Preview - <?= htmlspecialchars($tipoCert['codigo']) ?></title>
    <link rel="stylesheet" href="/assets/css/certificados.css?v=3">
    <style>
        body { margin: 0; background: #e8e8e8; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #005e4e; color: white; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 100; }
        .print-bar button { background: white; color: #005e4e; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .print-bar span { font-size: 13px; }
        .preview-container { margin-top: 60px; padding: 20px; display: flex; justify-content: center; }
        @media print { .print-bar { display: none !important; } .preview-container { margin-top: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
        <span>Preview: <?= htmlspecialchars($tipoCert['codigo']) ?> - <?= htmlspecialchars($tipoCert['titulo']) ?></span>
    </div>

    <div class="preview-container">
        <div id="certContainer"></div>
    </div>

    <script src="/assets/js/images-data.js"></script>
    <script src="/assets/js/certificados.js?v=3"></script>
    <script>
    // Dados de exemplo
    const colaboradorExemplo = {
        nome: 'COLABORADOR EXEMPLO DA SILVA',
        cpf: '000.000.000-00',
        função: 'Eletricista Industrial',
        cargo: 'Eletricista Industrial',
        data_admissao: '2024-01-15',
        data_emissao: new Date().toISOString().split('T')[0],
        data_realizacao: new Date().toISOString().split('T')[0],
        data_realizacao_fim: null,
    };

    const tipoCert = <?= json_encode($tipoCert, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const ministrante = <?= json_encode($instrutor ?: [
        'nome' => $tipoCert['ministrante_nome'] ?? 'Mariana Toscano Rios',
        'cargo_titulo' => $tipoCert['cargo_titulo'] ?? 'Eng. de Seguranca do Trabalho',
        'registro' => $tipoCert['registro'] ?? 'CREA - 5071365203/SP',
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Gerar certificado com dados de exemplo
    const html = gerarCertificadoHtml(colaboradorExemplo, tipoCert, ministrante);
    document.getElementById('certContainer').innerHTML = html;
    </script>
</body>
</html>
