"""
SESMT — Suíte completa de auditoria (orquestrador).

Roda em sequência:
1. PHPUnit (271 testes)
2. Smoke test (45 checks E2E read-only)
3. Integration test (23 checks CRUD)
4. DB integrity audit (ML inconsistências)
5. Security audit (13 categorias de vulnerabilidades)
6. Crawler + fuzzer (80+ páginas + payloads maliciosos)

Imprime relatório consolidado no fim. Exit code != 0 se algo crítico.
"""
import subprocess
import sys
import time

GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; BOLD = "\033[1m"; RESET = "\033[0m"

suites = [
    ("PHPUnit (Unit + Feature + Security)",
     ["docker", "exec", "sesmt-system-web-1", "sh", "-c", "cd /var/www/html && php vendor/bin/phpunit --testdox-text /tmp/phpunit.txt 2>&1 | tail -3"]),
    ("Smoke test (E2E read-only)",
     [sys.executable, "-X", "utf8", "scripts/smoke_test.py"]),
    ("Integration test (E2E CRUD)",
     [sys.executable, "-X", "utf8", "scripts/integration_test.py"]),
    ("DB integrity audit",
     [sys.executable, "-X", "utf8", "scripts/db_integrity_audit.py"]),
    ("Security audit (13 categorias)",
     [sys.executable, "-X", "utf8", "scripts/security_audit.py"]),
    ("Crawler + fuzzer",
     [sys.executable, "-X", "utf8", "scripts/crawler_fuzzer.py"]),
]


def run_suite(name, cmd):
    print(f"\n{CYAN}{BOLD}═══════════════════════════════════════════════════")
    print(f"  ▶ {name}")
    print(f"═══════════════════════════════════════════════════{RESET}")
    t0 = time.time()
    try:
        env = {"PYTHONIOENCODING": "utf-8"}
        import os
        full_env = {**os.environ, **env}
        r = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", env=full_env, timeout=600)
    except subprocess.TimeoutExpired:
        print(f"{RED}TIMEOUT após 10min{RESET}")
        return ("TIMEOUT", 0, "")
    elapsed = time.time() - t0

    out = (r.stdout or "") + (r.stderr or "")
    print(out)

    # Heurística para classificar: se exit != 0 ou achou texto-chave
    if r.returncode != 0:
        return ("FAIL", elapsed, out)
    if "FALHAS" in out.upper() and "0 FALHAS" not in out.upper():
        return ("FAIL", elapsed, out)
    if "VULNERABILIDADE ENCONTRADA" in out.upper() or "INCONSISTÊNCIA" in out.upper() and "NENHUMA" not in out.upper():
        # Avisos não fatais
        if "HIGH" in out:
            return ("WARN_HIGH", elapsed, out)
    return ("OK", elapsed, out)


def main():
    print(f"{BOLD}{CYAN}╔════════════════════════════════════════════════════════════╗")
    print(f"║         SESMT — Auditoria Completa do Sistema           ║")
    print(f"╚════════════════════════════════════════════════════════════╝{RESET}")

    results = []
    for name, cmd in suites:
        status, elapsed, _ = run_suite(name, cmd)
        results.append((name, status, elapsed))

    # Sumário final
    print(f"\n{BOLD}{CYAN}╔════════════════════════════════════════════════════════════╗")
    print(f"║                       SUMÁRIO FINAL                       ║")
    print(f"╚════════════════════════════════════════════════════════════╝{RESET}")
    falhas = 0
    for name, status, elapsed in results:
        if status == "OK":
            cor, icon = GREEN, "✓"
        elif status == "WARN_HIGH":
            cor, icon = YELLOW, "!"
        else:
            cor, icon = RED, "✗"
            falhas += 1
        print(f"  {cor}{icon}{RESET} {name:<55} {status:<12} ({elapsed:.1f}s)")

    print()
    if falhas == 0:
        print(f"{GREEN}{BOLD}TODAS AS SUÍTES PASSARAM ✓{RESET}")
        return 0
    print(f"{RED}{BOLD}{falhas} SUÍTE(S) COM FALHA{RESET}")
    return 1


if __name__ == "__main__":
    sys.exit(main())
