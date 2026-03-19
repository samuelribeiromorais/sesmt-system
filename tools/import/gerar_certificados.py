#!/usr/bin/env python3
"""
Gera registros de certificados a partir dos documentos de treinamento já importados.
Lê do banco, analisa nomes dos arquivos, e gera SQL para inserir na tabela certificados.
"""

import re, os, subprocess, unicodedata
from datetime import datetime, date
from collections import defaultdict

MONTHS_PT = {'JAN':1,'FEV':2,'MAR':3,'ABR':4,'MAI':5,'JUN':6,'JUL':7,'AGO':8,'SET':9,'OUT':10,'NOV':11,'DEZ':12,
             'JANEIRO':1,'FEVEREIRO':2,'MARCO':3,'ABRIL':4,'MAIO':5,'JUNHO':6,'JULHO':7,'AGOSTO':8,'SETEMBRO':9,'OUTUBRO':10,'NOVEMBRO':11,'DEZEMBRO':12}

# tipo_certificado mapping
NR_MAP = {
    'LOTO': 2,
    'NR 06': 3, 'NR06': 3, 'NR6': 3, 'NR 6': 3,
    'NR 10 BASICO': 4, 'NR10 BASICO': 4, 'NR 10': 4, 'NR10': 4,
    'NR 10 RECICLAGEM': 5, 'NR10 RECICLAGEM': 5, 'NR 10 RECICL': 22, 'NR10 RECICL': 22,
    'NR 10 SEP': 6, 'NR10 SEP': 6, 'NR 10 SEP RECICL': 23,
    'NR 11 MUNK': 7, 'NR11 MUNK': 7, 'NR 11': 7, 'NR11': 7,
    'NR 11 PONTE': 8, 'NR11 PONTE': 8,
    'NR 11 RIGGER': 9, 'NR11 RIGGER': 9,
    'NR 11 SINALEIRO': 10, 'NR11 SINALEIRO': 10,
    'NR 12 RECICL': 25, 'NR12 RECICL': 25,
    'NR 12': 11, 'NR12': 11,
    'NR 18 ANDAIME': 12, 'NR18 ANDAIME': 12,
    'NR 18 PTA': 24, 'NR18 PTA': 24, 'NR 18 PLATAFORMA': 24,
    'NR 18': 13, 'NR18': 13,
    'NR 20': 14, 'NR20': 14,
    'NR 33 SUPERVISOR': 16, 'NR33 SUPERVISOR': 16,
    'NR 33': 17, 'NR33': 17,
    'NR 34 SOLDADOR': 19, 'NR34 SOLDADOR': 19,
    'NR 34 OBSERVADOR': 20, 'NR34 OBSERVADOR': 20,
    'NR 34 QUENTE': 26, 'NR34 QUENTE': 26, 'NR 34 TRABALHO': 26,
    'NR 34': 18, 'NR34': 18,
    'NR 35': 21, 'NR35': 21,
    'DIRECAO DEFENSIVA': 1, 'DIREÇÃO DEFENSIVA': 1, 'DIR DEFENSIVA': 1,
}

VALIDADE_MESES = {
    1:60, 2:12, 3:12, 4:24, 5:24, 6:24, 7:12, 8:12, 9:12, 10:12,
    11:24, 12:12, 13:12, 14:12, 15:12, 16:12, 17:12, 18:12, 19:24,
    20:24, 21:24, 22:24, 23:24, 24:12, 25:24, 26:12,
}

def normalize(s):
    s = unicodedata.normalize('NFD', s)
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn').upper().strip()

def classify_nr(filename):
    fn = normalize(filename)
    # Try most specific first (longer keys)
    for key in sorted(NR_MAP.keys(), key=len, reverse=True):
        nk = normalize(key)
        if nk in fn:
            return NR_MAP[key]
    return None

def extract_cert_date(filename):
    """Extract date from certificate filename, ignoring NR numbers."""
    fn = normalize(filename)
    # Remove NR number prefix to avoid confusion (e.g., "NR 06" being read as day 6)
    fn_clean = re.sub(r'NR[\s-]*\d{1,2}', '', fn)

    # Pattern: MONTH-YEAR or MONTH YEAR (e.g., "DEZ-2025", "MAR 2026")
    m = re.search(r'([A-Z]{3,9})[\s-]*(\d{4})', fn_clean)
    if m:
        mo = MONTHS_PT.get(m.group(1))
        if mo:
            try: return date(int(m.group(2)), mo, 1)
            except: pass

    # Pattern: MM-YYYY (e.g., "12-2025")
    m = re.search(r'(\d{1,2})[\s-](\d{4})', fn_clean)
    if m:
        mo, y = int(m.group(1)), int(m.group(2))
        if 1 <= mo <= 12 and 2020 <= y <= 2030:
            try: return date(y, mo, 1)
            except: pass

    # Pattern: DD-MM-YYYY with actual date
    m = re.search(r'(\d{1,2})-(\d{1,2})-(\d{4})', fn_clean)
    if m:
        d, mo, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if 1 <= mo <= 12 and 1 <= d <= 31:
            try: return date(y, mo, d)
            except: pass

    return None

# Query training documents from DB
print("[1/3] Querying training documents from database...")
result = subprocess.run(
    ['docker', 'exec', 'sesmt-system-db-1', 'mariadb', '-u', 'sesmt', '-psesmt2026', 'sesmt_tse',
     '-N', '-e',
     "SELECT id, colaborador_id, arquivo_nome, data_emissao FROM documentos WHERE tipo_documento_id IN (9,11,12,13) AND status != 'obsoleto'"],
    capture_output=True, text=True, encoding='utf-8'
)

docs = []
for line in result.stdout.strip().split('\n'):
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 4:
        docs.append({
            'doc_id': int(parts[0]),
            'colab_id': int(parts[1]),
            'filename': parts[2],
            'data_emissao': parts[3],
        })

print(f"   Found {len(docs)} training documents")

# Classify and generate certificates
print("[2/3] Classifying certificates...")
certs = []
unclassified = 0
# Track latest cert per (colab, tipo) to avoid duplicates
latest = {}

for doc in docs:
    tipo_id = classify_nr(doc['filename'])
    if tipo_id is None:
        unclassified += 1
        continue

    cert_date = extract_cert_date(doc['filename'])
    if cert_date is None:
        # Use data_emissao from DB as fallback
        try:
            cert_date = datetime.strptime(doc['data_emissao'], '%Y-%m-%d').date()
        except:
            cert_date = date(2025, 1, 1)

    key = (doc['colab_id'], tipo_id)
    # Keep only the latest certificate per (collaborator, type)
    if key not in latest or cert_date > latest[key]['date']:
        latest[key] = {
            'colab_id': doc['colab_id'],
            'tipo_id': tipo_id,
            'date': cert_date,
        }

print(f"   Classified: {len(latest)} unique certificates ({unclassified} unclassified docs)")

# Count by type
type_counts = defaultdict(int)
for cert in latest.values():
    type_counts[cert['tipo_id']] += 1

TYPE_NAMES = {
    1:'Dir.Defensiva', 2:'LOTO', 3:'NR06', 4:'NR10 Bas', 5:'NR10 Recicl', 6:'NR10 SEP',
    7:'NR11 Munk', 8:'NR11 Ponte', 9:'NR11 Rigger', 10:'NR11 Sinaleiro',
    11:'NR12', 12:'NR18 Andaime', 13:'NR18 Geral', 14:'NR20 Unilever', 15:'NR20 Cargill',
    16:'NR33 Sup', 17:'NR33 Trab', 18:'NR34 Geral', 19:'NR34 Soldador', 20:'NR34 Observador',
    21:'NR35', 22:'NR10 Bas Recicl 20h', 23:'NR10 SEP Recicl', 24:'NR18 Plataforma',
    25:'NR12 Recicl', 26:'NR34 Trab.Quente',
}
for tid in sorted(type_counts.keys()):
    print(f"   {TYPE_NAMES.get(tid, f'Tipo {tid}')}: {type_counts[tid]}")

# Generate SQL
print("[3/3] Generating SQL...")
sql_lines = []
sql_lines.append(f"-- Certificate Import from Training Documents")
sql_lines.append(f"-- Generated: {datetime.now().isoformat()}")
sql_lines.append(f"-- Total: {len(latest)} certificates")
sql_lines.append("")

now = datetime.now()
for (colab_id, tipo_id), cert in sorted(latest.items()):
    data_real = cert['date'].isoformat()
    val_meses = VALIDADE_MESES.get(tipo_id, 12)

    # Calculate validade
    val_year = cert['date'].year + (cert['date'].month + val_meses - 1) // 12
    val_month = (cert['date'].month + val_meses - 1) % 12 + 1
    val_day = min(cert['date'].day, 28)
    try:
        data_val = date(val_year, val_month, val_day)
    except:
        data_val = date(val_year, val_month, 1)

    days_left = (data_val - now.date()).days
    if days_left < 0:
        status = 'vencido'
    elif days_left <= 30:
        status = 'proximo_vencimento'
    else:
        status = 'vigente'

    sql_lines.append(
        f"INSERT INTO certificados (colaborador_id, tipo_certificado_id, data_realizacao, "
        f"data_emissao, data_validade, status, criado_por) VALUES "
        f"({colab_id}, {tipo_id}, '{data_real}', '{data_real}', '{data_val.isoformat()}', '{status}', 2);"
    )

TEMP = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
output_file = os.path.join(TEMP, 'import_certificados.sql')
with open(output_file, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"\n   SQL file: {output_file}")
print(f"   Total certificates: {len(latest)}")

# Status summary
status_counts = defaultdict(int)
for (colab_id, tipo_id), cert in latest.items():
    val_meses = VALIDADE_MESES.get(tipo_id, 12)
    val_year = cert['date'].year + (cert['date'].month + val_meses - 1) // 12
    val_month = (cert['date'].month + val_meses - 1) % 12 + 1
    try:
        data_val = date(val_year, val_month, min(cert['date'].day, 28))
    except:
        data_val = date(val_year, val_month, 1)
    days_left = (data_val - now.date()).days
    if days_left < 0: status_counts['vencido'] += 1
    elif days_left <= 30: status_counts['proximo_vencimento'] += 1
    else: status_counts['vigente'] += 1

print(f"\n   Vigentes: {status_counts['vigente']}")
print(f"   Proximo vencimento: {status_counts['proximo_vencimento']}")
print(f"   Vencidos: {status_counts['vencido']}")
