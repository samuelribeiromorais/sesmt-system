<div class="page-header">
    <h1>Evento <?= htmlspecialchars($evento['tipo_evento']) ?> #<?= $evento['id'] ?></h1>
    <div style="display:flex;gap:8px;">
        <a href="/esocial/<?= $evento['id'] ?>/xml" class="btn btn-outline btn-sm">Exportar XML</a>
        <a href="/esocial" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <!-- Info do Evento -->
    <div style="padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
        <h3 style="margin:0 0 16px;color:#1e293b;">Dados do Evento</h3>
        <table style="width:100%;">
            <tr>
                <td style="padding:6px 0;color:#6b7280;width:140px;">Tipo:</td>
                <td style="padding:6px 0;font-weight:600;">
                    <?php
                    $tipoLabels = [
                        'S-2210' => 'CAT - Comunicacao de Acidente de Trabalho',
                        'S-2220' => 'ASO - Monitoramento da Saude',
                        'S-2240' => 'Condicoes Ambientais - Exposicao a Agentes',
                    ];
                    echo $evento['tipo_evento'] . ' - ' . ($tipoLabels[$evento['tipo_evento']] ?? '');
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Status:</td>
                <td style="padding:6px 0;">
                    <?php
                    $statusColors = [
                        'pendente'  => '#f59e0b',
                        'enviado'   => '#3b82f6',
                        'aceito'    => '#10b981',
                        'rejeitado' => '#ef4444',
                    ];
                    $color = $statusColors[$evento['status']] ?? '#6b7280';
                    ?>
                    <span style="display:inline-block;padding:2px 10px;background:<?= $color ?>20;color:<?= $color ?>;border-radius:4px;font-weight:600;">
                        <?= strtoupper($evento['status']) ?>
                    </span>
                </td>
            </tr>
            <?php if (!empty($evento['protocolo'])): ?>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Protocolo:</td>
                <td style="padding:6px 0;font-family:monospace;"><?= htmlspecialchars($evento['protocolo']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Criado em:</td>
                <td style="padding:6px 0;"><?= date('d/m/Y H:i', strtotime($evento['criado_em'])) ?></td>
            </tr>
            <?php if (!empty($evento['enviado_em'])): ?>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Enviado em:</td>
                <td style="padding:6px 0;"><?= date('d/m/Y H:i', strtotime($evento['enviado_em'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Info do Colaborador -->
    <div style="padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
        <h3 style="margin:0 0 16px;color:#1e293b;">Colaborador</h3>
        <table style="width:100%;">
            <tr>
                <td style="padding:6px 0;color:#6b7280;width:100px;">Nome:</td>
                <td style="padding:6px 0;font-weight:600;">
                    <a href="/colaboradores/<?= $evento['colaborador_id'] ?>" style="color:#2563eb;">
                        <?= htmlspecialchars($evento['colaborador_nome']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Matricula:</td>
                <td style="padding:6px 0;"><?= htmlspecialchars($evento['matricula'] ?? '-') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Cargo:</td>
                <td style="padding:6px 0;"><?= htmlspecialchars($evento['cargo'] ?? '-') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Funcao:</td>
                <td style="padding:6px 0;"><?= htmlspecialchars($evento['funcao'] ?? '-') ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- Payload do Evento -->
<div style="margin-top:24px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
    <h3 style="margin:0 0 16px;color:#1e293b;">Dados do Evento (Payload)</h3>

    <?php
    $payload = $evento['payload_decoded'];

    function renderPayloadSection(string $title, array $data, string $color = '#1e293b'): void {
        echo "<div style='margin-bottom:16px;'>";
        echo "<h4 style='margin:0 0 8px;color:{$color};'>{$title}</h4>";
        echo "<table style='width:100%;'>";
        foreach ($data as $key => $value) {
            if (is_array($value)) continue;
            $label = ucfirst(str_replace('_', ' ', $key));
            echo "<tr>";
            echo "<td style='padding:4px 0;color:#6b7280;width:200px;'>{$label}:</td>";
            echo "<td style='padding:4px 0;'>" . htmlspecialchars($value ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }

    if (!empty($payload['colaborador'])) {
        renderPayloadSection('Vinculo do Trabalhador', $payload['colaborador'], '#475569');
    }
    if (!empty($payload['cat'])) {
        renderPayloadSection('CAT - Dados do Acidente', $payload['cat'], '#dc2626');
    }
    if (!empty($payload['aso'])) {
        renderPayloadSection('ASO - Monitoramento', $payload['aso'], '#2563eb');
    }
    if (!empty($payload['exposicao'])) {
        renderPayloadSection('Exposicao a Agentes Nocivos', $payload['exposicao'], '#d97706');
    }
    ?>
</div>

<!-- JSON bruto -->
<div style="margin-top:24px;padding:20px;background:#1e293b;border-radius:8px;">
    <h4 style="margin:0 0 12px;color:#94a3b8;">Payload JSON</h4>
    <pre style="margin:0;color:#e2e8f0;font-size:13px;overflow-x:auto;white-space:pre-wrap;"><?= htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
