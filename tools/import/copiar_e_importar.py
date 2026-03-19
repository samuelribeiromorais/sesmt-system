#!/usr/bin/env python3
"""
Copia PDFs vigentes do DOSSIÊ para storage/uploads/ e gera SQL de importação.
Roda direto no host Windows.
"""

import os
import re
import json
import hashlib
import shutil
import unicodedata
from datetime import datetime, date
from dateutil.relativedelta import relativedelta

# Paths
BASE_DOSSIE = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\SESMT\1. DOSSIÊ\1. COLABORADORES ATIVOS"
STORAGE_UPLOADS = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\sesmt-system\storage\uploads"
TEMP = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
OUTPUT_SQL = os.path.join(TEMP, 'import_docs.sql')

# Load DB collaborators
colabs_db = {}
with open(os.path.join(TEMP, 'colabs_db.txt'), 'r', encoding='utf-8') as f:
    for line in f:
        line = line.strip()
        if not line:
            continue
        parts = line.split('\t', 1)
        if len(parts) == 2:
            colabs_db[parts[1].strip().upper()] = int(parts[0])

print(f"[1/5] Loaded {len(colabs_db)} collaborators from DB")

# Name matching
def normalize(s):
    s = unicodedata.normalize('NFD', s)
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn').upper().strip()

def match_name(folder_name):
    fn = normalize(folder_name)
    for db_name, cid in colabs_db.items():
        if normalize(db_name) == fn:
            return cid
    fn_parts = fn.split()
    if len(fn_parts) >= 2:
        for db_name, cid in colabs_db.items():
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
    # DD-MM-YYYY or DD-MM-YY
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
    # MM-YYYY or MM-YY
    m = re.search(r'(\d{1,2})-(\d{4}|\d{2})(?!\d)', fn)
    if m:
        mo, y = int(m.group(1)), int(m.group(2))
        if y < 100: y += 2000
        if 1 <= mo <= 12:
            try: return date(y, mo, 1).isoformat()
            except: pass
    # DD MONTH YYYY
    m = re.search(r'(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})', fn)
    if m:
        d, month_str, y = int(m.group(1)), m.group(2), int(m.group(3))
        mo = MONTHS_PT.get(month_str)
        if mo and 1 <= d <= 31:
            try: return date(y, mo, d).isoformat()
            except: pass
    # MONTH YYYY
    m = re.search(r'([A-Z]{3,9})\s*(\d{4})', fn)
    if m:
        mo = MONTHS_PT.get(m.group(1))
        if mo:
            try: return date(int(m.group(2)), mo, 1).isoformat()
            except: pass
    return None

# Document classification
def classify_document(filepath, cat_folder):
    fn = os.path.basename(filepath).upper()
    if cat_folder.startswith('1'):  # ASO
        if 'PRONTUARIO' in fn or 'KIT' in fn: return 8
        if 'PERIODICO' in fn or 'PERIÓDICO' in fn: return 2
        if 'ADMISSIONAL' in fn: return 1
        if 'DEMISSIONAL' in fn: return 3
        if 'RETORNO' in fn: return 4
        if 'MUD' in fn or 'FUNCAO' in fn or 'FUNÇÃO' in fn or 'MUDANCA' in fn: return 5
        return 2
    elif cat_folder.startswith('2'):  # EPI
        return 6
    elif cat_folder.startswith('3'):  # OS
        return 7
    elif cat_folder.startswith('4'):  # TREINAMENTOS
        if 'ANUENCIA' in fn or 'ANUÊNCIA' in fn:
            if 'NR 10' in fn or 'NR10' in fn or 'NR-10' in fn: return 11
            if 'NR 33' in fn or 'NR33' in fn or 'NR-33' in fn: return 12
            if 'NR 35' in fn or 'NR35' in fn or 'NR-35' in fn: return 13
            return 13
        if 'DECLARACAO' in fn or 'DECLARAÇÃO' in fn: return 9
        if 'LISTA' in fn and 'PRESENCA' in fn: return 10
        return 9
    elif cat_folder.startswith('5'):  # RH
        return 14
    return None

def is_excluded(path):
    p = path.upper()
    return 'OBSOLETO' in p or 'EM BRANCO' in p or 'MODELO' in p or 'Thumbs' in path

# Validade mapping
validade_map = {2: 12, 6: 12, 11: 24, 12: 12, 13: 24}

# ========== SCAN ==========
print("[2/5] Scanning DOSSIÊ folders...")
docs = []
matched = 0

letters = [d for d in os.listdir(BASE_DOSSIE) if os.path.isdir(os.path.join(BASE_DOSSIE, d)) and d not in ['1. MODELO PASTA']]
for letter in sorted(letters):
    letter_path = os.path.join(BASE_DOSSIE, letter)
    for colab_folder in sorted(os.listdir(letter_path)):
        colab_path = os.path.join(letter_path, colab_folder)
        if not os.path.isdir(colab_path):
            continue
        colab_id = match_name(colab_folder)
        if colab_id is None:
            continue
        matched += 1

        for cat_folder in os.listdir(colab_path):
            cat_path = os.path.join(colab_path, cat_folder)
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
                    docs.append({
                        'colab_id': colab_id,
                        'tipo_id': tipo_id,
                        'src': fpath,
                        'fname': fname,
                        'size': os.path.getsize(fpath),
                        'date': file_date,
                    })

print(f"   Matched {matched} collaborators, found {len(docs)} documents")

# ========== COPY FILES ==========
print(f"[3/5] Copying {len(docs)} PDFs to storage/uploads/...")
sql_lines = []
sql_lines.append(f"-- SESMT Document Import - {len(docs)} documents")
sql_lines.append(f"-- Generated: {datetime.now().isoformat()}")
sql_lines.append("")

copied = 0
errors = 0
for i, doc in enumerate(docs):
    # Create destination directory
    dest_dir = os.path.join(STORAGE_UPLOADS, str(doc['colab_id']))
    os.makedirs(dest_dir, exist_ok=True)

    # Generate hash-based filename
    file_hash = hashlib.sha256(open(doc['src'], 'rb').read()).hexdigest()
    safe_name = f"{file_hash}.pdf"
    dest_path = os.path.join(dest_dir, safe_name)
    rel_path = f"{doc['colab_id']}/{safe_name}"

    # Copy file (skip if already exists with same hash)
    if not os.path.exists(dest_path):
        try:
            shutil.copy2(doc['src'], dest_path)
            copied += 1
        except Exception as e:
            errors += 1
            continue
    else:
        copied += 1  # Already exists

    # Calculate validade and status
    val_months = validade_map.get(doc['tipo_id'])
    if val_months:
        try:
            dt = datetime.strptime(doc['date'], '%Y-%m-%d')
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
    fname_esc = doc['fname'].replace("'", "''")

    sql_lines.append(
        f"INSERT INTO documentos (colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, "
        f"arquivo_hash, arquivo_tamanho, data_emissao, data_validade, status, enviado_por) VALUES "
        f"({doc['colab_id']}, {doc['tipo_id']}, '{fname_esc}', '{rel_path}', "
        f"'{file_hash}', {doc['size']}, '{doc['date']}', {data_val_sql}, '{status}', 2);"
    )

    if (i + 1) % 500 == 0:
        print(f"   ... {i+1}/{len(docs)} processed")

print(f"   Copied: {copied}, Errors: {errors}")

# ========== WRITE SQL ==========
print(f"[4/5] Writing SQL ({len(sql_lines)-2} inserts)...")
with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))
print(f"   SQL file: {OUTPUT_SQL}")

# Summary
type_names = {1:'ASO Admissional',2:'ASO Periodico',3:'ASO Demissional',4:'ASO Retorno',5:'ASO Mud. Funcao',
              6:'Ficha EPI',7:'Ordem de Servico',8:'Prontuario',9:'Treinamento/Certificado',10:'Lista Presenca',
              11:'Anuencia NR-10',12:'Anuencia NR-33',13:'Anuencia NR-35',14:'RH/Kit'}
counts = {}
for d in docs:
    k = type_names.get(d['tipo_id'], '?')
    counts[k] = counts.get(k, 0) + 1

print(f"\n[5/5] Summary:")
for k, v in sorted(counts.items()):
    print(f"   {k}: {v}")
print(f"\n   TOTAL: {len(docs)} documents ready for DB import")
