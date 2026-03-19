#!/usr/bin/env python3
"""
Script de importação em massa de documentos do DOSSIÊ para o sistema SESMT.
Apenas documentos vigentes (exclui pastas OBSOLETO/OBSOLETOS).
Apenas colaboradores ativos (que existem no banco).
"""

import os
import re
import json
import hashlib
import shutil
import subprocess
import unicodedata
from datetime import datetime, date

# Paths
BASE_DOSSIE = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\SESMT\1. DOSSIÊ\1. COLABORADORES ATIVOS"
TEMP = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
OUTPUT_SQL = os.path.join(TEMP, 'import_docs.sql')
OUTPUT_COPY = os.path.join(TEMP, 'import_copy.sh')
OUTPUT_MANIFEST = os.path.join(TEMP, 'import_manifest.json')

# ========== LOAD DB COLLABORATORS ==========
colabs_db = {}
with open(os.path.join(TEMP, 'colabs_db.txt'), 'r', encoding='utf-8') as f:
    for line in f:
        line = line.strip()
        if not line:
            continue
        parts = line.split('\t', 1)
        if len(parts) == 2:
            cid = int(parts[0])
            name = parts[1].strip().upper()
            colabs_db[name] = cid

print(f"Loaded {len(colabs_db)} collaborators from DB")

# ========== NAME MATCHING ==========
def normalize(s):
    s = unicodedata.normalize('NFD', s)
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    return s.upper().strip()

def match_name(folder_name):
    fn = normalize(folder_name)
    # Exact match
    for db_name, cid in colabs_db.items():
        if normalize(db_name) == fn:
            return cid
    # Try without middle parts (first + last name)
    fn_parts = fn.split()
    if len(fn_parts) >= 2:
        for db_name, cid in colabs_db.items():
            db_parts = normalize(db_name).split()
            if len(db_parts) >= 2:
                if fn_parts[0] == db_parts[0] and fn_parts[-1] == db_parts[-1]:
                    # Check if it's close enough (at least 60% of words match)
                    common = set(fn_parts) & set(db_parts)
                    if len(common) >= min(len(fn_parts), len(db_parts)) * 0.6:
                        return cid
    return None

# ========== DATE EXTRACTION ==========
MONTHS_PT = {
    'JAN': 1, 'FEV': 2, 'MAR': 3, 'ABR': 4, 'MAI': 5, 'JUN': 6,
    'JUL': 7, 'AGO': 8, 'SET': 9, 'OUT': 10, 'NOV': 11, 'DEZ': 12,
    'JANEIRO': 1, 'FEVEREIRO': 2, 'MARCO': 3, 'ABRIL': 4, 'MAIO': 5,
    'JUNHO': 6, 'JULHO': 7, 'AGOSTO': 8, 'SETEMBRO': 9, 'OUTUBRO': 10,
    'NOVEMBRO': 11, 'DEZEMBRO': 12
}

def extract_date(filename):
    fn = filename.upper()

    # Pattern: DD-MM-YYYY or DD-MM-YY
    m = re.search(r'(\d{1,2})-(\d{1,2})-(\d{4}|\d{2})', fn)
    if m:
        d, mo, y = int(m.group(1)), int(m.group(2)), m.group(3)
        y = int(y)
        if y < 100:
            y += 2000
        if 1 <= mo <= 12 and 1 <= d <= 31:
            try:
                return date(y, mo, d).isoformat()
            except:
                pass
        # Maybe it's DD-MM-YY with reversed order
        if 1 <= d <= 12 and 1 <= mo <= 31:
            try:
                return date(y, d, mo).isoformat()
            except:
                pass

    # Pattern: MM-YYYY or MM-YY (month-year)
    m = re.search(r'(\d{1,2})-(\d{4}|\d{2})(?!\d)', fn)
    if m:
        mo, y = int(m.group(1)), m.group(2)
        y = int(y)
        if y < 100:
            y += 2000
        if 1 <= mo <= 12:
            try:
                return date(y, mo, 1).isoformat()
            except:
                pass

    # Pattern: MONTH YYYY (e.g., "JUL 2025", "AGO 2024")
    m = re.search(r'([A-Z]{3,9})\s*(\d{4})', fn)
    if m:
        month_str = m.group(1)
        y = int(m.group(2))
        mo = MONTHS_PT.get(month_str)
        if mo:
            try:
                return date(y, mo, 1).isoformat()
            except:
                pass

    # Pattern: DD MONTH YYYY (e.g., "06 JAN 2026")
    m = re.search(r'(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})', fn)
    if m:
        d = int(m.group(1))
        month_str = m.group(2)
        y = int(m.group(3))
        mo = MONTHS_PT.get(month_str)
        if mo and 1 <= d <= 31:
            try:
                return date(y, mo, d).isoformat()
            except:
                pass

    return None

# ========== DOCUMENT CLASSIFICATION ==========
def classify_document(filepath, category_folder):
    fn = os.path.basename(filepath).upper()

    if category_folder == '1. ASO':
        if 'PRONTUARIO' in fn or 'KIT' in fn:
            return 8  # Prontuário Médico
        if 'PERIODICO' in fn or 'PERIÓDICO' in fn:
            return 2  # ASO Periódico
        if 'ADMISSIONAL' in fn:
            return 1  # ASO Admissional
        if 'DEMISSIONAL' in fn:
            return 3  # ASO Demissional
        if 'RETORNO' in fn:
            return 4  # ASO Retorno
        if 'MUDANCA' in fn or 'MUD' in fn or 'FUNCAO' in fn or 'FUNÇÃO' in fn:
            return 5  # ASO Mudança de Risco
        if 'ASO' in fn:
            return 2  # Default ASO -> Periódico
        return 2  # Default for ASO folder

    elif category_folder == '2. EPIS' or category_folder == '2. EPI':
        return 6  # Ficha de EPI

    elif category_folder == '3. OS':
        return 7  # Ordem de Serviço

    elif category_folder == '4. TREINAMENTOS':
        if 'ANUENCIA' in fn or 'ANUÊNCIA' in fn:
            if 'NR 10' in fn or 'NR10' in fn or 'NR-10' in fn:
                return 11  # Anuência NR-10
            if 'NR 33' in fn or 'NR33' in fn or 'NR-33' in fn:
                return 12  # Anuência NR-33
            if 'NR 35' in fn or 'NR35' in fn or 'NR-35' in fn:
                return 13  # Anuência NR-35
            return 13  # Default anuência -> NR-35
        if 'DECLARACAO' in fn or 'DECLARAÇÃO' in fn:
            return 9  # Declaração de Treinamentos
        if 'LISTA' in fn and 'PRESENCA' in fn:
            return 10  # Lista de Presença
        # Certificados de treinamento - treat as declaração
        return 9  # Declaração/Certificado de treinamento

    elif category_folder == '5. RH':
        return 14  # Kit Admissional / Outro

    return None

# ========== SCAN AND PROCESS ==========
def is_excluded(path):
    path_upper = path.upper()
    return ('OBSOLETO' in path_upper or
            'EM BRANCO' in path_upper or
            'MODELO' in path_upper or
            'Thumbs.db' in path)

docs_to_import = []
unmatched_folders = []
matched_count = 0
skipped_count = 0

letters = [d for d in os.listdir(BASE_DOSSIE) if os.path.isdir(os.path.join(BASE_DOSSIE, d)) and len(d) <= 2 and d != '1. MODELO PASTA']

for letter in sorted(letters):
    letter_path = os.path.join(BASE_DOSSIE, letter)
    for colab_folder in sorted(os.listdir(letter_path)):
        colab_path = os.path.join(letter_path, colab_folder)
        if not os.path.isdir(colab_path):
            continue

        colab_id = match_name(colab_folder)
        if colab_id is None:
            skipped_count += 1
            continue

        matched_count += 1

        # Scan category folders
        for cat_folder in os.listdir(colab_path):
            cat_path = os.path.join(colab_path, cat_folder)
            if not os.path.isdir(cat_path):
                continue
            if cat_folder not in ['1. ASO', '2. EPIS', '2. EPI', '3. OS', '4. TREINAMENTOS', '5. RH']:
                continue

            # Walk through files (non-recursively for main level, skip OBSOLETO subdirs)
            for root, dirs, files in os.walk(cat_path):
                # Skip excluded directories
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

                    file_date = extract_date(fname)
                    if not file_date:
                        file_date = '2025-01-01'  # Default date if none found

                    file_size = os.path.getsize(fpath)

                    docs_to_import.append({
                        'colab_id': colab_id,
                        'tipo_id': tipo_id,
                        'file_path': fpath,
                        'file_name': fname,
                        'file_size': file_size,
                        'date': file_date,
                    })

print(f"Matched {matched_count} collaborators, skipped {skipped_count} (not in DB)")
print(f"Total documents to import: {len(docs_to_import)}")

# Count by type
type_names = {1:'ASO Adm', 2:'ASO Per', 3:'ASO Dem', 4:'ASO Ret', 5:'ASO Mud',
              6:'EPI', 7:'OS', 8:'Prontuario', 9:'Treinamento', 10:'Lista Presenca',
              11:'Anuencia NR10', 12:'Anuencia NR33', 13:'Anuencia NR35', 14:'RH/Kit'}
type_counts = {}
for d in docs_to_import:
    tn = type_names.get(d['tipo_id'], f"tipo_{d['tipo_id']}")
    type_counts[tn] = type_counts.get(tn, 0) + 1
print("\nBy type:")
for tn, count in sorted(type_counts.items()):
    print(f"  {tn}: {count}")

# ========== GENERATE SQL AND COPY SCRIPT ==========

# Validade mapping (tipo_id -> months)
validade_map = {2: 12, 6: 12, 11: 24, 12: 12, 13: 24}

sql_lines = []
sql_lines.append("-- SESMT Document Import")
sql_lines.append(f"-- Generated: {datetime.now().isoformat()}")
sql_lines.append(f"-- Total documents: {len(docs_to_import)}")
sql_lines.append("")

copy_lines = []
copy_lines.append("#!/bin/bash")
copy_lines.append("# Copy files to Docker container storage")
copy_lines.append("set -e")
copy_lines.append("")

doc_id = 1
for doc in docs_to_import:
    # Generate hash-based filename
    file_hash = hashlib.sha256(f"{doc['colab_id']}_{doc['file_name']}_{doc_id}".encode()).hexdigest()
    safe_name = f"{file_hash}.pdf"
    dest_rel_path = f"{doc['colab_id']}/{safe_name}"

    # Calculate validade
    validade_months = validade_map.get(doc['tipo_id'])
    if validade_months:
        try:
            dt = datetime.strptime(doc['date'], '%Y-%m-%d')
            from dateutil.relativedelta import relativedelta
            validade_dt = dt + relativedelta(months=validade_months)
            data_validade = validade_dt.strftime('%Y-%m-%d')
            days_left = (validade_dt - datetime.now()).days
            if days_left < 0:
                status = 'vencido'
            elif days_left <= 30:
                status = 'proximo_vencimento'
            else:
                status = 'vigente'
        except:
            data_validade = 'NULL'
            status = 'vigente'
    else:
        data_validade = 'NULL'
        status = 'vigente'

    data_val_sql = f"'{data_validade}'" if data_validade != 'NULL' else 'NULL'
    fname_esc = doc['file_name'].replace("'", "\\'").replace("\\", "\\\\")

    sql_lines.append(
        f"INSERT INTO documentos (id, colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, "
        f"arquivo_hash, arquivo_tamanho, data_emissao, data_validade, status, enviado_por) VALUES "
        f"({doc_id}, {doc['colab_id']}, {doc['tipo_id']}, '{fname_esc}', '{dest_rel_path}', "
        f"'{file_hash}', {doc['file_size']}, '{doc['date']}', {data_val_sql}, '{status}', 2);"
    )

    # Copy command - use container path
    src = doc['file_path'].replace("\\", "/")
    copy_lines.append(f'mkdir -p "/tmp/uploads/{doc["colab_id"]}"')
    copy_lines.append(f'cp "{src}" "/tmp/uploads/{doc["colab_id"]}/{safe_name}"')

    doc['dest_path'] = dest_rel_path
    doc['hash'] = file_hash
    doc['safe_name'] = safe_name
    doc['doc_id'] = doc_id
    doc_id += 1

# Write SQL
with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

# Write manifest (for copy operations)
manifest = [{'id': d['doc_id'], 'src': d['file_path'], 'colab_id': d['colab_id'], 'safe_name': d['safe_name']} for d in docs_to_import]
with open(OUTPUT_MANIFEST, 'w', encoding='utf-8') as f:
    json.dump(manifest, f, ensure_ascii=False)

print(f"\nSQL file: {OUTPUT_SQL}")
print(f"Manifest: {OUTPUT_MANIFEST}")
print(f"Total SQL inserts: {doc_id - 1}")
