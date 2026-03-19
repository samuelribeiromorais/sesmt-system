#!/usr/bin/env python3
"""
Extrai dados de colaboradores dos ASOs (PDFs com texto digital).
Atualiza cargo, setor e CPF dos colaboradores que têm esses campos vazios.
"""

import os, re, subprocess, unicodedata, hashlib
import pdfplumber
from collections import defaultdict

BASE = r"C:\Users\SamuelMorais\OneDrive - TSE ENERGIA E AUTOMACAO LTDA\Área de Trabalho\SESMT\1. DOSSIÊ\1. COLABORADORES ATIVOS"
TEMP = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))

def normalize(s):
    s = unicodedata.normalize('NFD', s)
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn').upper().strip()

# ========== 1. LOAD DB ==========
print("[1/4] Loading DB collaborators...")
result = subprocess.run(
    ['docker', 'exec', 'sesmt-system-db-1', 'mariadb', '-u', 'sesmt', '-psesmt2026', 'sesmt_tse',
     '-N', '-e',
     "SELECT id, nome_completo, cargo, setor, cpf_encrypted FROM colaboradores WHERE (cargo IS NULL OR cargo = '' OR setor IS NULL OR setor = '')"],
    capture_output=True, text=True, encoding='utf-8'
)

need_data = {}
for line in result.stdout.strip().split('\n'):
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 2:
        cid = int(parts[0])
        nome = parts[1].strip()
        cargo = parts[2].strip() if len(parts) > 2 and parts[2] != 'NULL' else ''
        setor = parts[3].strip() if len(parts) > 3 and parts[3] != 'NULL' else ''
        cpf = parts[4].strip() if len(parts) > 4 and parts[4] != 'NULL' else ''
        need_data[normalize(nome)] = {
            'id': cid,
            'nome': nome,
            'has_cargo': bool(cargo),
            'has_setor': bool(setor),
            'has_cpf': bool(cpf),
        }

print(f"   {len(need_data)} collaborators need data")

# ========== 2. SCAN ASO PDFs ==========
print("[2/4] Scanning ASO PDFs for text...")

def extract_aso_data(pdf_path):
    """Extract data fields from a text-based ASO PDF."""
    try:
        with pdfplumber.open(pdf_path) as pdf:
            text = ''
            for page in pdf.pages[:3]:
                t = page.extract_text()
                if t:
                    text += t + '\n'
            if len(text.strip()) < 50:
                return None  # Scanned image

            data = {}

            # CPF
            m = re.search(r'CPF[:\s]*([\d]{3}\.[\d]{3}\.[\d]{3}-[\d]{2})', text)
            if m:
                data['cpf'] = m.group(1).replace('.', '').replace('-', '')

            # Cargo
            m = re.search(r'CARGO[:\s]*(.+?)(?:\s+Idade|\s*\n)', text, re.IGNORECASE)
            if m:
                cargo = m.group(1).strip()
                cargo = re.sub(r'\s+', ' ', cargo)
                if len(cargo) > 3 and 'não' not in cargo.lower():
                    data['cargo'] = cargo

            # Setor
            m = re.search(r'SETOR[:\s]*(.+?)(?:\s*\n)', text, re.IGNORECASE)
            if m:
                setor = m.group(1).strip()
                setor = re.sub(r'\s+', ' ', setor)
                # Clean up setor codes like "27-GO ELETRICA" -> "Eletrica"
                setor = re.sub(r'^\d+[-\s]*[A-Z]{2}\s*[-\s]*', '', setor).strip()
                if len(setor) > 1:
                    data['setor'] = setor.title()

            # Nome (for matching)
            m = re.search(r'NOME[:\s]*(.+?)(?:\s*\n)', text, re.IGNORECASE)
            if m:
                data['nome'] = m.group(1).strip()

            return data if data else None
    except:
        return None

extracted = 0
updates = {}
scanned = 0
total_pdfs = 0

letters = [d for d in os.listdir(BASE) if os.path.isdir(os.path.join(BASE, d)) and d != '1. MODELO PASTA']
for letter in sorted(letters):
    letter_path = os.path.join(BASE, letter)
    for colab_folder in os.listdir(letter_path):
        colab_path = os.path.join(letter_path, colab_folder)
        if not os.path.isdir(colab_path):
            continue

        norm_name = normalize(colab_folder)
        if norm_name not in need_data:
            continue

        aso_path = None
        for sub in os.listdir(colab_path):
            if sub.startswith('1') and 'ASO' in sub.upper():
                aso_path = os.path.join(colab_path, sub)
                break
        if not aso_path or not os.path.isdir(aso_path):
            continue

        # Find newest PDF in ASO folder (skip OBSOLETO)
        pdfs = []
        for root, dirs, files in os.walk(aso_path):
            dirs[:] = [d for d in dirs if 'OBSOLETO' not in d.upper()]
            if 'OBSOLETO' in root.upper():
                continue
            for f in files:
                if f.lower().endswith('.pdf') and 'Thumbs' not in f:
                    pdfs.append(os.path.join(root, f))

        for pdf_file in pdfs:
            total_pdfs += 1
            data = extract_aso_data(pdf_file)
            if data and len(data) > 0:
                cid = need_data[norm_name]['id']
                if cid not in updates:
                    updates[cid] = {'nome': need_data[norm_name]['nome']}

                # Only update fields that are missing
                if 'cargo' in data and not need_data[norm_name]['has_cargo']:
                    updates[cid]['cargo'] = data['cargo']
                if 'setor' in data and not need_data[norm_name]['has_setor']:
                    updates[cid]['setor'] = data['setor']
                if 'cpf' in data and not need_data[norm_name]['has_cpf']:
                    updates[cid]['cpf'] = data['cpf']

                extracted += 1
                break  # One good extraction per collaborator is enough
            else:
                scanned += 1

    print(f"   ... letter {letter}: {extracted} extracted so far")

print(f"   Total ASO PDFs checked: {total_pdfs}")
print(f"   Text extracted: {extracted}")
print(f"   Scanned (no text): {scanned}")

# ========== 3. DETERMINE SETOR FROM CARGO ==========
print("[3/4] Determining setor from cargo...")

def cargo_to_setor(cargo):
    cl = cargo.lower()
    if any(x in cl for x in ['montador', 'auxiliar de montagem', 'eletricista', 'soldador', 'pedreiro',
                               'encarregado', 'mestre', 'andaime', 'operador', 'caldeireiro',
                               'funileiro', 'instrumentista', 'mecanico', 'mecânico', 'pintor',
                               'serralheiro', 'rigger', 'ajudante']):
        return 'Producao'
    elif any(x in cl for x in ['administrativo', 'financeiro', 'contabilidade', 'comercial', 'compras',
                                 'faturamento', 'rh', 'recursos']):
        return 'Administrativo'
    elif 'seguran' in cl:
        return 'SESMT'
    elif any(x in cl for x in ['software', ' ti ', 'sistema', 'programador', 'desenvolvedor']):
        return 'TI'
    elif any(x in cl for x in ['projetista', 'planejamento', 'engenheiro', 'engenharia']):
        return 'Engenharia'
    elif 'supervisor' in cl:
        return 'Supervisao'
    elif 'almoxarif' in cl:
        return 'Almoxarifado'
    elif 'servi' in cl and 'gerais' in cl:
        return 'Servicos Gerais'
    elif 'motorista' in cl:
        return 'Logistica'
    elif 'jovem' in cl or 'aprendiz' in cl:
        return 'Administrativo'
    return ''

for cid, data in updates.items():
    if 'cargo' in data and 'setor' not in data:
        setor = cargo_to_setor(data['cargo'])
        if setor:
            data['setor'] = setor

# ========== 4. GENERATE SQL ==========
print("[4/4] Generating SQL updates...")
sql_lines = []
sql_lines.append("-- ASO Data Extraction Updates")
sql_lines.append(f"-- Collaborators updated: {len(updates)}")
sql_lines.append("")

for cid, data in updates.items():
    parts = []
    if 'cargo' in data:
        parts.append(f"cargo = '{data['cargo'].replace(chr(39), chr(39)+chr(39))}'")
        parts.append(f"funcao = '{data['cargo'].replace(chr(39), chr(39)+chr(39))}'")
    if 'setor' in data:
        parts.append(f"setor = '{data['setor'].replace(chr(39), chr(39)+chr(39))}'")
    if parts:
        sql_lines.append(f"UPDATE colaboradores SET {', '.join(parts)} WHERE id = {cid};")

output = os.path.join(TEMP, 'update_aso_data.sql')
with open(output, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"\n   Updates generated: {len([u for u in updates.values() if any(k in u for k in ['cargo','setor'])])}")
print(f"   SQL file: {output}")

# Summary
cargo_count = sum(1 for u in updates.values() if 'cargo' in u)
setor_count = sum(1 for u in updates.values() if 'setor' in u)
cpf_count = sum(1 for u in updates.values() if 'cpf' in u)
print(f"\n   Fields filled:")
print(f"     Cargo: {cargo_count}")
print(f"     Setor: {setor_count}")
print(f"     CPF: {cpf_count}")
