#!/usr/bin/env python3
"""
Cadastra colaboradores faltantes (que têm pasta no DOSSIÊ mas não estão no banco)
e importa seus documentos vigentes.
"""

import os, re, json, hashlib, shutil, unicodedata
from datetime import datetime, date
from dateutil.relativedelta import relativedelta

BASE = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\SESMT\1. DOSSIÊ\1. COLABORADORES ATIVOS"
STORAGE = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\sesmt-system\storage\uploads"
TEMP = os.environ.get('TEMP', '')
OUTPUT_SQL = os.path.join(TEMP, 'import_faltantes.sql')

# Load existing DB names
db_names = {}
with open(os.path.join(TEMP, 'colabs_db.txt'), 'r', encoding='utf-8') as f:
    for line in f:
        parts = line.strip().split('\t', 1)
        if len(parts) == 2:
            db_names[parts[1].strip().upper()] = int(parts[0])

def normalize(s):
    s = unicodedata.normalize('NFD', s)
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn').upper().strip()

def match_name(folder_name):
    fn = normalize(folder_name)
    for db_name, cid in db_names.items():
        if normalize(db_name) == fn:
            return cid
    fn_parts = fn.split()
    if len(fn_parts) >= 2:
        for db_name, cid in db_names.items():
            db_parts = normalize(db_name).split()
            if len(db_parts) >= 2 and fn_parts[0] == db_parts[0] and fn_parts[-1] == db_parts[-1]:
                common = set(fn_parts) & set(db_parts)
                if len(common) >= min(len(fn_parts), len(db_parts)) * 0.6:
                    return cid
    return None

# Date extraction
MONTHS_PT = {'JAN':1,'FEV':2,'MAR':3,'ABR':4,'MAI':5,'JUN':6,'JUL':7,'AGO':8,'SET':9,'OUT':10,'NOV':11,'DEZ':12,
             'JANEIRO':1,'FEVEREIRO':2,'MARCO':3,'ABRIL':4,'MAIO':5,'JUNHO':6,'JULHO':7,'AGOSTO':8,'SETEMBRO':9,'OUTUBRO':10,'NOVEMBRO':11,'DEZEMBRO':12}

def extract_date(filename):
    fn = filename.upper()
    m = re.search(r'(\d{1,2})-(\d{1,2})-(\d{4}|\d{2})', fn)
    if m:
        d, mo, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if y < 100: y += 2000
        if 1 <= mo <= 12 and 1 <= d <= 31:
            try: return date(y, mo, d).isoformat()
            except: pass
        if 1 <= d <= 12 and 1 <= mo <= 31:
            try: return date(y, d, mo).isoformat()
            except: pass
    m = re.search(r'(\d{1,2})-(\d{4}|\d{2})(?!\d)', fn)
    if m:
        mo, y = int(m.group(1)), int(m.group(2))
        if y < 100: y += 2000
        if 1 <= mo <= 12:
            try: return date(y, mo, 1).isoformat()
            except: pass
    m = re.search(r'(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})', fn)
    if m:
        d, month_str, y = int(m.group(1)), m.group(2), int(m.group(3))
        mo = MONTHS_PT.get(month_str)
        if mo and 1 <= d <= 31:
            try: return date(y, mo, d).isoformat()
            except: pass
    m = re.search(r'([A-Z]{3,9})\s*(\d{4})', fn)
    if m:
        mo = MONTHS_PT.get(m.group(1))
        if mo:
            try: return date(int(m.group(2)), mo, 1).isoformat()
            except: pass
    return None

def classify_document(filepath, cat_folder):
    fn = os.path.basename(filepath).upper()
    if cat_folder.startswith('1'):
        if 'PRONTUARIO' in fn or 'KIT' in fn: return 8
        if 'PERIODICO' in fn or 'PERIÓDICO' in fn: return 2
        if 'ADMISSIONAL' in fn: return 1
        if 'DEMISSIONAL' in fn: return 3
        if 'RETORNO' in fn: return 4
        if 'MUD' in fn or 'FUNCAO' in fn or 'FUNÇÃO' in fn or 'MUDANCA' in fn: return 5
        return 2
    elif cat_folder.startswith('2'): return 6
    elif cat_folder.startswith('3'): return 7
    elif cat_folder.startswith('4'):
        if 'ANUENCIA' in fn or 'ANUÊNCIA' in fn:
            if 'NR 10' in fn or 'NR10' in fn or 'NR-10' in fn: return 11
            if 'NR 33' in fn or 'NR33' in fn or 'NR-33' in fn: return 12
            if 'NR 35' in fn or 'NR35' in fn or 'NR-35' in fn: return 13
            return 13
        if 'DECLARACAO' in fn or 'DECLARAÇÃO' in fn: return 9
        if 'LISTA' in fn and 'PRESENCA' in fn: return 10
        return 9
    elif cat_folder.startswith('5'): return 14
    return None

def is_excluded(path):
    p = path.upper()
    return 'OBSOLETO' in p or 'EM BRANCO' in p or 'MODELO' in p or 'Thumbs' in path

validade_map = {2: 12, 6: 12, 11: 24, 12: 12, 13: 24}

# ========== FIND UNMATCHED FOLDERS ==========
print("[1/4] Finding unmatched collaborator folders...")
unmatched = []
letters = [d for d in os.listdir(BASE) if os.path.isdir(os.path.join(BASE, d)) and d != '1. MODELO PASTA']
for letter in sorted(letters):
    lp = os.path.join(BASE, letter)
    for name in sorted(os.listdir(lp)):
        fp = os.path.join(lp, name)
        if os.path.isdir(fp) and match_name(name) is None:
            unmatched.append((name, fp))

print(f"   {len(unmatched)} collaborators to add")

# ========== GENERATE SQL FOR NEW COLLABORATORS + DOCS ==========
print("[2/4] Generating SQL and copying files...")

# Next collaborator ID
max_id = max(db_names.values())
next_colab_id = max_id + 1

sql_lines = []
sql_lines.append(f"-- Import {len(unmatched)} new collaborators + their documents")
sql_lines.append(f"-- Generated: {datetime.now().isoformat()}")
sql_lines.append("")
sql_lines.append("-- New collaborators")

# Map new IDs
new_colabs = {}
for folder_name, folder_path in unmatched:
    cid = next_colab_id
    new_colabs[folder_name] = cid
    nome_esc = folder_name.replace("'", "''")
    sql_lines.append(
        f"INSERT INTO colaboradores (id, nome_completo, status, unidade) "
        f"VALUES ({cid}, '{nome_esc}', 'ativo', 'Goiania');"
    )
    next_colab_id += 1

sql_lines.append("")
sql_lines.append("-- Documents for new collaborators")

doc_count = 0
copy_count = 0
errors = 0

for folder_name, folder_path in unmatched:
    colab_id = new_colabs[folder_name]

    for cat_folder in os.listdir(folder_path):
        cat_path = os.path.join(folder_path, cat_folder)
        if not os.path.isdir(cat_path) or not cat_folder[0].isdigit():
            continue
        for root, dirs, files in os.walk(cat_path):
            dirs[:] = [d for d in dirs if not is_excluded(d)]
            if is_excluded(root):
                continue
            for fname in files:
                if not fname.lower().endswith('.pdf'):
                    continue
                fpath = os.path.join(root, fname)
                if is_excluded(fpath):
                    continue
                tipo_id = classify_document(fpath, cat_folder)
                if tipo_id is None:
                    continue

                file_date = extract_date(fname) or '2025-01-01'

                # Copy file
                dest_dir = os.path.join(STORAGE, str(colab_id))
                os.makedirs(dest_dir, exist_ok=True)
                file_hash = hashlib.sha256(open(fpath, 'rb').read()).hexdigest()
                safe_name = f"{file_hash}.pdf"
                dest_path = os.path.join(dest_dir, safe_name)
                rel_path = f"{colab_id}/{safe_name}"

                if not os.path.exists(dest_path):
                    try:
                        shutil.copy2(fpath, dest_path)
                        copy_count += 1
                    except:
                        errors += 1
                        continue
                else:
                    copy_count += 1

                # Calculate validade
                val_months = validade_map.get(tipo_id)
                if val_months:
                    try:
                        dt = datetime.strptime(file_date, '%Y-%m-%d')
                        val_dt = dt + relativedelta(months=val_months)
                        data_val = val_dt.strftime('%Y-%m-%d')
                        days_left = (val_dt - datetime.now()).days
                        status = 'vencido' if days_left < 0 else ('proximo_vencimento' if days_left <= 30 else 'vigente')
                    except:
                        data_val = None
                        status = 'vigente'
                else:
                    data_val = None
                    status = 'vigente'

                data_val_sql = f"'{data_val}'" if data_val else 'NULL'
                fname_esc = fname.replace("'", "''")
                file_size = os.path.getsize(fpath)

                sql_lines.append(
                    f"INSERT INTO documentos (colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, "
                    f"arquivo_hash, arquivo_tamanho, data_emissao, data_validade, status, enviado_por) VALUES "
                    f"({colab_id}, {tipo_id}, '{fname_esc}', '{rel_path}', "
                    f"'{file_hash}', {file_size}, '{file_date}', {data_val_sql}, '{status}', 2);"
                )
                doc_count += 1

    if (new_colabs[folder_name] - max_id) % 200 == 0:
        print(f"   ... {new_colabs[folder_name] - max_id}/{len(unmatched)} collaborators processed")

print(f"[3/4] Files copied: {copy_count}, Errors: {errors}")
print(f"[4/4] Writing SQL...")

with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"\nResults:")
print(f"   New collaborators: {len(unmatched)}")
print(f"   New documents: {doc_count}")
print(f"   SQL file: {OUTPUT_SQL}")
