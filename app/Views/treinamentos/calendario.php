<?php
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
$primeiroDia = (int)date('w', mktime(0, 0, 0, $mes, 1, $ano));
$totalDias = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
$mesAnterior = $mes - 1; $anoAnterior = $ano;
if ($mesAnterior < 1) { $mesAnterior = 12; $anoAnterior--; }
$mesSeguinte = $mes + 1; $anoSeguinte = $ano;
if ($mesSeguinte > 12) { $mesSeguinte = 1; $anoSeguinte++; }
$hoje = (int)date('j'); $mesAtual = (int)date('n'); $anoAtual = (int)date('Y');
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Calendario de Treinamentos</h2>
    <div style="display:flex; gap:8px;">
        <a href="/treinamentos/calendario?mes=<?= $mesAnterior ?>&ano=<?= $anoAnterior ?>" class="btn btn-outline btn-sm">&laquo; Anterior</a>
        <span style="font-size:18px; font-weight:600; min-width:200px; text-align:center;"><?= $meses[$mes] ?> <?= $ano ?></span>
        <a href="/treinamentos/calendario?mes=<?= $mesSeguinte ?>&ano=<?= $anoSeguinte ?>" class="btn btn-outline btn-sm">Próximo &raquo;</a>
        <a href="/treinamentos/novo" class="btn btn-primary btn-sm">Novo Treinamento</a>
    </div>
</div>

<div class="table-container">
    <table style="table-layout:fixed;">
        <thead>
            <tr>
                <?php foreach ($diasSemana as $ds): ?>
                <th style="text-align:center; padding:8px;"><?= $ds ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
            <?php
            // Dias vazios antes do primeiro dia
            for ($i = 0; $i < $primeiroDia; $i++) {
                echo '<td style="background:var(--c-bg-alt,#f5f5f5); vertical-align:top; height:100px; padding:4px;"></td>';
            }
            $diaAtual = $primeiroDia;

            for ($dia = 1; $dia <= $totalDias; $dia++) {
                $isHoje = ($dia === $hoje && $mes === $mesAtual && $ano === $anoAtual);
                $bgStyle = $isHoje ? 'background:#e8f5e9; border:2px solid #00b279;' : '';
                $eventos = $eventosCalendario[$dia] ?? [];

                echo '<td style="vertical-align:top; height:100px; padding:4px; ' . $bgStyle . '">';
                echo '<div style="font-weight:' . ($isHoje ? '700' : '400') . '; font-size:13px; color:' . ($isHoje ? '#00b279' : '#333') . ';">' . $dia . '</div>';

                foreach ($eventos as $ev) {
                    echo '<a href="/treinamentos/' . $ev['id'] . '" style="display:block; margin-top:2px; padding:2px 4px; background:#005e4e; color:white; border-radius:3px; font-size:10px; text-decoration:none; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;" title="' . htmlspecialchars($ev['tipo_codigo'] . ' - ' . $ev['total_participantes'] . ' part.') . '">';
                    echo htmlspecialchars($ev['tipo_codigo']);
                    echo ' <span style="opacity:0.7;">(' . $ev['total_participantes'] . ')</span>';
                    echo '</a>';
                }

                echo '</td>';
                $diaAtual++;

                if ($diaAtual % 7 === 0 && $dia < $totalDias) {
                    echo '</tr><tr>';
                }
            }

            // Preencher resto da semana
            while ($diaAtual % 7 !== 0) {
                echo '<td style="background:var(--c-bg-alt,#f5f5f5); vertical-align:top; height:100px; padding:4px;"></td>';
                $diaAtual++;
            }
            ?>
            </tr>
        </tbody>
    </table>
</div>

<!-- Lista resumida do mês -->
<?php if (!empty($eventosCalendario)): ?>
<div class="table-container" style="margin-top:16px;">
    <div class="table-header"><span class="table-title">Treinamentos de <?= $meses[$mes] ?></span></div>
    <table>
        <thead><tr><th>Data</th><th>Treinamento</th><th>Ministrante</th><th style="text-align:center;">Participantes</th><th>Acoes</th></tr></thead>
        <tbody>
        <?php foreach ($eventosCalendario as $dia => $eventos): ?>
            <?php foreach ($eventos as $ev): ?>
            <tr>
                <td style="font-size:13px;"><?= str_pad($dia, 2, '0', STR_PAD_LEFT) ?>/<?= str_pad($mes, 2, '0', STR_PAD_LEFT) ?>/<?= $ano ?></td>
                <td style="font-size:13px; font-weight:600;"><?= htmlspecialchars($ev['tipo_codigo']) ?> (<?= htmlspecialchars($ev['duracao']) ?>)</td>
                <td style="font-size:13px;"><?= htmlspecialchars($ev['ministrante_nome'] ?? '-') ?></td>
                <td style="text-align:center;"><span class="badge badge-vigente"><?= $ev['total_participantes'] ?></span></td>
                <td><a href="/treinamentos/<?= $ev['id'] ?>" class="btn btn-outline btn-sm">Ver</a></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
