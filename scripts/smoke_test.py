"""
SESMT — Smoke test usando requests.

Cria um usuário admin de teste sem 2FA, faz login real, navega por todas
as rotas principais, valida HTTP status e conteúdo-chave, e remove o
usuário ao final. Não cria/edita registros de produção.
"""
import re
import sys
import subprocess
import time
import requests
from urllib.parse import urljoin

BASE = "http://localhost:8081"
TEST_EMAIL = "smoke.test@tsea.local"
TEST_PASS = "TseAdmin@2026"

DB_EXEC = ["docker", "exec", "sesmt-system-db-1", "mariadb", "-u", "sesmt", "-psesmt2026", "sesmt_tse", "-e"]


def db_exec(sql):
    return subprocess.run(DB_EXEC + [sql], capture_output=True, text=True, encoding="utf-8")


def gen_bcrypt(password):
    """Gera bcrypt hash via PHP do container (cost 12, mesma rotina do app)."""
    r = subprocess.run(
        ["docker", "exec", "sesmt-system-web-1", "php", "-r",
         f"echo password_hash('{password}', PASSWORD_BCRYPT, ['cost' => 12]);"],
        capture_output=True, text=True, encoding="utf-8"
    )
    return r.stdout.strip()


# ─── Cores e relatório ────────────────────────────────────────────────────
GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; RESET = "\033[0m"
results = []  # (status, name, detail)

def ok(name, detail=""):
    print(f"  {GREEN}✓{RESET} {name}" + (f" — {detail}" if detail else ""))
    results.append(("OK", name, detail))

def fail(name, detail=""):
    print(f"  {RED}✗{RESET} {name}" + (f" — {detail}" if detail else ""))
    results.append(("FAIL", name, detail))

def section(name):
    print(f"\n{CYAN}══ {name} ══{RESET}")


# ─── Setup / teardown do usuário de teste ─────────────────────────────────
def setup_user():
    test_hash = gen_bcrypt(TEST_PASS)
    if not test_hash.startswith("$2y$"):
        print(f"{RED}Falha ao gerar hash bcrypt:{RESET} {test_hash!r}")
        sys.exit(1)
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    r = db_exec(
        f"INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, totp_ativo, criado_em) "
        f"VALUES ('Smoke Test', '{TEST_EMAIL}', '{test_hash}', 'admin', 1, 0, NOW());"
    )
    if r.returncode != 0:
        print(f"{RED}Falha ao criar usuário de teste:{RESET} {r.stderr}")
        sys.exit(1)
    print(f"{GREEN}Usuário de teste criado:{RESET} {TEST_EMAIL}")

def teardown_user():
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    print(f"{GREEN}Usuário de teste removido.{RESET}")


# ─── Login ────────────────────────────────────────────────────────────────
def login(session):
    r = session.get(f"{BASE}/login")
    if r.status_code != 200:
        fail("GET /login", f"HTTP {r.status_code}")
        return False
    m = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text)
    if not m:
        fail("CSRF token", "não encontrado em /login")
        return False
    csrf = m.group(1)
    r = session.post(f"{BASE}/login", data={
        "_csrf_token": csrf, "email": TEST_EMAIL, "senha": TEST_PASS,
    }, allow_redirects=True)
    if "/dashboard" in r.url or r.status_code == 200 and "Dashboard" in r.text[:5000]:
        ok("Login admin sem 2FA", f"redirect → {r.url}")
        return True
    fail("Login", f"HTTP {r.status_code}, url={r.url}")
    return False


# ─── Helpers de teste ─────────────────────────────────────────────────────
def get(session, path, expect_code=200, expect_text=None, name=None):
    name = name or f"GET {path}"
    r = session.get(f"{BASE}{path}", allow_redirects=False)
    if r.status_code != expect_code:
        fail(name, f"esperava {expect_code}, recebi {r.status_code}")
        return None
    if expect_text and expect_text not in r.text:
        fail(name, f"texto '{expect_text}' não encontrado")
        return None
    ok(name, f"HTTP {r.status_code}" + (f", contém '{expect_text}'" if expect_text else ""))
    return r

def has_no_php_error(session, path):
    r = session.get(f"{BASE}{path}", allow_redirects=True)
    bad = ["Fatal error", "Parse error", "Uncaught", "PDOException", "Warning</b>:", "Stack trace"]
    found = [b for b in bad if b in r.text]
    name = f"Sem erros PHP em {path}"
    if found:
        fail(name, f"encontrado: {found}")
    else:
        ok(name)


# ─── Suite ────────────────────────────────────────────────────────────────
def run():
    s = requests.Session()
    s.headers["User-Agent"] = "SmokeTest/1.0"

    section("Páginas públicas")
    get(s, "/login", expect_text="SESMT TSE", name="GET /login (página)")
    r = s.get(f"{BASE}/", allow_redirects=False)
    if r.status_code in (302, 301):
        ok("GET / redireciona", f"→ {r.headers.get('Location', '?')}")
    else:
        fail("GET / não redireciona", f"HTTP {r.status_code}")

    section("Login")
    if not login(s):
        return

    section("Smoke — rotas autenticadas")
    rotas = [
        ("/dashboard",                 "Dashboard"),
        ("/colaboradores",             "Colaboradores"),
        ("/colaboradores?status=todos", None),
        ("/certificados",              "Certificados"),
        ("/treinamentos",              "Treinamentos"),
        ("/treinamentos/calendario",   None),
        ("/documentos",                "Documentos"),
        ("/aprovacoes",                None),
        ("/kit-pj",                    "Kit PJ"),
        ("/clientes",                  None),
        ("/agenda-exames",             None),
        ("/checklist",                 None),
        ("/alertas",                   None),
        ("/relatorios",                None),
        ("/usuarios",                  "Usuarios"),
        ("/logs",                      None),
        ("/gco",                       None),
        ("/lixeira",                   None),
        ("/rh",                        "Painel RH"),
        ("/backup",                    None),
        ("/configuracoes",             None),
    ]
    for path, txt in rotas:
        get(s, path, 200, txt)

    section("Sem erros PHP nas páginas críticas")
    for path in ["/dashboard", "/colaboradores", "/treinamentos",
                 "/documentos", "/rh", "/kit-pj"]:
        has_no_php_error(s, path)

    section("Endpoints de exportação (Excel)")
    r = s.get(f"{BASE}/exportar/colaboradores", allow_redirects=False)
    if r.status_code == 200 and "spreadsheet" in r.headers.get("Content-Type", ""):
        ok("GET /exportar/colaboradores", "retorna xlsx")
    else:
        fail("GET /exportar/colaboradores", f"HTTP {r.status_code} ct={r.headers.get('Content-Type','')}")

    r = s.get(f"{BASE}/exportar/documentos", allow_redirects=False)
    if r.status_code == 200 and "spreadsheet" in r.headers.get("Content-Type", ""):
        ok("GET /exportar/documentos", "retorna xlsx")
    else:
        fail("GET /exportar/documentos", f"HTTP {r.status_code} ct={r.headers.get('Content-Type','')}")

    section("Conteúdo específico — Painel RH (Fase 1 + 2 + 3)")
    r = s.get(f"{BASE}/rh")
    for keyword in ["Pendentes de envio", "Aguardando confirmação", "Confirmados", "Total geral",
                    "Dashboard gerencial", "Recalcular pendências", "Relatórios", "Configurações"]:
        if keyword in r.text:
            ok(f"/rh contém '{keyword}'")
        else:
            fail(f"/rh contém '{keyword}'")

    # Fase 3: Dashboard
    r = s.get(f"{BASE}/rh/dashboard")
    if r.status_code == 200 and "Conformidade global" in r.text and "Mapa de calor" in r.text:
        ok("/rh/dashboard renderiza com KPIs e mapa de calor")
    else:
        fail(f"/rh/dashboard HTTP={r.status_code}")

    # Fase 3: Relatórios
    r = s.get(f"{BASE}/rh/relatorios")
    if r.status_code == 200 and "Pendências em aberto por cliente" in r.text:
        ok("/rh/relatorios renderiza")
    else:
        fail(f"/rh/relatorios HTTP={r.status_code}")

    # Fase 3: Configurações
    r = s.get(f"{BASE}/rh/configuracoes")
    if r.status_code == 200 and "Janelas de alerta" in r.text and "SLA de reprotocolo" in r.text:
        ok("/rh/configuracoes renderiza")
    else:
        fail(f"/rh/configuracoes HTTP={r.status_code}")

    # Fase 3: Excel export
    r = s.get(f"{BASE}/rh/relatorios/conformidade-obra.xlsx")
    if r.status_code == 200 and "spreadsheet" in r.headers.get("Content-Type",""):
        ok("/rh/relatorios/conformidade-obra.xlsx retorna XLSX")
    else:
        fail(f"/rh/relatorios/conformidade-obra.xlsx HTTP={r.status_code} ct={r.headers.get('Content-Type','')}")

    section("Conteúdo específico — Treinamentos (Grupo 7)")
    r = s.get(f"{BASE}/treinamentos")
    if "Treinamentos" in r.text:
        ok("/treinamentos renderiza")
    # Pega o primeiro id de turma da listagem para abrir
    m = re.search(r'/treinamentos/(\d+)', r.text)
    if m:
        tid = m.group(1)
        r2 = s.get(f"{BASE}/treinamentos/{tid}")
        for kw in ["Presença", "Fotos do Treinamento"]:
            if kw in r2.text:
                ok(f"/treinamentos/{tid} contém '{kw}'")
            else:
                fail(f"/treinamentos/{tid} contém '{kw}'")
        r3 = s.get(f"{BASE}/treinamentos/{tid}/editar")
        if "Tipo de Certificado" in r3.text:
            ok(f"/treinamentos/{tid}/editar renderiza")
        else:
            fail(f"/treinamentos/{tid}/editar")

    section("Conteúdo específico — Kit PJ (Grupo 5+6)")
    r = s.get(f"{BASE}/kit-pj/novo")
    if "Posturas inc" in r.text:
        ok("/kit-pj/novo: risco ergonômico default")
    else:
        fail("/kit-pj/novo: risco ergonômico")
    if "contato com eletricidade" in r.text:
        ok("/kit-pj/novo: novo risco de eletricidade")
    else:
        fail("/kit-pj/novo: novo risco de eletricidade")

    r = s.get(f"{BASE}/kit-pj")
    if "Excluir" in r.text:
        ok("/kit-pj: botão Excluir disponível")

    section("Conteúdo específico — Tela emissão (Grupo 2.4)")
    # Preciso de um colaborador para testar
    r = s.get(f"{BASE}/colaboradores")
    m = re.search(r'/colaboradores/(\d+)', r.text)
    if m:
        cid = m.group(1)
        r2 = s.get(f"{BASE}/certificados/emitir/{cid}")
        if r2.status_code == 200:
            ok(f"/certificados/emitir/{cid} renderiza")

    section("Logout")
    r = s.get(f"{BASE}/logout", allow_redirects=False)
    if r.status_code in (302, 301):
        ok("GET /logout redireciona", f"→ {r.headers.get('Location','?')}")
        # Confirma sessão derrubada
        r2 = s.get(f"{BASE}/dashboard", allow_redirects=False)
        if r2.status_code in (302, 301) and "/login" in r2.headers.get("Location", ""):
            ok("Sessão encerrada", "/dashboard → /login")
        else:
            fail("Sessão NÃO encerrada", f"HTTP {r2.status_code}")

    # Relatório final
    print()
    total = len(results); falhas = sum(1 for r in results if r[0] == "FAIL")
    if falhas == 0:
        print(f"{GREEN}══ TODOS OS {total} TESTES PASSARAM ══{RESET}")
    else:
        print(f"{RED}══ {falhas}/{total} FALHAS ══{RESET}")
        for s_, n, d in results:
            if s_ == "FAIL":
                print(f"  {RED}{n}{RESET} — {d}")
    return falhas == 0


if __name__ == "__main__":
    setup_user()
    try:
        success = run()
    finally:
        teardown_user()
    sys.exit(0 if success else 1)
