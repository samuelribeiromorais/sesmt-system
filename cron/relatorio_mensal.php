<?php
/**
 * Relatorio Mensal de Vencimentos
 * Gera relatorio HTML com documentos e certificados vencendo nos proximos 30 dias,
 * agrupados por cliente. Envia por email e salva em storage/relatorios/.
 *
 * Cron: 0 7 1 * * cd /var/www/html && php cron/relatorio_mensal.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\EmailService;
use App\Middleware\LoggerMiddleware;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando relatorio mensal de vencimentos...\n";

try {
    $db = Database::getInstance();

    // --- Documents expiring in next 30 days ---
    $stmt = $db->query(
        "SELECT d.id, d.data_validade, d.status,
                td.nome as tipo_nome, td.categoria,
                c.nome_completo, c.matricula,
                cl.id as cliente_id, cl.razao_social, cl.nome_fantasia
         FROM documentos d
         JOIN tipos_documento td ON d.tipo_documento_id = td.id
         JOIN colaboradores c ON d.colaborador_id = c.id
         JOIN clientes cl ON c.cliente_id = cl.id
         WHERE d.data_validade IS NOT NULL
           AND d.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND d.status != 'obsoleto'
           AND d.excluido_em IS NULL
         ORDER BY cl.nome_fantasia, d.data_validade ASC"
    );
    $docsExpiring = $stmt->fetchAll();

    // --- Certificates expiring in next 30 days ---
    $stmt = $db->query(
        "SELECT cert.id, cert.data_validade, cert.status,
                tc.codigo as tipo_nome, 'certificado' as categoria,
                c.nome_completo, c.matricula,
                cl.id as cliente_id, cl.razao_social, cl.nome_fantasia
         FROM certificados cert
         JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
         JOIN colaboradores c ON cert.colaborador_id = c.id
         JOIN clientes cl ON c.cliente_id = cl.id
         WHERE cert.data_validade IS NOT NULL
           AND cert.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY cl.nome_fantasia, cert.data_validade ASC"
    );
    $certsExpiring = $stmt->fetchAll();

    // Merge and group by client
    $allItems = array_merge($docsExpiring, $certsExpiring);
    $byClient = [];
    foreach ($allItems as $item) {
        $clienteKey = $item['cliente_id'];
        if (!isset($byClient[$clienteKey])) {
            $byClient[$clienteKey] = [
                'nome' => $item['nome_fantasia'] ?? $item['razao_social'],
                'items' => [],
            ];
        }
        $byClient[$clienteKey]['items'][] = $item;
    }

    $totalItems = count($allItems);
    $totalClientes = count($byClient);
    $dataRef = date('d/m/Y');
    $mesRef = date('m/Y');

    // --- Generate HTML ---
    $html = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head><meta charset='UTF-8'><title>Relatorio Mensal SESMT - {$mesRef}</title></head>
    <body style='font-family:Segoe UI,Arial,sans-serif;margin:0;padding:0;background:#f3f4f6;'>
    <div style='max-width:800px;margin:0 auto;background:white;'>
        <div style='background:#005e4e;color:white;padding:24px 32px;'>
            <h1 style='margin:0;font-size:20px;'>SESMT - Relatorio Mensal de Vencimentos</h1>
            <p style='margin:8px 0 0;font-size:13px;opacity:0.85;'>TSE Engenharia | Referencia: {$mesRef} | Gerado em: {$dataRef}</p>
        </div>

        <div style='padding:24px 32px;'>
            <div style='display:flex;gap:16px;margin-bottom:24px;'>
                <div style='flex:1;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;text-align:center;'>
                    <div style='font-size:28px;font-weight:bold;color:#005e4e;'>{$totalItems}</div>
                    <div style='font-size:12px;color:#6b7280;'>Itens vencendo em 30 dias</div>
                </div>
                <div style='flex:1;background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:16px;text-align:center;'>
                    <div style='font-size:28px;font-weight:bold;color:#f39c12;'>{$totalClientes}</div>
                    <div style='font-size:12px;color:#6b7280;'>Clientes afetados</div>
                </div>
            </div>";

    if (empty($byClient)) {
        $html .= "<p style='text-align:center;color:#6b7280;padding:32px 0;'>Nenhum documento ou certificado vencendo nos proximos 30 dias.</p>";
    } else {
        foreach ($byClient as $clienteId => $clienteData) {
            $clienteNome = htmlspecialchars($clienteData['nome']);
            $qtd = count($clienteData['items']);
            $html .= "
            <div style='margin-bottom:24px;'>
                <h3 style='color:#005e4e;font-size:15px;margin:0 0 8px;border-bottom:2px solid #005e4e;padding-bottom:4px;'>
                    {$clienteNome} ({$qtd} itens)
                </h3>
                <table style='width:100%;border-collapse:collapse;font-size:13px;'>
                    <thead>
                        <tr style='background:#f9fafb;'>
                            <th style='padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;'>Colaborador</th>
                            <th style='padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;'>Tipo</th>
                            <th style='padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;'>Categoria</th>
                            <th style='padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;'>Vencimento</th>
                            <th style='padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;'>Dias</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($clienteData['items'] as $item) {
                $diasRestantes = (int)((strtotime($item['data_validade']) - time()) / 86400);
                $corDias = $diasRestantes <= 7 ? '#e74c3c' : ($diasRestantes <= 15 ? '#f39c12' : '#00b279');
                $validade = date('d/m/Y', strtotime($item['data_validade']));
                $nomeColab = htmlspecialchars($item['nome_completo']);
                $tipoNome = htmlspecialchars($item['tipo_nome']);
                $cat = strtoupper(htmlspecialchars($item['categoria']));

                $html .= "
                        <tr>
                            <td style='padding:8px;border-bottom:1px solid #f3f4f6;'>{$nomeColab}</td>
                            <td style='padding:8px;border-bottom:1px solid #f3f4f6;'>{$tipoNome}</td>
                            <td style='padding:8px;border-bottom:1px solid #f3f4f6;'>{$cat}</td>
                            <td style='padding:8px;border-bottom:1px solid #f3f4f6;'>{$validade}</td>
                            <td style='padding:8px;border-bottom:1px solid #f3f4f6;color:{$corDias};font-weight:bold;'>{$diasRestantes}d</td>
                        </tr>";
            }

            $html .= "
                    </tbody>
                </table>
            </div>";
        }
    }

    $html .= "
            <p style='font-size:11px;color:#9ca3af;margin-top:32px;text-align:center;border-top:1px solid #e5e7eb;padding-top:16px;'>
                Este relatorio foi gerado automaticamente pelo Sistema SESMT - TSE Engenharia.
            </p>
        </div>
    </div>
    </body>
    </html>";

    // --- Save to storage/relatorios/ ---
    $storageDir = dirname(__DIR__) . '/storage/relatorios';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0750, true);
    }
    $fileName = 'relatorio_mensal_' . date('Y-m-d') . '.html';
    $filePath = $storageDir . '/' . $fileName;
    file_put_contents($filePath, $html);
    echo "[" . date('Y-m-d H:i:s') . "] Relatorio salvo em: {$filePath}\n";

    // --- Send email ---
    if ($totalItems > 0) {
        $emailService = new EmailService();
        $assunto = "[SESMT] Relatorio Mensal de Vencimentos - {$mesRef} ({$totalItems} itens)";

        // Use the public enviar method via the resumo approach
        $result = $emailService->enviarRelatorioMensal($assunto, $html);

        if ($result) {
            echo "[" . date('Y-m-d H:i:s') . "] Email enviado com sucesso.\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Falha ao enviar email (verifique configuracoes SMTP).\n";
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Nenhum item vencendo - email nao enviado.\n";
    }

    // --- Log execution ---
    LoggerMiddleware::log('cron', "Relatorio mensal gerado: {$totalItems} itens, {$totalClientes} clientes. Arquivo: {$fileName}");

    echo "[" . date('Y-m-d H:i:s') . "] Relatorio mensal concluido. {$totalItems} itens encontrados.\n";

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("[Cron] relatorio_mensal.php ERRO: " . $e->getMessage());
    exit(1);
}
