#!/usr/bin/env python3
"""
Atualiza colaboradores: apenas os da planilha VinculoColaboradorSiteObra ficam ativos.
Atualiza matricula, cargo, site/obra. Marca todos os demais como inativos.
"""

import pandas as pd
import re, os, unicodedata, subprocess
from collections import defaultdict

EXCEL = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\SESMT\VinculoColaboradorSiteObra18_03_2026_141551.xlsx"
TEMP = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))

# ========== 1. READ EXCEL ==========
print("[1/5] Reading Excel...")
df = pd.read_excel(EXCEL)
print(f"   {len(df)} rows")

# Parse "MATRICULA - NOME" from Colaborador column
records = []
for _, row in df.iterrows():
    colab_str = str(row['Colaborador']).strip()
    m = re.match(r'([\d\-]+)\s*-\s*(.+)', colab_str)
    if m:
        matricula = m.group(1).strip()
        nome = m.group(2).strip()
    else:
        matricula = ''
        nome = colab_str

    cargo_str = str(row['Cargo']).strip() if pd.notna(row['Cargo']) else ''
    # Remove code prefix like "00277 - ", "2000 - "
    cm = re.match(r'[\d]+\s*-\s*(.+)', cargo_str)
    cargo = cm.group(1).strip() if cm else cargo_str
    if 'não encontrado' in cargo.lower() or cargo == '-':
        cargo = ''

    site = str(row['Site Obra Ativo']).strip() if pd.notna(row['Site Obra Ativo']) else ''
    if site == 'SEM VÍNCULO' or site == 'SEM V\xcdNCULO' or 'SEM V' in site.upper():
        site = ''

    records.append({
        'matricula': matricula,
        'nome': nome,
        'cargo': cargo,
        'site': site,
    })

print(f"   Parsed {len(records)} collaborators")

# ========== 2. LOAD DB COLLABORATORS ==========
print("[2/5] Loading DB collaborators...")
result = subprocess.run(
    ['docker', 'exec', 'sesmt-system-db-1', 'mariadb', '-u', 'sesmt', '-psesmt2026', 'sesmt_tse',
     '-N', '-e', "SELECT id, nome_completo, matricula FROM colaboradores"],
    capture_output=True, text=True, encoding='utf-8'
)

db_colabs = {}
for line in result.stdout.strip().split('\n'):
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 2:
        cid = int(parts[0])
        nome = parts[1].strip()
        mat = parts[2].strip() if len(parts) > 2 and parts[2].strip() != 'NULL' else ''
        db_colabs[cid] = {'nome': nome, 'matricula': mat}

print(f"   {len(db_colabs)} collaborators in DB")

# ========== 3. LOAD OBRAS ==========
result2 = subprocess.run(
    ['docker', 'exec', 'sesmt-system-db-1', 'mariadb', '-u', 'sesmt', '-psesmt2026', 'sesmt_tse',
     '-N', '-e', "SELECT id, cliente_id, nome FROM obras"],
    capture_output=True, text=True, encoding='utf-8'
)

obras_db = {}
for line in result2.stdout.strip().split('\n'):
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 3:
        obras_db[int(parts[0])] = {'cliente_id': int(parts[1]), 'nome': parts[2].strip()}

# Build site name -> (obra_id, cliente_id) map
# Site names from Excel: "CARGILL - PORTO NACIONAL - TO", "NESTLE - CAÇAPAVA", etc.
# Obra names in DB: "Cargill Porto Nacional", "Nestle Cacapava", etc.
def normalize(s):
    s = unicodedata.normalize('NFD', s)
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn').upper().strip()

site_map = {}
for oid, obra in obras_db.items():
    site_map[normalize(obra['nome'])] = (oid, obra['cliente_id'])

def find_obra(site_name):
    if not site_name:
        return None, None
    sn = normalize(site_name)
    # Direct match
    for key, val in site_map.items():
        if key == sn:
            return val
    # Fuzzy: check if key words overlap
    sn_words = set(sn.replace('-', ' ').split())
    best_match = None
    best_score = 0
    for key, val in site_map.items():
        key_words = set(key.replace('-', ' ').split())
        common = sn_words & key_words
        if len(common) >= 2 and len(common) > best_score:
            best_score = len(common)
            best_match = val
    return best_match if best_match else (None, None)

# ========== 4. MATCH EXCEL -> DB ==========
print("[3/5] Matching collaborators...")

def match_name(nome_excel):
    ne = normalize(nome_excel)
    # Exact match
    for cid, data in db_colabs.items():
        if normalize(data['nome']) == ne:
            return cid
    # First + last name
    ne_parts = ne.split()
    if len(ne_parts) >= 2:
        for cid, data in db_colabs.items():
            db_parts = normalize(data['nome']).split()
            if len(db_parts) >= 2 and ne_parts[0] == db_parts[0] and ne_parts[-1] == db_parts[-1]:
                common = set(ne_parts) & set(db_parts)
                if len(common) >= min(len(ne_parts), len(db_parts)) * 0.6:
                    return cid
    return None

matched_ids = set()
sql_lines = []
sql_lines.append(f"-- Update collaborators from VinculoColaboradorSiteObra Excel")
sql_lines.append(f"-- {len(records)} active collaborators")
sql_lines.append("")

unmatched = []
for rec in records:
    cid = match_name(rec['nome'])
    if cid is None:
        unmatched.append(rec['nome'])
        continue

    matched_ids.add(cid)

    obra_id, cliente_id = find_obra(rec['site'])

    parts = []
    parts.append(f"status = 'ativo'")
    if rec['matricula']:
        parts.append(f"matricula = '{rec['matricula'].replace(chr(39), chr(39)+chr(39))}'")
    if rec['cargo']:
        parts.append(f"cargo = '{rec['cargo'].replace(chr(39), chr(39)+chr(39))}'")
        parts.append(f"funcao = '{rec['cargo'].replace(chr(39), chr(39)+chr(39))}'")

        # Determine setor from cargo
        cl = rec['cargo'].lower()
        setor = ''
        if any(x in cl for x in ['montador', 'auxiliar', 'eletricista', 'soldador', 'pedreiro', 'encarregado', 'mestre', 'andaime', 'operador', 'caldeireiro', 'funileiro', 'instrumentista', 'mecanico', 'mecânico']):
            setor = 'Producao'
        elif any(x in cl for x in ['administrativo', 'financeiro', 'contabilidade', 'controladoria', 'comercial', 'compras', 'faturamento', 'rh']):
            setor = 'Administrativo'
        elif 'seguran' in cl:
            setor = 'SESMT'
        elif any(x in cl for x in ['software', 'ti', 'sistema']):
            setor = 'TI'
        elif any(x in cl for x in ['projetista', 'planejamento']):
            setor = 'Engenharia'
        elif 'supervisor' in cl:
            setor = 'Supervisao'
        elif 'almoxarifado' in cl or 'almoxarife' in cl:
            setor = 'Almoxarifado'
        elif 'servi' in cl:
            setor = 'Servicos Gerais'
        elif 'jovem' in cl or 'aprendiz' in cl:
            setor = 'Administrativo'
        elif 'eng' in cl:
            setor = 'Engenharia'
        elif 'motorista' in cl:
            setor = 'Logistica'
        if setor:
            parts.append(f"setor = '{setor}'")

    if obra_id:
        parts.append(f"obra_id = {obra_id}")
    if cliente_id:
        parts.append(f"cliente_id = {cliente_id}")

    sql_lines.append(f"UPDATE colaboradores SET {', '.join(parts)} WHERE id = {cid};")

# Mark all others as inactive
sql_lines.append("")
sql_lines.append(f"-- Mark {len(db_colabs) - len(matched_ids)} non-matched collaborators as inactive")
if matched_ids:
    ids_str = ','.join(str(i) for i in sorted(matched_ids))
    sql_lines.append(f"UPDATE colaboradores SET status = 'inativo' WHERE id NOT IN ({ids_str});")

print(f"   Matched: {len(matched_ids)}/{len(records)}")
print(f"   Unmatched: {len(unmatched)}")
if unmatched[:10]:
    for n in unmatched[:10]:
        print(f"     - {n}")
    if len(unmatched) > 10:
        print(f"     ... e mais {len(unmatched) - 10}")

# ========== 5. WRITE SQL ==========
print("[4/5] Writing SQL...")
output = os.path.join(TEMP, 'atualizar_ativos.sql')
with open(output, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"   SQL file: {output}")
print(f"\n[5/5] Summary:")
print(f"   Active (matched): {len(matched_ids)}")
print(f"   Inactive (rest): {len(db_colabs) - len(matched_ids)}")
print(f"   Unmatched from Excel: {len(unmatched)}")
