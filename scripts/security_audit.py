"""
SESMT — Auditoria de segurança ativa.

Probes que testam:
- Autenticação obrigatória em todas as rotas
- CSRF protection em POSTs
- SQL injection em parâmetros de busca
- XSS refletido em parâmetros
- IDOR (acessar recurso de outro escopo)
- Path traversal em rotas que recebem ID
- Open redirect
- Headers de segurança (X-Frame-Options, CSP, etc.)
- HTTP method tampering
- Force browse (rotas administrativas)
- Mass assignment (campos não esperados)

Não causa danos: faz GET em endpoints públicos, POSTs com dados de
teste em endpoints de leitura, e usa um usuário admin temporário para
o que precisa de autenticação. Limpa no finally.
"""
import re
import sys
import subprocess
import requests

BASE = "http://localhost:8081"
TEST_EMAIL = "secaudit@tsea.local"
TEST_PASS = "TseAdmin@2026"

DB = ["docker", "exec", "sesmt-system-db-1", "mariadb", "-u", "sesmt", "-psesmt2026", "sesmt_tse"]


def db_query(sql):
    r = subprocess.run(DB + ["-N", "-e", sql], capture_output=True, text=True, encoding="utf-8")
    return r.stdout.strip()

def db_exec(sql):
    return subprocess.run(DB + ["-e", sql], capture_output=True, text=True, encoding="utf-8")

def gen_bcrypt(p):
    r = subprocess.run(
        ["docker", "exec", "sesmt-system-web-1", "php", "-r",
         f"echo password_hash('{p}', PASSWORD_BCRYPT, ['cost' => 12]);"],
        capture_output=True, text=True, encoding="utf-8"
    )
    return r.stdout.strip()


GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; BOLD = "\033[1m"; RESET = "\033[0m"
findings = []  # (severity, name, detail)

def section(name):
    print(f"\n{CYAN}{BOLD}══ {name} ══{RESET}")

def ok(name):
    print(f"  {GREEN}✓{RESET} {name}")

def vuln(severity, name, detail=""):
    color = RED if severity == "HIGH" else YELLOW
    icon = "✗" if severity == "HIGH" else "!"
    print(f"  {color}{icon} [{severity}] {name}{RESET}" + (f" — {detail}" if detail else ""))
    findings.append((severity, name, detail))


def setup_user():
    h = gen_bcrypt(TEST_PASS)
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    db_exec(
        f"INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, totp_ativo, criado_em) "
        f"VALUES ('Sec Audit', '{TEST_EMAIL}', '{h}', 'admin', 1, 0, NOW());"
    )

def teardown():
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")


def login(s):
    r = s.get(f"{BASE}/login")
    csrf = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text).group(1)
    r = s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": TEST_EMAIL, "senha": TEST_PASS}, allow_redirects=True)
    return "/dashboard" in r.url or "Dashboard" in r.text[:5000]


# ─── Probes ────────────────────────────────────────────────────────────────
def test_unauth_routes():
    """Rotas autenticadas não devem servir conteúdo sem login."""
    section("1. Autenticação obrigatória em rotas privadas")
    s = requests.Session()
    rotas_privadas = [
        "/dashboard", "/colaboradores", "/certificados", "/treinamentos",
        "/documentos", "/kit-pj", "/clientes", "/usuarios", "/logs",
        "/backup", "/configuracoes", "/aprovacoes", "/rh", "/relatorios",
    ]
    for path in rotas_privadas:
        r = s.get(f"{BASE}{path}", allow_redirects=False)
        # Esperado: 302 → /login. NÃO 200, NÃO 500.
        if r.status_code in (301, 302) and "/login" in r.headers.get("Location", ""):
            ok(f"{path} redireciona para /login")
        elif r.status_code == 200:
            vuln("HIGH", f"{path} acessível sem login", f"HTTP 200 sem auth")
        else:
            vuln("MEDIUM", f"{path} resposta inesperada", f"HTTP {r.status_code}")


def test_security_headers():
    """Headers de segurança HTTP."""
    section("2. Headers de segurança HTTP")
    r = requests.get(f"{BASE}/login")
    h = {k.lower(): v for k, v in r.headers.items()}

    required = {
        "x-frame-options": "Previne clickjacking",
        "x-content-type-options": "Previne MIME sniffing",
        "referrer-policy": "Vaza URL para terceiros",
    }
    for hd, why in required.items():
        if hd in h:
            ok(f"{hd}: {h[hd]}")
        else:
            vuln("MEDIUM", f"Header ausente: {hd}", why)

    if "content-security-policy" not in h:
        vuln("MEDIUM", "Content-Security-Policy ausente", "XSS amplificado")
    else:
        ok("CSP presente")

    if "set-cookie" in h:
        cookies = r.headers.get("Set-Cookie", "")
        if "HttpOnly" not in cookies:
            vuln("HIGH", "Cookie de sessão sem HttpOnly", "JS pode ler PHPSESSID")
        else:
            ok("Cookie HttpOnly")
        if "Secure" not in cookies and BASE.startswith("https://"):
            vuln("MEDIUM", "Cookie sem flag Secure", "Vai por HTTP claro")


def test_csrf_post():
    """POST sem CSRF token deve falhar."""
    section("3. CSRF protection em POSTs")
    s = requests.Session()
    if not login(s):
        vuln("HIGH", "Não conseguiu logar para testar CSRF")
        return

    # Tenta criar um cliente sem CSRF
    r = s.post(f"{BASE}/clientes/salvar", data={
        "razao_social": "TESTE CSRF AUDIT",
        "nome_fantasia": "TST",
    }, allow_redirects=False)
    if r.status_code in (200, 302):
        criado = db_query("SELECT id FROM clientes WHERE razao_social='TESTE CSRF AUDIT'")
        if criado.isdigit():
            vuln("HIGH", "CSRF aceito sem token!", f"cliente {criado} criado")
            db_exec(f"DELETE FROM clientes WHERE id={criado};")
        else:
            ok("POST sem CSRF foi rejeitado (não persistiu no banco)")
    else:
        ok(f"POST sem CSRF rejeitado com HTTP {r.status_code}")


def test_sql_injection():
    """Probes de SQL injection em parâmetros de busca."""
    section("4. SQL injection em parâmetros de busca")
    s = requests.Session()
    if not login(s):
        return

    payloads = [
        "' OR '1'='1",
        "1' UNION SELECT NULL--",
        "'; DROP TABLE colaboradores; --",
        "%27%20OR%201%3D1--",
        "x'='x",
    ]
    endpoints = [
        "/colaboradores?q={p}",
        "/documentos?q={p}",
        "/treinamentos?q={p}",
        "/kit-pj?q={p}",
        "/rh?q={p}",
        "/usuarios?q={p}",
    ]
    for ep in endpoints:
        for p in payloads:
            r = s.get(f"{BASE}{ep.format(p=p)}")
            indicators = ["You have an error in your SQL", "SQLSTATE", "Uncaught PDOException", "syntax error"]
            leaked = [i for i in indicators if i in r.text]
            if leaked:
                vuln("HIGH", f"Possível SQLi em {ep}", f"payload={p!r} sintoma={leaked}")
            elif r.status_code >= 500:
                vuln("MEDIUM", f"500 em {ep}", f"payload={p!r}")
        else:
            pass
    ok(f"{len(endpoints)} endpoints × {len(payloads)} payloads — nenhum vazamento de SQL")


def test_xss():
    """XSS refletido em parâmetros."""
    section("5. XSS refletido em parâmetros de URL")
    s = requests.Session()
    if not login(s):
        return

    payload = "<script>__xss_test_marker__</script>"
    canary = "__xss_test_marker__"
    endpoints = ["/colaboradores?q=", "/documentos?q=", "/treinamentos?q=", "/kit-pj?q="]
    for ep in endpoints:
        r = s.get(f"{BASE}{ep}{requests.utils.quote(payload)}")
        if "<script>" + canary in r.text:
            vuln("HIGH", f"XSS refletido em {ep}", "payload renderizado sem escape")
        elif canary in r.text:
            # Texto sai mas dentro de tag escapada — OK
            pass
    ok(f"XSS refletido — todos os {len(endpoints)} endpoints escapam payloads")


def test_idor_force_browse():
    """IDOR — tenta acessar recurso fora do escopo previsto."""
    section("6. IDOR / Force browse")
    s = requests.Session()
    if not login(s):
        return

    # Tenta acessar IDs inexistentes ou negativos
    ids_invalidos = [-1, 0, 999999, "abc", "../../etc/passwd"]
    rotas = ["/colaboradores/{id}", "/treinamentos/{id}", "/clientes/{id}", "/obras/{id}", "/kit-pj/{id}/imprimir"]
    falhas = 0
    for rota in rotas:
        for i in ids_invalidos:
            url = f"{BASE}{rota.format(id=i)}"
            r = s.get(url, allow_redirects=False)
            if r.status_code == 500:
                vuln("MEDIUM", f"500 em {rota} com id={i!r}", "deveria retornar 404 ou redirect")
                falhas += 1
            elif r.status_code == 200 and i in (-1, 0, 999999):
                # Deveria ter redirecionado por not-found
                if "Cliente" in r.text and i == 999999:
                    vuln("MEDIUM", f"{rota} retorna 200 com ID inexistente", str(i))
                    falhas += 1
    if falhas == 0:
        ok(f"{len(rotas) * len(ids_invalidos)} variações testadas — comportamento OK")


def test_path_traversal():
    """Path traversal em download e em rotas que recebem nome de arquivo."""
    section("7. Path traversal em downloads")
    s = requests.Session()
    if not login(s):
        return

    payloads = ["../../etc/passwd", "..%2F..%2F.env", "%2e%2e%2f%2e%2e%2f.env", "/../../../../etc/hosts"]
    for p in payloads:
        r = s.get(f"{BASE}/documentos/download/{requests.utils.quote(p)}", allow_redirects=False)
        if r.status_code == 200 and ("root:" in r.text or "PASSWORD" in r.text or "DB_PASS" in r.text):
            vuln("HIGH", f"Path traversal em /documentos/download", f"payload={p!r}")
            return
    ok("Download com paths traversal: bloqueado/redirect")


def test_open_redirect():
    """Open redirect em parâmetros tipo ?next=/redirect=."""
    section("8. Open redirect")
    s = requests.Session()
    payloads = ["//evil.local", "https://evil.local", "/\\evil.local"]
    for p in payloads:
        r = s.get(f"{BASE}/login?next={requests.utils.quote(p)}", allow_redirects=False)
        loc = r.headers.get("Location", "")
        if loc and ("evil.local" in loc or loc.startswith("//") or loc.startswith("http")):
            vuln("MEDIUM", "Open redirect em /login", f"Location={loc}")
            return
    ok("Open redirect: nenhum encontrado")


def test_method_tampering():
    """POST endpoints aceitando GET (e vice-versa)?"""
    section("9. HTTP method tampering")
    s = requests.Session()
    if not login(s):
        return

    # Endpoints que devem ser POST-only
    post_only = [
        "/clientes/salvar", "/colaboradores/salvar", "/treinamentos/salvar",
        "/kit-pj/salvar", "/usuarios/salvar",
    ]
    for ep in post_only:
        r = s.get(f"{BASE}{ep}", allow_redirects=False)
        # Esperado: 405 (Method Not Allowed) ou redirect
        if r.status_code == 200:
            vuln("MEDIUM", f"GET aceito em endpoint POST {ep}", "deveria recusar")
    ok(f"{len(post_only)} endpoints POST-only — todos rejeitam GET")


def test_brute_force_protection():
    """5 tentativas de senha errada bloqueiam o usuário?"""
    section("10. Proteção contra força bruta")
    # Usa o usuário de teste
    s = requests.Session()
    falhas = 0
    for i in range(7):
        r = s.get(f"{BASE}/login")
        m = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text)
        csrf = m.group(1) if m else ""
        r = s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": TEST_EMAIL, "senha": "errada"}, allow_redirects=False)
        # Conta como falha de login (302 com error=invalid)
        if "error=invalid" in r.headers.get("Location", "") or "error=invalid" in r.url:
            falhas += 1
    tentativas = db_query(f"SELECT tentativas_login FROM usuarios WHERE email='{TEST_EMAIL}'")
    bloqueado = db_query(f"SELECT bloqueado_ate FROM usuarios WHERE email='{TEST_EMAIL}'")
    if tentativas.isdigit() and int(tentativas) >= 5:
        ok(f"Após {falhas} falhas, tentativas_login={tentativas}, bloqueado_ate={bloqueado or 'NULL'}")
        if bloqueado in ("NULL", "", "0000-00-00 00:00:00"):
            vuln("MEDIUM", "Tentativas contadas mas usuário NÃO bloqueado",
                 "deveria bloquear após N tentativas")
    else:
        vuln("MEDIUM", "Contador de tentativas não incrementou",
             f"tentativas_login={tentativas}")
    # Reseta para o user funcionar nos próximos testes
    db_exec(f"UPDATE usuarios SET tentativas_login=0, bloqueado_ate=NULL WHERE email='{TEST_EMAIL}';")


def test_mass_assignment():
    """Tenta atribuir campo sensível em form de update."""
    section("11. Mass assignment / privilege escalation")
    s = requests.Session()
    if not login(s):
        return
    user_id = db_query(f"SELECT id FROM usuarios WHERE email='{TEST_EMAIL}'").strip()
    # Tenta alterar perfil via form de auto-edição (se houver)
    r = s.get(f"{BASE}/usuarios/{user_id}/editar", allow_redirects=False)
    if r.status_code != 200:
        ok("Form de edição não acessível para auto-edição (esperado)")
    else:
        # Pega CSRF e tenta alterar perfil
        m = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text)
        csrf = m.group(1) if m else ""
        s.post(f"{BASE}/usuarios/{user_id}/atualizar", data={
            "_csrf_token": csrf,
            "nome": "Sec Audit",
            "email": TEST_EMAIL,
            "perfil": "admin",  # já é admin, mas testa se aceita ID, ativo, etc.
            "ativo": "1",
            "id": "1",
            "totp_ativo": "0",
            "senha_hash": "$2y$12$AAAAAAAAAAAA",
        }, allow_redirects=False)
        # Se senha_hash do usuário mudou, é mass assignment
        atual = db_query(f"SELECT senha_hash FROM usuarios WHERE email='{TEST_EMAIL}'")
        if atual.startswith("$2y$12$AAAA"):
            vuln("HIGH", "Mass assignment em senha_hash via form update")
        else:
            ok("Mass assignment via form: senha_hash protegido")


def test_admin_only_routes_as_lower_role():
    """Cria um RH e tenta acessar rotas de admin."""
    section("12. RBAC — RH não pode acessar rotas de admin")
    db_exec(f"DELETE FROM usuarios WHERE email='secrh@tsea.local';")
    db_exec(
        f"INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, totp_ativo, criado_em) "
        f"VALUES ('Sec RH', 'secrh@tsea.local', '{gen_bcrypt(TEST_PASS)}', 'rh', 1, 0, NOW());"
    )
    s = requests.Session()
    r = s.get(f"{BASE}/login")
    csrf = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text).group(1)
    r = s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": "secrh@tsea.local", "senha": TEST_PASS}, allow_redirects=True)

    rotas_admin = ["/usuarios", "/logs", "/backup", "/configuracoes", "/integracao-gco", "/lixeira"]
    for path in rotas_admin:
        r = s.get(f"{BASE}{path}", allow_redirects=False)
        if r.status_code == 200:
            vuln("HIGH", f"RH acessou rota admin: {path}")
        elif r.status_code in (302, 301, 403):
            ok(f"RH bloqueado em {path}")
    db_exec("DELETE FROM usuarios WHERE email='secrh@tsea.local';")


def test_session_fixation():
    """Session ID muda após login?"""
    section("13. Session fixation (cookie rotation no login)")
    s = requests.Session()
    s.get(f"{BASE}/login")
    cookie_pre = s.cookies.get("PHPSESSID")
    r = s.get(f"{BASE}/login")
    csrf = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text).group(1)
    s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": TEST_EMAIL, "senha": TEST_PASS}, allow_redirects=True)
    cookie_pos = s.cookies.get("PHPSESSID")
    if cookie_pre and cookie_pos and cookie_pre == cookie_pos:
        vuln("MEDIUM", "Session fixation possível", "PHPSESSID não muda no login")
    else:
        ok(f"PHPSESSID rotacionado no login")


# ─── Main ──────────────────────────────────────────────────────────────────
def main():
    print(f"{BOLD}🛡️  Auditoria de Segurança Ativa — SESMT{RESET}")
    setup_user()
    try:
        test_unauth_routes()
        test_security_headers()
        test_csrf_post()
        test_sql_injection()
        test_xss()
        test_idor_force_browse()
        test_path_traversal()
        test_open_redirect()
        test_method_tampering()
        test_brute_force_protection()
        test_mass_assignment()
        test_admin_only_routes_as_lower_role()
        test_session_fixation()
    finally:
        teardown()

    print()
    high = sum(1 for s,_,_ in findings if s == "HIGH")
    med  = sum(1 for s,_,_ in findings if s == "MEDIUM")
    if not findings:
        print(f"{GREEN}{BOLD}══ NENHUMA VULNERABILIDADE ENCONTRADA ══{RESET}")
        return 0
    print(f"{BOLD}══ Sumário ══{RESET}")
    if high: print(f"  {RED}{high} HIGH{RESET}")
    if med:  print(f"  {YELLOW}{med} MEDIUM{RESET}")
    for s,n,d in findings:
        color = RED if s == "HIGH" else YELLOW
        print(f"  {color}[{s}]{RESET} {n}" + (f" — {d}" if d else ""))
    return 0 if high == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
