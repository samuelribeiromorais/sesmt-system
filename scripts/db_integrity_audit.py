"""
SESMT — Auditoria de integridade do banco de dados.

Roda dezenas de queries que detectam inconsistências reais:
- Foreign keys órfãs
- Soft-delete em desacordo com referências
- Status de documento que não bate com data_validade
- Certificados sem treinamento, treinamentos sem participantes
- Hashes duplicados (potencial duplicidade)
- Status calculado on-the-fly vs status armazenado
- Etc.

Não faz writes — só leitura. Reporta no console com severidade.
"""
import sys
import subprocess

DB_EXEC = ["docker", "exec", "sesmt-system-db-1", "mariadb", "-u", "sesmt", "-psesmt2026", "sesmt_tse"]


def query(sql):
    r = subprocess.run(DB_EXEC + ["-N", "-e", sql], capture_output=True, text=True, encoding="utf-8")
    return r.stdout.strip()


def first(sql):
    out = query(sql)
    if not out:
        return 0
    return out.split("\n")[0].split("\t")[0]


GREEN = "\033[92m"; RED = "\033[91m"; YELLOW = "\033[93m"; CYAN = "\033[96m"; BOLD = "\033[1m"; RESET = "\033[0m"

issues = []  # (severity, name, count, sample)


def check(name, sql, severity="HIGH", show_sample=True):
    """Roda SQL que deve retornar 0 linhas. Se retornar >0, reporta como issue."""
    out = query(sql)
    if not out.strip():
        print(f"  {GREEN}✓{RESET} {name}")
        return
    lines = out.split("\n")
    n = len(lines)
    sample = lines[0] if show_sample else ""
    color = RED if severity == "HIGH" else YELLOW
    icon = "✗" if severity == "HIGH" else "!"
    print(f"  {color}{icon} {name} — {n} ocorrência(s){RESET}" + (f"  [{sample[:120]}]" if sample else ""))
    issues.append((severity, name, n, sample))


def section(name):
    print(f"\n{CYAN}{BOLD}══ {name} ══{RESET}")


def main():
    print(f"{BOLD}🔬 Auditoria de Integridade do Banco — SESMT{RESET}\n")

    section("1. Foreign keys órfãs")
    check("Documentos com colaborador_id inexistente",
          "SELECT d.id, d.colaborador_id FROM documentos d LEFT JOIN colaboradores c ON d.colaborador_id=c.id WHERE c.id IS NULL")
    check("Documentos com tipo_documento_id inexistente",
          "SELECT d.id, d.tipo_documento_id FROM documentos d LEFT JOIN tipos_documento t ON d.tipo_documento_id=t.id WHERE t.id IS NULL")
    check("Certificados com colaborador_id inexistente",
          "SELECT cert.id, cert.colaborador_id FROM certificados cert LEFT JOIN colaboradores c ON cert.colaborador_id=c.id WHERE c.id IS NULL")
    check("Certificados com tipo_certificado_id inexistente",
          "SELECT cert.id, cert.tipo_certificado_id FROM certificados cert LEFT JOIN tipos_certificado t ON cert.tipo_certificado_id=t.id WHERE t.id IS NULL")
    check("Colaboradores com obra_id inexistente",
          "SELECT c.id, c.obra_id FROM colaboradores c LEFT JOIN obras o ON c.obra_id=o.id WHERE c.obra_id IS NOT NULL AND o.id IS NULL")
    check("Obras com cliente_id inexistente",
          "SELECT o.id, o.cliente_id FROM obras o LEFT JOIN clientes cl ON o.cliente_id=cl.id WHERE cl.id IS NULL")

    section("2. Status de documento vs data_validade")
    check("Documento com data_validade < hoje mas status != 'vencido'",
          "SELECT id, status, data_validade FROM documentos WHERE excluido_em IS NULL AND data_validade IS NOT NULL AND data_validade < CURDATE() AND status NOT IN ('vencido','obsoleto') LIMIT 5",
          severity="MEDIUM")
    check("Documento com data_validade > hoje+30d mas status = 'proximo_vencimento'",
          "SELECT id, status, data_validade FROM documentos WHERE excluido_em IS NULL AND data_validade IS NOT NULL AND data_validade > DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'proximo_vencimento' LIMIT 5",
          severity="MEDIUM")
    check("Documento com data_validade entre hoje e +30d mas status = 'vigente'",
          "SELECT id, status, data_validade FROM documentos WHERE excluido_em IS NULL AND data_validade IS NOT NULL AND data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'vigente' LIMIT 5",
          severity="MEDIUM")

    section("3. Soft-delete coerente")
    check("Certificado vinculado a treinamento soft-deletado",
          "SELECT cert.id, cert.treinamento_id FROM certificados cert JOIN treinamentos t ON cert.treinamento_id=t.id WHERE cert.excluido_em IS NULL AND t.excluido_em IS NOT NULL LIMIT 5",
          severity="MEDIUM")
    check("Documento ativo de colaborador soft-deletado",
          "SELECT d.id, d.colaborador_id FROM documentos d JOIN colaboradores c ON d.colaborador_id=c.id WHERE d.excluido_em IS NULL AND c.excluido_em IS NOT NULL LIMIT 5",
          severity="MEDIUM")
    check("Substituido_por aponta para documento que não existe",
          "SELECT d.id, d.substituido_por FROM documentos d LEFT JOIN documentos d2 ON d.substituido_por=d2.id WHERE d.substituido_por IS NOT NULL AND d2.id IS NULL LIMIT 5")

    section("4. Duplicidades suspeitas")
    check("Hashes de arquivo duplicados (possível upload duplicado)",
          "SELECT arquivo_hash, COUNT(*) AS n FROM documentos WHERE arquivo_hash IS NOT NULL AND excluido_em IS NULL GROUP BY arquivo_hash HAVING n > 1 LIMIT 5",
          severity="LOW")
    check("Múltiplos certificados vigentes do mesmo tipo para o mesmo colaborador",
          "SELECT colaborador_id, tipo_certificado_id, COUNT(*) n FROM certificados WHERE excluido_em IS NULL AND status='vigente' GROUP BY colaborador_id, tipo_certificado_id HAVING n > 1 LIMIT 5",
          severity="LOW")
    check("Colaboradores com CPF duplicado (não-encrypted match)",
          "SELECT cpf_encrypted, COUNT(*) n FROM colaboradores WHERE excluido_em IS NULL AND cpf_encrypted IS NOT NULL GROUP BY cpf_encrypted HAVING n > 1 LIMIT 5",
          severity="MEDIUM")

    section("5. Treinamentos órfãos")
    check("Treinamentos com 0 participantes ativos",
          "SELECT t.id FROM treinamentos t LEFT JOIN certificados c ON t.id=c.treinamento_id AND c.excluido_em IS NULL WHERE t.excluido_em IS NULL GROUP BY t.id HAVING COUNT(c.id) = 0 LIMIT 5",
          severity="LOW")
    check("Treinamentos com tipo_certificado inativo",
          "SELECT t.id FROM treinamentos t JOIN tipos_certificado tc ON t.tipo_certificado_id=tc.id WHERE t.excluido_em IS NULL AND tc.ativo=0 LIMIT 5",
          severity="LOW")
    check("Treinamentos com total_participantes != contagem real",
          "SELECT t.id, t.total_participantes, COUNT(c.id) AS real_count FROM treinamentos t LEFT JOIN certificados c ON t.id=c.treinamento_id AND c.excluido_em IS NULL WHERE t.excluido_em IS NULL GROUP BY t.id, t.total_participantes HAVING t.total_participantes != real_count LIMIT 5",
          severity="LOW")

    section("6. Usuários e segurança")
    check("Usuários ativos sem 2FA (admin/sesmt/rh)",
          "SELECT id, email, perfil FROM usuarios WHERE ativo=1 AND perfil IN ('admin','sesmt','rh') AND totp_ativo=0 LIMIT 10",
          severity="HIGH")
    check("Usuários com tentativas_login >= 5 (possível bloqueio)",
          "SELECT id, email, tentativas_login FROM usuarios WHERE tentativas_login >= 5 LIMIT 5",
          severity="LOW")
    check("Senhas alteradas há mais de 90 dias",
          "SELECT id, email, senha_alterada_em FROM usuarios WHERE ativo=1 AND senha_alterada_em IS NOT NULL AND senha_alterada_em < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 5",
          severity="LOW", show_sample=False)

    section("7. Anomalias de dados")
    check("Documentos com data_emissao > data_validade",
          "SELECT id, data_emissao, data_validade FROM documentos WHERE excluido_em IS NULL AND data_validade IS NOT NULL AND data_emissao > data_validade LIMIT 5")
    check("Documentos com data_emissao no futuro",
          "SELECT id, data_emissao FROM documentos WHERE excluido_em IS NULL AND data_emissao > DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 5",
          severity="MEDIUM")
    check("Colaboradores com data_admissao no futuro",
          "SELECT id, data_admissao FROM colaboradores WHERE excluido_em IS NULL AND data_admissao > CURDATE() LIMIT 5",
          severity="LOW")
    check("Tamanhos de arquivo zerados ou negativos",
          "SELECT id, arquivo_tamanho FROM documentos WHERE excluido_em IS NULL AND (arquivo_tamanho IS NULL OR arquivo_tamanho <= 0) LIMIT 5",
          severity="LOW")

    section("8. Tipos de documento e certificado")
    check("Documentos do tipo 'Kit Admissional' ainda visíveis (deveriam estar soft-deleted)",
          "SELECT d.id FROM documentos d JOIN tipos_documento t ON d.tipo_documento_id=t.id WHERE t.nome='Kit Admissional' AND d.excluido_em IS NULL LIMIT 5",
          severity="HIGH")
    check("Tipos de documento órfãos (sem nenhum documento)",
          "SELECT id, nome FROM tipos_documento WHERE id NOT IN (SELECT DISTINCT tipo_documento_id FROM documentos) AND ativo=1 LIMIT 10",
          severity="LOW")

    section("9. Sessões e logs")
    check("logs_acesso muito antigos (> 1 ano)",
          "SELECT COUNT(*) FROM logs_acesso WHERE criado_em < DATE_SUB(NOW(), INTERVAL 1 YEAR)",
          severity="LOW", show_sample=False)
    n_logs = query("SELECT COUNT(*) FROM logs_acesso")
    print(f"  {CYAN}ℹ Total de registros em logs_acesso: {n_logs}{RESET}")

    section("10. Tamanhos das tabelas")
    print(query(
        "SELECT table_name AS 'Tabela', "
        "table_rows AS 'Linhas', "
        "ROUND((data_length + index_length) / 1024 / 1024, 1) AS 'Tamanho_MB' "
        "FROM information_schema.tables "
        "WHERE table_schema = 'sesmt_tse' AND table_rows > 0 "
        "ORDER BY (data_length + index_length) DESC LIMIT 12"
    ))

    # Sumário
    print()
    high = sum(1 for s,_,_,_ in issues if s == "HIGH")
    med  = sum(1 for s,_,_,_ in issues if s == "MEDIUM")
    low  = sum(1 for s,_,_,_ in issues if s == "LOW")
    if not issues:
        print(f"{GREEN}{BOLD}══ NENHUMA INCONSISTÊNCIA ENCONTRADA ══{RESET}")
        return 0
    print(f"{BOLD}══ Sumário ══{RESET}")
    if high: print(f"  {RED}{high} HIGH{RESET}")
    if med:  print(f"  {YELLOW}{med} MEDIUM{RESET}")
    if low:  print(f"  {CYAN}{low} LOW{RESET}")
    return 0 if high == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
