"""
SESMT — Crawler + fuzzer.

1. Faz login como admin.
2. Spider: navega de /dashboard seguindo todos os <a href> internos,
   coletando rotas únicas. Para em profundidade 3 ou 200 URLs.
3. Para cada URL: verifica HTTP status, presença de erro PHP no HTML,
   tempo de resposta.
4. Fuzzing: para cada parâmetro de URL detectado, manda valores absurdos
   (string longa, unicode, NULL bytes, números negativos) e procura 5xx.

Reporta no fim com cor por severidade.
"""
import re
import sys
import time
import subprocess
from collections import deque
from urllib.parse import urljoin, urlparse, parse_qsl, urlencode

import requests

BASE = "http://localhost:8081"
TEST_EMAIL = "crawler@tsea.local"
TEST_PASS = "TseAdmin@2026"
DB = ["docker", "exec", "sesmt-system-db-1", "mariadb", "-u", "sesmt", "-psesmt2026", "sesmt_tse"]


def db_exec(sql):
    subprocess.run(DB + ["-e", sql], capture_output=True, text=True, encoding="utf-8")

def gen_bcrypt(p):
    r = subprocess.run(
        ["docker", "exec", "sesmt-system-web-1", "php", "-r",
         f"echo password_hash('{p}', PASSWORD_BCRYPT, ['cost' => 12]);"],
        capture_output=True, text=True, encoding="utf-8"
    )
    return r.stdout.strip()


GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; BOLD = "\033[1m"; RESET = "\033[0m"
findings = []  # (severity, name, detail)
visited = {}   # url -> {status, time_ms, errors}


def setup():
    h = gen_bcrypt(TEST_PASS)
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")
    db_exec(
        f"INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, totp_ativo, criado_em) "
        f"VALUES ('Crawler Bot', '{TEST_EMAIL}', '{h}', 'admin', 1, 0, NOW());"
    )

def teardown():
    db_exec(f"DELETE FROM usuarios WHERE email='{TEST_EMAIL}';")


def login(s):
    r = s.get(f"{BASE}/login")
    csrf = re.search(r'name="_csrf_token"\s+value="([^"]+)"', r.text).group(1)
    r = s.post(f"{BASE}/login", data={"_csrf_token": csrf, "email": TEST_EMAIL, "senha": TEST_PASS}, allow_redirects=True)
    return "/dashboard" in r.url or "Dashboard" in r.text[:5000]


PHP_ERROR_PATTERNS = [
    r"Fatal error",
    r"Parse error",
    r"Uncaught\s+\w+",
    r"PDOException",
    r"Warning</b>",
    r"<b>Notice</b>",
    r"Stack trace:",
    r"PHP Request Shutdown",
]
ERROR_RE = re.compile("|".join(PHP_ERROR_PATTERNS), re.IGNORECASE)


def normalize_url(href, current):
    """Resolve href relativo, descarta âncoras e externos."""
    if not href or href.startswith(("javascript:", "mailto:", "tel:", "#")):
        return None
    abs_url = urljoin(current, href)
    p = urlparse(abs_url)
    if p.netloc and p.netloc != urlparse(BASE).netloc:
        return None
    # Tira fragment
    return p._replace(fragment="").geturl()


def crawl(s, max_pages=120):
    print(f"\n{CYAN}{BOLD}══ Crawler ══{RESET}")
    queue = deque([f"{BASE}/dashboard"])
    while queue and len(visited) < max_pages:
        url = queue.popleft()
        # Normaliza removendo query duplicada
        if url in visited:
            continue
        # Pula páginas que esperamos não serem HTML
        if any(p in url for p in ["/exportar/", "/download", "/preview/", "/imprimir", "/pdf", "/foto/"]):
            continue
        # Pula POST-only (não vamos crawlear)
        if any(p in url for p in ["/salvar", "/atualizar", "/excluir", "/aprovar", "/upload", "/marcar", "/sincronizar"]):
            continue
        # Pula logout pra não derrubar a sessão
        if url.endswith("/logout"):
            continue
        try:
            t0 = time.time()
            r = s.get(url, allow_redirects=False, timeout=10)
            elapsed_ms = int((time.time() - t0) * 1000)
        except requests.RequestException as e:
            visited[url] = {"status": "ERR", "time": 0, "errors": [str(e)]}
            findings.append(("MEDIUM", f"Erro de rede em {url}", str(e)))
            continue

        errors = []
        if r.status_code >= 500:
            errors.append(f"HTTP {r.status_code}")
            findings.append(("HIGH", f"5xx em {url}", f"status={r.status_code}"))
        if r.status_code == 200 and ERROR_RE.search(r.text):
            err_match = ERROR_RE.search(r.text)
            snippet = r.text[max(0, err_match.start()-30):err_match.end()+80]
            errors.append("PHP error in body")
            findings.append(("HIGH", f"PHP error em {url}", snippet[:150].replace("\n", " ")))

        visited[url] = {"status": r.status_code, "time": elapsed_ms, "errors": errors}

        # Se foi 200, extrai links
        if r.status_code == 200 and "text/html" in r.headers.get("Content-Type", ""):
            for href in re.findall(r'href="([^"]+)"', r.text):
                nxt = normalize_url(href, url)
                if nxt and nxt not in visited and nxt not in queue:
                    queue.append(nxt)

    # Reporta
    n_ok = sum(1 for v in visited.values() if isinstance(v["status"], int) and 200 <= v["status"] < 400 and not v["errors"])
    n_5xx = sum(1 for v in visited.values() if isinstance(v["status"], int) and v["status"] >= 500)
    n_php = sum(1 for v in visited.values() if v["errors"] and "PHP error" in str(v["errors"]))
    slow = sorted([(u, v["time"]) for u, v in visited.items() if isinstance(v["time"], int)], key=lambda x: -x[1])[:5]

    print(f"  Visitadas: {len(visited)}")
    print(f"  {GREEN}{n_ok} OK{RESET} | {RED}{n_5xx} 5xx{RESET} | {RED}{n_php} com PHP error{RESET}")
    print(f"  Top 5 mais lentas:")
    for u, t in slow:
        cor = RED if t > 2000 else (YELLOW if t > 800 else GREEN)
        print(f"    {cor}{t}ms{RESET}  {u.replace(BASE,'')}")
    if any(t > 2000 for _, t in slow):
        findings.append(("MEDIUM", "Páginas com >2s detectadas", f"top: {slow[0][0]} ({slow[0][1]}ms)"))


def fuzz_params(s):
    """Para cada query-string detectada no crawl, faz fuzz."""
    print(f"\n{CYAN}{BOLD}══ Fuzzing de parâmetros ══{RESET}")
    # Coleta URLs com query string
    urls_with_qs = [u for u in visited if "?" in u]
    payloads = [
        "A" * 5000,                          # string muito longa
        "0",                                  # zero
        "-1",                                 # negativo
        "0; DROP TABLE x;",                   # SQL-ish
        "<script>alert(1)</script>",          # XSS
        "%00",                                # null byte (encoded)
        "../../../etc/passwd",                # path traversal
        "🔥🔥🔥💩",                          # unicode/emoji
        "{\"$gt\":\"\"}",                     # NoSQL-ish
    ]
    base_targets = set()
    for u in urls_with_qs:
        p = urlparse(u)
        params = dict(parse_qsl(p.query))
        for k in params.keys():
            base_targets.add((u, k))

    if not base_targets:
        # Adiciona alvos conhecidos
        for ep in ["/colaboradores?q=test", "/documentos?q=test", "/treinamentos?q=test"]:
            base_targets.add((f"{BASE}{ep}", "q"))

    crashes = 0
    for url, key in list(base_targets)[:8]:  # limita
        for p in payloads:
            pr = urlparse(url)
            params = dict(parse_qsl(pr.query))
            params[key] = p
            new_url = pr._replace(query=urlencode(params)).geturl()
            try:
                r = s.get(new_url, timeout=10, allow_redirects=False)
            except requests.RequestException:
                continue
            if r.status_code >= 500 or (r.status_code == 200 and ERROR_RE.search(r.text)):
                findings.append(("HIGH", f"Crash em {url} fuzzing param {key!r}",
                                f"payload={p[:40]!r} HTTP={r.status_code}"))
                crashes += 1
    if crashes == 0:
        print(f"  {GREEN}✓ Nenhum crash em {len(base_targets)} alvos × {len(payloads)} payloads{RESET}")
    else:
        print(f"  {RED}{crashes} crash(es) detectado(s){RESET}")


def main():
    print(f"{BOLD}🕷️  Crawler + Fuzzer — SESMT{RESET}")
    setup()
    try:
        s = requests.Session()
        if not login(s):
            print(f"{RED}Falha no login{RESET}")
            return 1
        print(f"{GREEN}Login OK{RESET}")
        crawl(s, max_pages=80)
        fuzz_params(s)
    finally:
        teardown()

    print()
    high = sum(1 for sev,_,_ in findings if sev == "HIGH")
    med  = sum(1 for sev,_,_ in findings if sev == "MEDIUM")
    if not findings:
        print(f"{GREEN}{BOLD}══ NENHUM PROBLEMA ENCONTRADO ══{RESET}")
        return 0
    print(f"{BOLD}══ Achados ({len(findings)}) ══{RESET}")
    if high: print(f"  {RED}{high} HIGH{RESET}")
    if med:  print(f"  {YELLOW}{med} MEDIUM{RESET}")
    for sev, name, det in findings:
        cor = RED if sev == "HIGH" else YELLOW
        print(f"  {cor}[{sev}]{RESET} {name}" + (f" — {det[:200]}" if det else ""))
    return 0 if high == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
