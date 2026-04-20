// =============================================
// SESMT TSE - Geracao de Certificados PDF
// Migrado do sistema original (index.html SPA)
// =============================================

function formatarData(dateStr) {
    if (!dateStr) return '____/____/________';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatarDataExtenso(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

function formatarRangeDatas(dataInicio, dataFim) {
    if (!dataFim || dataFim === dataInicio) {
        return formatarData(dataInicio);
    }
    const di = new Date(dataInicio + 'T00:00:00');
    const df = new Date(dataFim + 'T00:00:00');

    if (di.getFullYear() === df.getFullYear() && di.getMonth() === df.getMonth()) {
        // Mesmo mês: "16 a 20/03/2026"
        return di.getDate().toString().padStart(2, '0') + ' a ' + formatarData(dataFim);
    }
    // Meses diferentes: "28/03 a 02/04/2026"
    return formatarData(dataInicio) + ' a ' + formatarData(dataFim);
}

function gerarCertificadoHtml(colaborador, tipoCert, ministrante) {
    const c = tipoCert;
    const nome = colaborador.nome;
    const cpf = colaborador.cpf || '___.___.___-__';
    const funcao = colaborador.funcao || colaborador.cargo || '____________________';
    const admissao = formatarData(colaborador.data_admissao);
    const dataEmissao = formatarDataExtenso(colaborador.data_emissao);

    // Datas de realização (range se multi-dia)
    const datasRealizacao = formatarRangeDatas(colaborador.data_realizacao, colaborador.data_realizacao_fim);

    const conteudo = JSON.parse(c.conteudo_programatico || '[]');

    const nrMatch = c.titulo.match(/NR\s*-?\s*(\d+)/i);
    const nrNum = nrMatch ? nrMatch[1] : '';
    const nrLabel = nrNum ? 'NR ' + nrNum : c.codigo;

    // Dados do ministrante (instrutor)
    const min = ministrante || {};
    const minNome = min.nome || 'Mariana Toscano Rios';
    const minCargo = min.cargo_titulo || 'Eng. de Segurança do Trabalho';
    const minRegistro = min.registro || 'CREA - 5071365203/SP';

    // Responsável técnica (sempre Mariana)
    const respNome = 'Mariana Toscano Rios';
    const respCargo = 'Eng. de Segurança do Trabalho';
    const respRegistro = 'CREA - 5071365203/SP';

    // O ministrante é diferente da responsável?
    const ministranteSeparado = minNome !== respNome;

    // ===== ASSINATURAS =====
    let signaturesHtml = '';

    // Caso especial: NR-12 com ministrante separado (2 assinaturas distintas obrigatórias)
    const ehNr12 = (nrNum === '12');

    // Helper: gera um bloco de assinatura alinhado
    const sigBlock = (imgHtml, name, role) => `
        <div class="sig">
            <div class="sig-img-space">${imgHtml}</div>
            <div class="sig-line"></div>
            <div class="sig-name">${name}</div>
            <div class="sig-role">${role}</div>
        </div>`;

    const imgMariana = '<img src="/assets/images/assinatura_mariana.png?v=3">';
    const imgCarimbo = '<img src="/assets/images/carimbo_tse.png?v=3" class="sig-stamp">';

    const diegoCargo  = 'Engenheiro Eletricista';
    const diegoReg    = 'CREA - 1018746617D/GO';
    const diegoNome   = 'Diego Costa Rodrigues';

    if (c.tem_diego_responsavel) {
        // Mariana = Instrutora, Diego = Responsável Técnico (ex.: NR-10)
        signaturesHtml = `<div class="cert-sig-grid">
            ${sigBlock(imgMariana, respNome, `Instrutora<br>${respCargo}<br>${respRegistro}`)}
            ${sigBlock('', nome, 'Participante')}
            ${sigBlock('', diegoNome, `Responsável Técnico<br>${diegoCargo}<br>${diegoReg}`)}
            ${sigBlock(imgCarimbo, 'TSE Automação Industrial Ltda', 'CNPJ 05.149.152/0002-55')}
        </div>`;
    } else if (ehNr12 && ministranteSeparado) {
        // NR-12: 4 assinaturas - Ministrante + Participante + Responsável + Empresa
        signaturesHtml = `<div class="cert-sig-grid">
            ${sigBlock('', minNome, `Instrutor(a)<br>${minCargo}<br>${minRegistro}`)}
            ${sigBlock('', nome, 'Participante')}
            ${sigBlock(imgMariana, respNome, `Responsável Técnico<br>${respCargo}<br>${respRegistro}`)}
            ${sigBlock(imgCarimbo, 'TSE Automação Industrial Ltda', 'CNPJ 05.149.152/0002-55')}
        </div>`;
    } else if (c.tem_diego) {
        // Layout 4 assinaturas: Diego (Instrutor) + Participante + Mariana (Resp.) + TSE
        signaturesHtml = `<div class="cert-sig-grid">
            ${sigBlock('', diegoNome, `Instrutor<br>${diegoCargo}<br>${diegoReg}`)}
            ${sigBlock('', nome, 'Participante')}
            ${sigBlock(imgMariana, respNome, `Responsável Técnico<br>${respCargo}<br>${respRegistro}`)}
            ${sigBlock(imgCarimbo, 'TSE Automação Industrial Ltda', 'CNPJ 05.149.152/0002-55')}
        </div>`;
    } else if (ministranteSeparado) {
        // Ministrante diferente da responsável: 4 assinaturas
        signaturesHtml = `<div class="cert-sig-grid">
            ${sigBlock('', minNome, `Instrutor(a)<br>${minCargo}<br>${minRegistro}`)}
            ${sigBlock('', nome, 'Participante')}
            ${sigBlock(imgMariana, respNome, `Responsável Técnico<br>${respCargo}<br>${respRegistro}`)}
            ${sigBlock(imgCarimbo, 'TSE Automação Industrial Ltda', 'CNPJ 05.149.152/0002-55')}
        </div>`;
    } else {
        // Layout 3 assinaturas: Ministrante/Responsável combinado + Participante + Empresa
        const ehMariana = minNome.includes('Mariana');
        signaturesHtml = `<div class="cert-sig-grid">
            ${sigBlock(ehMariana ? imgMariana : '', minNome, `Instrutor(a) / Responsável Técnico<br>${minCargo}<br>${minRegistro}`)}
            ${sigBlock('', nome, 'Participante')}
            <div class="sig" style="grid-column: 1 / -1; max-width: 320px; margin: 0 auto;">
                <div class="sig-img-space">${imgCarimbo}</div>
                <div class="sig-line"></div>
                <div class="sig-name">TSE Automação Industrial Ltda</div>
                <div class="sig-role">CNPJ 05.149.152/0002-55</div>
            </div>
        </div>`;
    }

    // ===== CONTEUDO =====
    let conteudoHtml = '';
    if (c.conteudo_no_verso) {
        conteudoHtml = `
            <div class="cert-info">
                <p><strong>Duração (em horas):</strong> ${c.duracao}</p>
                <p><strong>Datas de realização:</strong> ${datasRealizacao}</p>
                <p>Conteúdo programático${nrNum ? ' (NR ' + nrNum + ')' : ''}, no verso.</p>
            </div>`;
    } else {
        conteudoHtml = `
            <div class="cert-info">
                <p><strong>Duração (em horas):</strong> ${c.duracao}</p>
                <p><strong>Data de realização:</strong> ${datasRealizacao}</p>
            </div>
            <div class="cert-syllabus-title"><strong>Conteúdo programático${nrNum ? ' (NR ' + nrNum + ')' : ''}:</strong></div>
            <ul class="cert-syllabus">
                ${conteudo.map(item => '<li>' + item + '</li>').join('')}
            </ul>`;
    }

    // ===== PAGINA 1: CERTIFICADO =====
    let html = `
        <div class="cert-page">
            <img class="cert-banner-top" src="${IMG_BANNER}" alt="">
            <img class="cert-banner-bottom" src="${IMG_BANNER}" alt="">
            <img class="cert-badge-right" src="${IMG_BADGE}" alt="${nrLabel}">

            <div style="text-align:center; margin-bottom: 0;">
                <img class="cert-logo-top" src="${IMG_LOGO_HD}" alt="TSE">
            </div>

            <div class="cert-title">CERTIFICADO</div>
            <div class="cert-nr">${c.titulo}</div>
            <div class="cert-nome">${nome.toUpperCase()}</div>
            <div class="cert-cpf">PORTADOR(A) DO CPF: ${cpf}</div>

            <div class="cert-body">
                <p>Participou com aproveitamento satisfatório do treinamento, de acordo com a portaria
                nº 3.214 de 08 de junho de 1978, e suas alterações, conforme conteúdo programático,
                duração e data de realização mencionados abaixo:</p>
            </div>

            ${conteudoHtml}

            <div class="cert-empresa">
                <p>Empresa: TSE Automação Industrial Ltda.</p>
                <p>Rua Amélia Rosa, Qd. Chácara, Lt. 27 - Sítio Recreio Ipê, Goiânia - GO</p>
            </div>

            <div class="cert-local">Goiânia GO, ${dataEmissao}</div>
            <div class="cert-spacer"></div>
            ${signaturesHtml}
        </div>
    `;

    // ===== PAGINA 2: CONTEUDO PROGRAMATICO (verso) =====
    if (c.conteudo_no_verso) {
        html += `
        <div class="cert-page">
            <img class="cert-banner-top" src="${IMG_BANNER}" alt="">
            <img class="cert-banner-bottom" src="${IMG_BANNER}" alt="">
            <div class="cert-conteudo-title">CONTEÚDO PROGRAMÁTICO</div>
            <div class="cert-conteudo-subtitle">${c.titulo}</div>
            <div class="cert-conteudo-cols">
                ${conteudo.map(item => '<p>' + item + '</p>').join('')}
            </div>
            <div style="margin-top: auto; text-align:center; padding-bottom: 70px;">
                <div style="font-size:10px; color:#001D21;">Empresa: TSE Automação Industrial Ltda</div>
                <div style="font-size:10px; color:#001D21;">CNPJ 05.149.152/0002-55</div>
                <img class="cert-logo-bottom" src="${IMG_LOGO_HD}" alt="TSE" style="margin-top: 8px;">
            </div>
        </div>`;
    }

    // ===== PAGINA ANUENCIA =====
    if (c.tem_anuencia) {
        // Texto e abrangência variam por NR
        let anuenciaCorpo = '';
        let anuenciaBox = '';

        if (nrNum === '10') {
            anuenciaCorpo = `<strong>CAPACITA</strong> e <strong>AUTORIZA</strong>, de acordo com os itens 10.8.3 e 10.8.4,
                    da Norma Regulamentadora nº 10, o(a) colaborador(a) supracitado(a).`;
            anuenciaBox = `<strong>A empresa autoriza o colaborador acima a executar atividades dentro das seguintes abrangências:</strong><br><br>
                    Adentrar em CCM e salas de elétrica para passagem de cabos desenergizados e montagem mecânica de leitos, bandejas e suportes.`;
        } else if (nrNum === '33') {
            anuenciaCorpo = `<strong>CAPACITA</strong> e <strong>AUTORIZA</strong>, de acordo com os itens 33.3.5 e 33.3.5.1,
                    da Norma Regulamentadora nº 33, o(a) colaborador(a) supracitado(a).`;
            anuenciaBox = `A empresa declara que o(a) colaborador(a) acima está:<br><br>
                    Apto, conforme indica seu ASO, e autoriza o colaborador a executar atividades em espaços confinados,
                    seguindo todos os procedimentos de segurança conforme foi orientado e treinado,
                    de acordo com os requisitos da NR - 33.`;
        } else if (nrNum === '35') {
            anuenciaCorpo = `<strong>CAPACITA</strong> e <strong>AUTORIZA</strong>, de acordo com os itens 35.4.1,
                    subitem 35.4.1.1, da Norma Regulamentadora nº 35, o(a) colaborador(a) supracitado(a).`;
            anuenciaBox = `A empresa declara que o(a) colaborador(a) acima está:<br><br>
                    Apto, conforme indica seu ASO, e autoriza o colaborador a executar trabalho em altura,
                    seguindo todos os procedimentos de segurança conforme foi orientado e treinado,
                    de acordo com os requisitos da NR - 35.`;
        } else {
            anuenciaCorpo = `<strong>CAPACITA</strong> e <strong>AUTORIZA</strong>, de acordo com a
                    Norma Regulamentadora nº ${nrNum}, o(a) colaborador(a) supracitado(a).`;
            anuenciaBox = `A empresa declara que o(a) colaborador(a) acima está apto(a) e autorizado(a) a executar
                    atividades conforme orientado e treinado, de acordo com os requisitos da NR - ${nrNum}.`;
        }

        // Tabela de assinaturas varia se tem Diego ou não
        let anuenciaSigRows = `<tr><td class="role-cell">Colaborador autorizado</td><td>${nome}</td></tr>`;
        if (c.tem_diego) {
            anuenciaSigRows += `<tr><td class="role-cell">Responsável técnico Habilitado</td><td>Diego Costa Rodrigues<br>Engenheiro Eletricista<br>CREA - 1018746617D/GO</td></tr>`;
        }
        anuenciaSigRows += `<tr><td class="role-cell">Setor de Segurança, Saúde e Meio Ambiente</td><td><img src="/assets/images/assinatura_mariana.png?v=2" style="max-height:60px;max-width:160px;display:block;margin-bottom:4px;">Mariana Toscano Rios<br>Engenheira Seg. Trabalho<br>CREA - 5071365203/SP</td></tr>`;
        anuenciaSigRows += `<tr><td class="role-cell">TSE Automação Industrial Ltda.</td><td><img src="/assets/images/carimbo_tse.png?v=2" style="max-height:55px;max-width:150px;display:block;margin-bottom:4px;">CNPJ 05.149.152/0002-55</td></tr>`;

        html += `
            <div class="cert-page">
                <img class="cert-banner-top" src="${IMG_BANNER}" alt="">
                <img class="cert-banner-bottom" src="${IMG_BANNER}" alt="">
                <div style="text-align:center; margin-bottom: 10px;">
                    <img style="width:60px; opacity:0.85;" src="${IMG_BADGE}" alt="">
                </div>
                <div class="anuencia-title">${nrNum ? 'NR - ' + nrNum : c.titulo.replace(/:.*/, '')}: ANUÊNCIA FORMAL</div>
                <div class="cert-nome">${nome.toUpperCase()}</div>
                <div class="cert-cpf">PORTADOR(A) DO CPF: ${cpf}</div>
                <div class="cert-body" style="margin-top: 16px;">
                    <p>A TSE Automação Industrial Ltda, estabelecida na cidade de Goiânia, na Rua Amélia Rosa,
                    Lote 27 s/n - Sítio de Recreio Ipê, inscrita no CNPJ 05.149.152/0002-55, declara que
                    ${anuenciaCorpo}</p>
                </div>
                <table class="anuencia-table">
                    <tr><td class="label-cell">Data de admissão:</td><td>${admissao}</td></tr>
                    <tr><td class="label-cell">Datas de treinamento:</td><td>${datasRealizacao}</td></tr>
                    <tr><td class="label-cell">Função:</td><td>${funcao}</td></tr>
                </table>
                <div class="anuencia-box">
                    ${anuenciaBox}
                </div>
                <table class="anuencia-sig-table">
                    ${anuenciaSigRows}
                </table>
                <div style="margin-top: auto; text-align:center; padding-bottom: 70px;">
                    <img class="cert-logo-bottom" src="${IMG_LOGO_HD}" alt="TSE">
                </div>
            </div>`;
    }

    return html;
}

// ===== PREVIEW =====
function renderPreview(colaboradorData, tipoCertData, ministrante) {
    const area = document.getElementById('preview-area');
    if (!area) return;
    area.innerHTML = gerarCertificadoHtml(colaboradorData, tipoCertData, ministrante);
}

// ===== IMPRIMIR =====
function imprimirCertificado() {
    const previewArea = document.getElementById('preview-area');
    if (!previewArea || previewArea.querySelectorAll('.cert-page').length === 0) {
        alert('Visualize um certificado primeiro.');
        return;
    }

    // Clone and add for-pdf class to all pages for compact sizing
    const clone = previewArea.cloneNode(true);
    clone.querySelectorAll('.cert-page').forEach(p => p.classList.add('for-pdf'));
    const certHtml = clone.innerHTML;

    const certCss = document.querySelector('link[href*="certificados.css"]');
    const cssHref = certCss ? certCss.href : '/assets/css/certificados.css';

    const cacheBust = '?v=' + Date.now();
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado - TSE Engenharia</title>
    <link rel="stylesheet" href="${cssHref}${cacheBust}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: white; }
        .cert-page { margin: 0 auto; }
        @media screen {
            .print-content { padding-top: 60px; }
        }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-content { padding: 0; margin: 0; }
            .cert-page {
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                overflow: hidden !important;
                page-break-after: always;
                page-break-inside: avoid;
                margin: 0 !important;
            }
            .cert-page:last-child {
                page-break-after: auto;
            }
        }
        .print-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
            background: #005e4e; color: white; padding: 10px 24px;
            display: flex; align-items: center; gap: 16px;
            font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px;
        }
        .print-bar button {
            background: #00b279; color: white; border: none;
            padding: 8px 20px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 600;
        }
        .print-bar button:hover { background: #009966; }
        .print-bar .close-btn { background: transparent; border: 1px solid rgba(255,255,255,0.4); }
        .print-bar .close-btn:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <button onclick="window.print()">Imprimir</button>
        <button class="close-btn" onclick="window.close()">Fechar</button>
        <span>Configure: Margens "Nenhuma" | Marque "Gráficos de plano de fundo" | Desmarque "Cabeçalhos e rodapés"</span>
    </div>
    <div class="print-content">${certHtml}</div>
</body>
</html>`);
    printWindow.document.close();
}

// ===== BAIXAR PDF INDIVIDUAL =====
async function baixarPdfIndividual(colaboradorData, tipoCertData, ministrante) {
    const container = document.createElement('div');
    container.style.cssText = 'position: fixed; left: -9999px; top: 0;';
    document.body.appendChild(container);

    container.innerHTML = gerarCertificadoHtml(colaboradorData, tipoCertData, ministrante);
    container.querySelectorAll('.cert-page').forEach(p => p.classList.add('for-pdf'));

    const imgs = container.querySelectorAll('img');
    await Promise.all([...imgs].map(img => {
        if (img.complete) return Promise.resolve();
        return new Promise(resolve => { img.onload = resolve; img.onerror = resolve; });
    }));

    const paginas = container.querySelectorAll('.cert-page');
    const pdf = new jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

    for (let p = 0; p < paginas.length; p++) {
        if (p > 0) pdf.addPage('a4', 'portrait');
        const canvas = await html2canvas(paginas[p], {
            scale: 2, useCORS: true, logging: false, width: 794, height: 1122
        });
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
    }

    const nomeArquivo = colaboradorData.nome + ' - ' + tipoCertData.codigo +
        (colaboradorData.data_realizacao ? ' - ' + colaboradorData.data_realizacao : '') + '.pdf';
    pdf.save(nomeArquivo);

    document.body.removeChild(container);
}

// ===== BAIXAR TODOS PDFs (ZIP) =====
async function baixarTodosPDFs(colaboradorData, certificados) {
    if (!certificados || certificados.length === 0) {
        alert('Este colaborador não possui certificados.');
        return;
    }

    const container = document.createElement('div');
    container.style.cssText = 'position: fixed; left: -9999px; top: 0;';
    document.body.appendChild(container);

    const zip = new JSZip();
    const total = certificados.length;
    let gerados = 0;

    const progressDiv = document.createElement('div');
    progressDiv.style.cssText = 'position:fixed;inset:0;background:rgba(0,30,33,0.85);display:flex;align-items:center;justify-content:center;z-index:99999;';
    progressDiv.innerHTML = `
        <div style="background:white;padding:40px 50px;border-radius:12px;text-align:center;max-width:400px;">
            <h3 style="color:#001e21;margin-bottom:16px;">Gerando PDFs...</h3>
            <p id="pdfProgressText" style="color:#555;font-size:14px;">Preparando 0 de ${total}...</p>
            <div style="background:#e0e0e0;border-radius:8px;height:12px;margin-top:16px;overflow:hidden;">
                <div id="pdfProgressBar" style="background:linear-gradient(90deg,#005e4e,#00b279);height:100%;width:0%;border-radius:8px;transition:width 0.3s;"></div>
            </div>
        </div>`;
    document.body.appendChild(progressDiv);

    for (let i = 0; i < certificados.length; i++) {
        const cert = certificados[i];
        const tipoCert = {
            titulo: cert.titulo,
            codigo: cert.codigo,
            duracao: cert.duracao,
            tem_anuencia: cert.tem_anuencia,
            tem_diego: cert.tem_diego,
            tem_diego_responsavel: cert.tem_diego_responsavel || 0,
            conteudo_no_verso: cert.conteudo_no_verso,
            conteudo_programatico: cert.conteudo_programatico,
        };

        // Ministrante do certificado salvo
        const ministrante = cert.ministrante_nome ? {
            nome: cert.ministrante_nome,
            cargo_titulo: cert.ministrante_cargo,
            registro: cert.ministrante_registro,
        } : null;

        const colabData = {
            ...colaboradorData,
            data_realizacao: cert.data_realizacao,
            data_realizacao_fim: cert.data_realizacao_fim || null,
            data_emissao: cert.data_emissao,
        };

        container.innerHTML = gerarCertificadoHtml(colabData, tipoCert, ministrante);
        container.querySelectorAll('.cert-page').forEach(p => p.classList.add('for-pdf'));

        const imgs = container.querySelectorAll('img');
        await Promise.all([...imgs].map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise(resolve => { img.onload = resolve; img.onerror = resolve; });
        }));

        const paginas = container.querySelectorAll('.cert-page');
        const pdf = new jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

        for (let p = 0; p < paginas.length; p++) {
            if (p > 0) pdf.addPage('a4', 'portrait');
            const canvas = await html2canvas(paginas[p], {
                scale: 2, useCORS: true, logging: false, width: 794, height: 1122
            });
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
        }

        const nomeArquivo = colaboradorData.nome + ' - ' + cert.codigo +
            (cert.data_realizacao ? ' - ' + cert.data_realizacao : '') + '.pdf';
        zip.file(nomeArquivo, pdf.output('blob'));

        gerados++;
        document.getElementById('pdfProgressText').textContent = 'Gerando ' + gerados + ' de ' + total + '...';
        document.getElementById('pdfProgressBar').style.width = ((gerados / total) * 100) + '%';
        await new Promise(r => setTimeout(r, 50));
    }

    document.getElementById('pdfProgressText').textContent = 'Compactando arquivos...';
    const zipBlob = await zip.generateAsync({ type: 'blob' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(zipBlob);
    link.download = 'Certificados - ' + colaboradorData.nome + '.zip';
    link.click();
    URL.revokeObjectURL(link.href);

    document.body.removeChild(container);
    document.body.removeChild(progressDiv);
}
