"""
SESMT — Teste de integração profundo.

Simula uso real do sistema fazendo POST em todos os fluxos críticos:
cliente → obra → colaborador → documento (upload + substituir + marcar
enviado ao cliente) → treinamento (criar, adicionar/remover colab,
marcar presença, upload de foto, vincular cert assinado, editar NR,
excluir) → kit pj (criar + excluir).

Cria um admin temporário sem 2FA e LIMPA TUDO no finally.
"""
import os
import re
import sys
import io
import subprocess
import requests

BASE = "http://localhost:8081"
TEST_EMAIL = "integration.test@tsea.local"
TEST_PASS = "TseAdmin@2026"

DB_EXEC = ["docker", "exec", "sesmt-system-db-1", "mariadb", "-u", "sesmt", "-psesmt2026", "sesmt_tse"]


def db_query(sql):
    r = subprocess.run(DB_EXEC + ["-N", "-e", sql], capture_output=True, text=True, encoding="utf-8")
    return r.stdout.strip()

def db_exec(sql):
    return subprocess.run(DB_EXEC + ["-e", sql], capture_output=True, text=True, encoding="utf-8")

def gen_bcrypt(password):
    r = subprocess.run(
        ["docker", "exec", "sesmt-system-web-1", "php", "-r",
         f"echo password_hash('{password}', PASSWORD_BCRYPT, ['cost' => 12]);"],
        capture_output=True, text=True, encoding="utf-8"
    )
    return r.stdout.strip()


# ─── Cores ─────────────────────────────────────────────────────────────────
GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; BOLD = "\033[1m"; RESET = "\033[0m"
results = []
created_ids = {}  # rastreia o que criamos para limpar

def step(msg):
    print(f"\n{CYAN}▶ {msg}{RESET}")

def ok(name, detail=""):
    print(f"  {GREEN}✓{RESET} {name}" + (f" — {detail}" if detail else ""))
    results.append(("OK", name, detail))

def fail(name, detail=""):
    print(f"  {RED}✗{RESET} {name}" + (f" — {detail}" if detail else ""))
    results.append(("FAIL", name, detail))


# ─── Setup / teardown ──────────────────────────────────────────────────────
def setup_user():
    h = gen_bcrypt(TEST_PASS)
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    db_exec(
        f"INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, totp_ativo, criado_em) "
        f"VALUES ('Integration Test', '{TEST_EMAIL}', '{h}', 'admin', 1, 0, NOW());"
    )
    print(f"{GREEN}Usuário de teste criado:{RESET} {TEST_EMAIL}")

def teardown():
    # Apaga, em ordem reversa, tudo que criamos
    print(f"\n{YELLOW}── Limpando dados de teste ──{RESET}")
    if "kit_pj_id" in created_ids:
        db_exec(f"DELETE FROM kits_pj WHERE id={created_ids['kit_pj_id']};")
    if "treinamento_id" in created_ids:
        db_exec(f"DELETE FROM certificados WHERE treinamento_id={created_ids['treinamento_id']};")
        db_exec(f"DELETE FROM treinamentos WHERE id={created_ids['treinamento_id']};")
    if "colab_id" in created_ids:
        db_exec(f"DELETE FROM rh_protocolo_comprovantes WHERE protocolo_id IN (SELECT id FROM rh_protocolos WHERE colaborador_id={created_ids['colab_id']});")
        db_exec(f"DELETE FROM rh_protocolos WHERE colaborador_id={created_ids['colab_id']};")
        db_exec(f"DELETE FROM documentos WHERE colaborador_id={created_ids['colab_id']};")
        db_exec(f"DELETE FROM certificados WHERE colaborador_id={created_ids['colab_id']};")
        db_exec(f"DELETE FROM colaboradores WHERE id={created_ids['colab_id']};")
    if "obra_id" in created_ids:
        db_exec(f"DELETE FROM obras WHERE id={created_ids['obra_id']};")
    if "cliente_id" in created_ids:
        db_exec(f"DELETE FROM clientes WHERE id={created_ids['cliente_id']};")
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    print(f"{GREEN}Limpeza concluída.{RESET}")


# ─── Helpers HTTP ──────────────────────────────────────────────────────────
def login(s):
    r = s.get(f"{BASE}/login")
    csrf = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text).group(1)
    r = s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": TEST_EMAIL, "senha": TEST_PASS}, allow_redirects=True)
    if "/dashboard" not in r.url and "Dashboard" not in r.text[:5000]:
        fail("Login", f"url={r.url}")
        return False
    ok("Login admin sem 2FA")
    return True


def fresh_csrf(s):
    r = s.get(f"{BASE}/dashboard")
    m = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text)
    if m:
        return m.group(1)
    # fallback: meta tag
    m = re.search(r'<meta name="csrf-token" content="([^"]+)"', r.text)
    return m.group(1) if m else None


def fake_pdf_bytes(label="DOC"):
    """Gera um PDF mínimo válido."""
    return (
        b"%PDF-1.4\n"
        b"1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n"
        b"2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n"
        b"3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]>> endobj\n"
        b"xref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n"
        b"0000000052 00000 n \n0000000101 00000 n \n"
        b"trailer <</Size 4 /Root 1 0 R>>\nstartxref\n160\n%%EOF\n"
    )


def fake_jpg_bytes():
    """JPG mínimo (1x1 px branco)."""
    return bytes.fromhex(
        "ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707"
        "07090908"
        + "0a0c14"
        + "0d0c0b"
        + "0b0c19121309" * 1
        + "ffd9"
    )


# ─── Flow ──────────────────────────────────────────────────────────────────
def run():
    s = requests.Session()
    s.headers["User-Agent"] = "IntegrationTest/1.0"

    if not login(s):
        return

    csrf = fresh_csrf(s)
    if not csrf:
        fail("CSRF token", "não conseguiu obter")
        return

    # ── 1. Cliente CRUD ─────────────────────────────────────────────────
    step("Cliente CRUD")
    r = s.post(f"{BASE}/clientes/salvar", data={
        "_csrf_token": csrf,
        "razao_social": "Cliente Integração Teste Ltda",
        "nome_fantasia": "INTEG TEST",
        "cnpj": "00.000.000/0001-91",
        "contato_nome": "Tester",
        "contato_email": "tester@integ.local",
        "contato_telefone": "11999990000",
    })
    if r.status_code in (200, 302):
        cid = db_query("SELECT id FROM clientes WHERE razao_social='Cliente Integração Teste Ltda' LIMIT 1;")
        if cid.isdigit():
            created_ids["cliente_id"] = int(cid)
            ok("POST /clientes/salvar", f"id={cid}")
        else:
            fail("Cliente não criado no banco")
            return
    else:
        fail(f"POST /clientes/salvar HTTP {r.status_code}")
        return

    # Verificar que aparece na lista
    r = s.get(f"{BASE}/clientes/{created_ids['cliente_id']}")
    if "Cliente Integração Teste Ltda" in r.text or "INTEG TEST" in r.text:
        ok("GET /clientes/{id}: cliente renderiza")
    else:
        fail("Cliente não aparece na tela /clientes/{id}")

    # ── 2. Obra CRUD ────────────────────────────────────────────────────
    step("Obra CRUD")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/obras/salvar", data={
        "_csrf_token": csrf,
        "cliente_id": created_ids["cliente_id"],
        "nome": "Obra Integração",
        "local_obra": "São Paulo - SP",
        "data_inicio": "2026-01-01",
        "status": "ativa",
    })
    oid = db_query(f"SELECT id FROM obras WHERE cliente_id={created_ids['cliente_id']} AND nome='Obra Integração' LIMIT 1;")
    if oid.isdigit():
        created_ids["obra_id"] = int(oid)
        ok("POST /obras/salvar", f"id={oid}")
    else:
        fail("Obra não criada")
        return

    # Tela do cliente deve mostrar a obra com resumo de conformidade (Bug 2 fix)
    r = s.get(f"{BASE}/clientes/{created_ids['cliente_id']}")
    if "Conformidade" in r.text and "sem colaboradores" in r.text:
        ok("Coluna 'Conformidade' aparece em /clientes/{id} (Bug 2 fix)")
    else:
        fail("Coluna 'Conformidade' não aparece corretamente")

    # ── 3. Colaborador CRUD ────────────────────────────────────────────
    step("Colaborador CRUD")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/colaboradores/salvar", data={
        "_csrf_token": csrf,
        "nome_completo": "JOAO INTEGRACAO TESTE",
        "cpf": "529.982.247-25",  # CPF válido
        "matricula": "INT-001",
        "cargo": "ELETRICISTA",
        "funcao": "ELETRICISTA",
        "setor": "MANUTENCAO",
        "cliente_id": created_ids["cliente_id"],
        "obra_id": created_ids["obra_id"],
        "data_admissao": "2026-01-15",
        "status": "ativo",
        "data_nascimento": "1990-05-10",
        "telefone": "11988887777",
        "email": "joao.integ@local.test",
    })
    cid_colab = db_query("SELECT id FROM colaboradores WHERE nome_completo='JOAO INTEGRACAO TESTE' LIMIT 1;")
    if cid_colab.isdigit():
        created_ids["colab_id"] = int(cid_colab)
        ok("POST /colaboradores/salvar", f"id={cid_colab}")
    else:
        fail(f"Colaborador não criado. HTTP {r.status_code}, body[:200]={r.text[:200]}")
        return

    # ── 4. Upload de documento ─────────────────────────────────────────
    step("Upload de documento (ASO Periódico)")
    csrf = fresh_csrf(s)
    tipo_aso = db_query("SELECT id FROM tipos_documento WHERE nome='ASO Periódico' LIMIT 1;")
    pdf_bytes = fake_pdf_bytes("ASO")
    files = {"arquivos[]": ("aso_teste.pdf", pdf_bytes, "application/pdf")}
    r = s.post(f"{BASE}/documentos/upload", data={
        "_csrf_token": csrf,
        "colaborador_id": created_ids["colab_id"],
        "tipo_documento_id": tipo_aso,
        "data_emissao": "2026-01-15",
    }, files=files)
    docs = db_query(f"SELECT COUNT(*) FROM documentos WHERE colaborador_id={created_ids['colab_id']};")
    if int(docs) >= 1:
        ok("POST /documentos/upload", f"{docs} doc(s) gravado(s)")
    else:
        fail(f"Doc não foi criado. HTTP {r.status_code}")
        return

    # Verifica que aparece em /rh como pendente de envio (Grupo 8)
    r = s.get(f"{BASE}/rh?filtro=pendentes")
    if "JOAO INTEGRACAO TESTE" in r.text:
        ok("Doc aparece em /rh como pendente de envio")
    else:
        fail("Doc não apareceu em /rh")

    # ── 5. Marcar 'Enviado ao cliente' ─────────────────────────────────
    step("Marcar 'Enviado ao cliente' (Grupo 8)")
    doc_id = db_query(f"SELECT id FROM documentos WHERE colaborador_id={created_ids['colab_id']} ORDER BY id DESC LIMIT 1;")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/documentos/{doc_id}/enviado-cliente", data={"_csrf_token": csrf, "marcar": "1"})
    if r.status_code == 200:
        try:
            data = r.json()
            if data.get("success"):
                marcado = db_query(f"SELECT enviado_cliente FROM documentos WHERE id={doc_id};")
                if marcado == "1":
                    ok("POST /documentos/{id}/enviado-cliente: marcado e persistido")
                else:
                    fail(f"DB não persistiu enviado_cliente (={marcado})")
            else:
                fail("JSON sem success=true")
        except Exception:
            fail("Resposta não é JSON")
    else:
        fail(f"HTTP {r.status_code}")

    # ── 5b. Registrar protocolo no cliente (Fase 1 — Reprotocolo) ──────
    step("Registrar protocolo no cliente (Fase 1)")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/rh/{doc_id}/marcar-enviado", data={
        "_csrf_token":      csrf,
        "numero_protocolo": "TEST-001",
        "data_protocolo":   "2026-05-02",
        "observacoes":      "Teste de integração",
    })
    if r.status_code == 200:
        try:
            data = r.json()
            if data.get("success"):
                rp_status = db_query(f"SELECT status FROM rh_protocolos WHERE documento_id={doc_id} LIMIT 1;")
                if rp_status == "enviado":
                    ok("POST /rh/{id}/marcar-enviado: rh_protocolos.status='enviado'")
                else:
                    fail(f"rh_protocolos.status inesperado (={rp_status})")
            else:
                fail(f"JSON sem success=true: {data}")
        except Exception:
            fail("Resposta não é JSON")
    else:
        fail(f"HTTP {r.status_code}")

    # Após registrar: deve sair de 'pendente' e aparecer em 'enviado'
    r = s.get(f"{BASE}/rh?status=pendente")
    if "JOAO INTEGRACAO TESTE" not in r.text:
        ok("Após registrar, doc some de /rh?status=pendente")
    else:
        fail("Doc continua aparecendo como pendente")
    r = s.get(f"{BASE}/rh?status=enviado")
    if "JOAO INTEGRACAO TESTE" in r.text:
        ok("Doc aparece em /rh?status=enviado")
    else:
        fail("Doc não aparece em /rh?status=enviado")

    # ── 6. Substituir documento (Grupo 8) ──────────────────────────────
    step("Substituir documento")
    csrf = fresh_csrf(s)
    files = {"arquivo": ("aso_v2.pdf", fake_pdf_bytes("ASO_V2"), "application/pdf")}
    r = s.post(f"{BASE}/documentos/{doc_id}/substituir", data={
        "_csrf_token": csrf,
        "data_emissao": "2026-04-01",
    }, files=files, allow_redirects=False)

    # Antigo deve estar soft-deleted + substituido_por preenchido
    sub_por = db_query(f"SELECT substituido_por FROM documentos WHERE id={doc_id};")
    if sub_por.isdigit():
        ok(f"Antigo aponta para substituto (substituido_por={sub_por})")
        # Novo deve ter enviado_cliente=0 (volta a pendente)
        novo_envio = db_query(f"SELECT enviado_cliente FROM documentos WHERE id={sub_por};")
        if novo_envio == "0":
            ok("Nova versão entra com enviado_cliente=0 (volta a pendente)")
        else:
            fail(f"Nova versão deveria ter enviado_cliente=0, mas está {novo_envio}")
    else:
        fail(f"substituido_por não preenchido. HTTP {r.status_code}")

    # ── 7. Conformidade do cliente reflete colaborador ─────────────────
    step("Conformidade no painel do cliente (Bug 1 fix)")
    r = s.get(f"{BASE}/clientes/{created_ids['cliente_id']}")
    # Deve mostrar 1 colaborador na obra criada com status correto
    if str(created_ids["obra_id"]) in r.text and "✓" in r.text:
        ok("Cliente mostra colaborador regular (versão antiga não conta)")
    else:
        fail("Resumo da obra no cliente não reflete o colaborador")

    # ── 8. Treinamento (Grupo 1+7) ─────────────────────────────────────
    step("Criar Treinamento e adicionar colaborador")
    csrf = fresh_csrf(s)
    # Pega um tipo de certificado simples (NR-06 tem id 3 baseado em verificações anteriores)
    tipo_cert = db_query("SELECT id FROM tipos_certificado WHERE codigo='NR 06' LIMIT 1;")
    r = s.post(f"{BASE}/treinamentos/salvar", data={
        "_csrf_token": csrf,
        "tipo_certificado_id": tipo_cert,
        "data_realizacao": "2026-04-15",
        "data_emissao": "2026-04-15",
        "colaborador_ids[]": [created_ids["colab_id"]],
        "observacoes": "Turma de teste integração",
    }, allow_redirects=True)
    tid = db_query(f"SELECT id FROM treinamentos WHERE observacoes='Turma de teste integração' LIMIT 1;")
    if tid.isdigit():
        created_ids["treinamento_id"] = int(tid)
        ok("POST /treinamentos/salvar", f"id={tid}")
    else:
        fail(f"Turma não criada. HTTP {r.status_code}")
        return

    # ── 9. Marcar presença (Grupo 7) ───────────────────────────────────
    step("Marcar presença (Grupo 7)")
    cert_id = db_query(f"SELECT id FROM certificados WHERE treinamento_id={created_ids['treinamento_id']} LIMIT 1;")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/treinamentos/{tid}/marcar-presenca", data={
        "_csrf_token": csrf,
        "certificado_id": cert_id,
        "presente": "1",
    })
    pres = db_query(f"SELECT presente FROM certificados WHERE id={cert_id};")
    if pres == "1":
        ok("POST /treinamentos/{id}/marcar-presenca: presente=1 persistiu")
    else:
        fail(f"presente={pres}")

    # ── 10. Upload foto (Grupo 7) ──────────────────────────────────────
    step("Upload de foto da turma")
    csrf = fresh_csrf(s)
    files = {"foto": ("foto1.jpg", fake_jpg_bytes(), "image/jpeg")}
    r = s.post(f"{BASE}/treinamentos/{tid}/upload-foto", data={
        "_csrf_token": csrf,
        "slot": "1",
    }, files=files, allow_redirects=False)
    foto1 = db_query(f"SELECT foto1_path FROM treinamentos WHERE id={tid};")
    if foto1 and foto1 != "NULL":
        ok(f"foto1_path persistiu: {foto1}")
    else:
        fail(f"foto1_path vazio. HTTP {r.status_code}")

    # ── 11. Editar turma (trocar NR — Grupo 7) ─────────────────────────
    step("Tentar trocar NR da turma com cert já gerado (deve recusar)")
    csrf = fresh_csrf(s)
    outro_tipo = db_query("SELECT id FROM tipos_certificado WHERE codigo='NR 35' LIMIT 1;")
    r = s.post(f"{BASE}/treinamentos/{tid}/atualizar", data={
        "_csrf_token": csrf,
        "tipo_certificado_id": outro_tipo,
        "data_realizacao": "2026-04-15",
        "data_emissao": "2026-04-15",
    }, allow_redirects=True)
    tipo_atual = db_query(f"SELECT tipo_certificado_id FROM treinamentos WHERE id={tid};")
    if tipo_atual == tipo_cert:
        ok("Servidor recusou troca de NR (turma já tem certs)")
    else:
        fail(f"NR foi trocada indevidamente para {tipo_atual}")

    # ── 12. Vincular cert assinado (Grupo 1) ───────────────────────────
    step("Vincular certificado assinado (Grupo 1 fix)")
    csrf = fresh_csrf(s)
    files = {"arquivo_assinado": ("cert_assinado.pdf", fake_pdf_bytes("CERT"), "application/pdf")}
    r = s.post(f"{BASE}/treinamentos/{tid}/upload-assinado", data={
        "_csrf_token": csrf,
        "certificado_id": cert_id,
    }, files=files, allow_redirects=False)
    arq_ass = db_query(f"SELECT arquivo_assinado FROM certificados WHERE id={cert_id};")
    if arq_ass and arq_ass != "NULL":
        ok(f"arquivo_assinado persistiu: {arq_ass}")
    else:
        fail(f"arquivo_assinado vazio. HTTP {r.status_code}")

    # ── 13. Remover colaborador da turma ───────────────────────────────
    step("Remover colaborador da turma")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/treinamentos/{tid}/remover-colaborador", data={
        "_csrf_token": csrf,
        "certificado_id": cert_id,
    })
    if r.status_code == 200:
        try:
            if r.json().get("success"):
                ok("POST /treinamentos/{id}/remover-colaborador: success=true (sem JSON cru na tela)")
            else:
                fail("JSON sem success=true")
        except Exception:
            fail("Resposta não é JSON")
    else:
        fail(f"HTTP {r.status_code}")

    # ── 14. Kit PJ create + delete ─────────────────────────────────────
    step("Kit PJ — criar e excluir")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/kit-pj/salvar", data={
        "_csrf_token": csrf,
        "colaborador_id": created_ids["colab_id"],
        "razao_social": "JOAO INTEGRACAO TESTE",
        "cnpj": "00.000.000/0001-91",
        "endereco": "Rua Teste, 123",
        "tipo_aso": "admissional",
        "medico_nome": "Dr. Tester",
        "medico_crm": "12345",
        "medico_uf": "SP",
        "riscos_fisicos": "Ausencia",
        "riscos_quimicos": "Ausencia",
        "riscos_biologicos": "Ausencia",
        "riscos_ergonomicos[]": ["Posturas em pe/sentado por longos períodos"],
        "riscos_acidentes[]": ["Eletricidade"],
        "exames[]": ["Exame Clínico"],
        "aptidoes[]": ["Apto para a função"],
    }, allow_redirects=True)
    kit_id = db_query(f"SELECT id FROM kits_pj WHERE colaborador_id={created_ids['colab_id']} LIMIT 1;")
    if kit_id.isdigit():
        created_ids["kit_pj_id"] = int(kit_id)
        ok(f"POST /kit-pj/salvar: id={kit_id}")
        # Excluir
        csrf = fresh_csrf(s)
        r = s.post(f"{BASE}/kit-pj/{kit_id}/excluir", data={"_csrf_token": csrf}, allow_redirects=True)
        existe = db_query(f"SELECT COUNT(*) FROM kits_pj WHERE id={kit_id};")
        if existe == "0":
            ok("POST /kit-pj/{id}/excluir: removido")
            del created_ids["kit_pj_id"]
        else:
            fail("Kit PJ não foi removido")
    else:
        fail(f"Kit PJ não criado. HTTP {r.status_code}, body[:300]={r.text[:300]}")

    # ── 15. Excluir treinamento ────────────────────────────────────────
    step("Excluir treinamento")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/treinamentos/{tid}/excluir", data={"_csrf_token": csrf}, allow_redirects=True)
    excluido = db_query(f"SELECT excluido_em FROM treinamentos WHERE id={tid};")
    if excluido and excluido != "NULL":
        ok("Turma soft-deletada")
    else:
        fail(f"Turma não foi soft-deletada (excluido_em={excluido})")

    # ── 16. Excluir colaborador ────────────────────────────────────────
    step("Excluir colaborador")
    csrf = fresh_csrf(s)
    r = s.post(f"{BASE}/colaboradores/{created_ids['colab_id']}/excluir", data={"_csrf_token": csrf}, allow_redirects=True)
    excluido = db_query(f"SELECT excluido_em FROM colaboradores WHERE id={created_ids['colab_id']};")
    if excluido and excluido != "NULL":
        ok("Colaborador soft-deletado")
    else:
        fail(f"Colaborador não foi soft-deletado (excluido_em={excluido})")

    # Relatório final
    print()
    total = len(results); falhas = sum(1 for r in results if r[0] == "FAIL")
    if falhas == 0:
        print(f"{GREEN}{BOLD}══ TODOS OS {total} CHECKS DE INTEGRAÇÃO PASSARAM ══{RESET}")
        return True
    print(f"{RED}{BOLD}══ {falhas}/{total} FALHAS ══{RESET}")
    for s_, n, d in results:
        if s_ == "FAIL":
            print(f"  {RED}{n}{RESET} — {d}")
    return False


if __name__ == "__main__":
    setup_user()
    try:
        success = run()
    finally:
        teardown()
    sys.exit(0 if success else 1)
