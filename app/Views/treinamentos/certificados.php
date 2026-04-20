<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificados - <?= htmlspecialchars($treinamento['tipo_codigo']) ?></title>
    <link rel="stylesheet" href="/assets/css/certificados.css">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
    <script src="/assets/js/images-data.js"></script>
    <script src="/assets/js/certificados.js"></script>
    <style>
        body { margin: 0; padding: 0; background: #f5f5f5; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #005e4e; color: white; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 100; }
        .print-bar button { background: white; color: #005e4e; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .print-bar button:hover { background: #e8e8e8; }
        .print-bar span { font-size: 14px; }
        .cert-pages { margin-top: 50px; }
        .cert-page-wrapper { page-break-after: always; margin: 10px auto; }
        @media print {
            .print-bar { display: none !important; }
            .cert-pages { margin-top: 0; }
            .cert-page-wrapper { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
        <span><?= htmlspecialchars($treinamento['tipo_codigo']) ?> - <?= count($participantesJson) ?> certificados</span>
    </div>

    <div class="cert-pages" id="cert-pages"></div>

    <script>
    const PARTICIPANTES = <?= json_encode($participantesJson) ?>;
    const TREINAMENTO = <?= json_encode([
        'data_realizacao' => $treinamento['data_realizacao'],
        'data_realizacao_fim' => $treinamento['data_realizacao_fim'],
        'data_emissao' => $treinamento['data_emissao'],
        'tipo_certificado_id' => $treinamento['tipo_certificado_id'],
        'ministrante_id' => $treinamento['ministrante_id'],
    ]) ?>;

    const TIPOS_MAP = {};
    <?php foreach ($tipos as $t): ?>
    TIPOS_MAP[<?= $t['id'] ?>] = {
        titulo: <?= json_encode($t['titulo']) ?>,
        codigo: <?= json_encode($t['codigo']) ?>,
        duracao: <?= json_encode($t['duracao']) ?>,
        validade_meses: <?= $t['validade_meses'] ?>,
        tem_anuencia: <?= $t['tem_anuencia'] ?>,
        tem_diego: <?= $t['tem_diego'] ?>,
        tem_diego_responsavel: <?= $t['tem_diego_responsavel'] ?? 0 ?>,
        conteudo_no_verso: <?= $t['conteudo_no_verso'] ?>,
        conteudo_programatico: <?= json_encode($t['conteudo_programatico'] ?? '[]') ?>,
        ministrante_id: <?= json_encode($t['ministrante_id']) ?>,
    };
    <?php endforeach; ?>

    const MINISTRANTES_MAP = {};
    <?php foreach ($ministrantes as $m): ?>
    MINISTRANTES_MAP[<?= $m['id'] ?>] = {
        id: <?= $m['id'] ?>,
        nome: <?= json_encode($m['nome']) ?>,
        cargo_titulo: <?= json_encode($m['cargo_titulo']) ?>,
        registro: <?= json_encode($m['registro'] ?? '') ?>,
    };
    <?php endforeach; ?>

    // Generate all certificates on load
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('cert-pages');
        const tipoCert = TIPOS_MAP[TREINAMENTO.tipo_certificado_id];
        const ministrante = TREINAMENTO.ministrante_id ? MINISTRANTES_MAP[TREINAMENTO.ministrante_id] : null;

        if (!tipoCert) return;

        PARTICIPANTES.forEach(p => {
            const colabData = {
                nome: p.nome, cpf: p.cpf, função: p.função, cargo: p.cargo,
                data_admissao: p.data_admissao,
                data_realizacao: TREINAMENTO.data_realizacao,
                data_realizacao_fim: TREINAMENTO.data_realizacao_fim,
                data_emissao: TREINAMENTO.data_emissao,
            };

            const html = gerarCertificadoHtml(colabData, tipoCert, ministrante);
            const wrapper = document.createElement('div');
            wrapper.className = 'cert-page-wrapper';
            wrapper.innerHTML = html;
            container.appendChild(wrapper);
        });
    });
    </script>
</body>
</html>
