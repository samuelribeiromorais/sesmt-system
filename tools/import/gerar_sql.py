import json, os

tmpdir = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
with open(os.path.join(tmpdir, 'ativos.json'), 'r', encoding='utf-8') as f:
    records = json.load(f)

def esc(s):
    return s.replace("'", "\\'").replace("\\", "\\\\") if s else ''

# Client definitions: id, razao_social, nome_fantasia, cnpj
clients = [
    (1, 'Unilever Brasil Industrial Ltda', 'Unilever', '01.615.814/0001-35'),
    (2, 'Cargill Agricola S.A.', 'Cargill', '60.498.706/0001-60'),
    (3, 'Nestle Brasil Ltda', 'Nestle', '60.409.075/0001-52'),
    (4, 'Bunge Alimentos S.A.', 'Bunge', '84.046.101/0001-93'),
    (5, 'ADM do Brasil Ltda', 'ADM', '02.003.402/0001-75'),
    (6, 'Biolab Sanus Farmaceutica Ltda', 'Biolab', '49.475.833/0001-06'),
    (7, 'Inpasa Agroindustrial S.A.', 'Inpasa', '02.882.940/0001-20'),
    (8, 'Lesaffre do Brasil Ltda', 'Lesaffre', '60.657.617/0001-69'),
    (9, 'Softys Brasil Ltda', 'Softys', '14.738.032/0001-07'),
    (10, 'TSE Automacao Industrial Ltda', 'TSE', '05.148.152/0002-55'),
    (11, 'BCM Metro', 'BCM Metro', None),
]

# Obra definitions: site_key, client_id, nome, local_obra
obras = [
    ('UNILEVER - AGUAI', 1, 'Unilever Aguai', 'Aguai - SP'),
    ('CARGILL - ANAPOLIS', 2, 'Cargill Anapolis', 'Anapolis - GO'),
    ('CARGILL - BARREIRAS', 2, 'Cargill Barreiras', 'Barreiras - BA'),
    ('CARGILL - CACHOEIRA DO SUL - RS', 2, 'Cargill Cachoeira do Sul', 'Cachoeira do Sul - RS'),
    ('CARGILL - GOIANIA', 2, 'Cargill Goiania', 'Goiania - GO'),
    ('CARGILL - GOIANIRA', 2, 'Cargill Goianira', 'Goianira - GO'),
    ('CARGILL - PORTO NACIONAL - TO', 2, 'Cargill Porto Nacional', 'Porto Nacional - TO'),
    ('CARGILL - PRIMAVERA DO LESTE', 2, 'Cargill Primavera do Leste', 'Primavera do Leste - MT'),
    ('CARGILL - UBERLANDIA', 2, 'Cargill Uberlandia', 'Uberlandia - MG'),
    ('NESTLE - ARARAS', 3, 'Nestle Araras', 'Araras - SP'),
    ('NESTLE - GOIANIA', 3, 'Nestle Goiania', 'Goiania - GO'),
    ('NESTLE - IBIA - MG', 3, 'Nestle Ibia', 'Ibia - MG'),
    ('NESTLE - ITUIUTABA MG', 3, 'Nestle Ituiutaba', 'Ituiutaba - MG'),
    ('NESTLE - RIBEIRAO PRETO', 3, 'Nestle Ribeirao Preto', 'Ribeirao Preto - SP'),
    ('NESTLE - VILA VELHA - ES', 3, 'Nestle Vila Velha', 'Vila Velha - ES'),
    ('BUNGE - LEM - BA', 4, 'Bunge LEM', 'Luis Eduardo Magalhaes - BA'),
    ('BUNGE - NOVA MUTUM - MT', 4, 'Bunge Nova Mutum', 'Nova Mutum - MT'),
    ('BUNGE - RIO GRANDE RS', 4, 'Bunge Rio Grande', 'Rio Grande - RS'),
    ('BUNGE - RONDONOPOLIS - MT', 4, 'Bunge Rondonopolis', 'Rondonopolis - MT'),
    ('BUNGE - URUCUI - PI', 4, 'Bunge Urucui', 'Urucui - PI'),
    ('ADM - CAMPO GRANDE', 5, 'ADM Campo Grande', 'Campo Grande - MS'),
    ('BIOLAB - POUSO ALEGRE', 6, 'Biolab Pouso Alegre', 'Pouso Alegre - MG'),
    ('INPASA - LEM BA', 7, 'Inpasa LEM', 'Luis Eduardo Magalhaes - BA'),
    ('LESAFFRE - NARANDIBA SP', 8, 'Lesaffre Narandiba', 'Narandiba - SP'),
    ('SOFTYS - SENADOR CANEDO GO', 9, 'Softys Senador Canedo', 'Senador Canedo - GO'),
    ('TSE - GOIANIA', 10, 'TSE Goiania', 'Goiania - GO'),
    ('TSE - FAZENDA AVELINOPOLIS', 10, 'TSE Fazenda Avelinopolis', 'Avelinopolis - GO'),
    ('TSE - FAZENDA GUAPO', 10, 'TSE Fazenda Guapo', 'Guapo - GO'),
    ('TSE - SOFTWARE - GO', 10, 'TSE Software GO', 'Goiania - GO'),
    ('TSE - SOFTWARE - SP', 10, 'TSE Software SP', 'Vinhedo - SP'),
    ('TSE - VENDAS TSE GO', 10, 'TSE Vendas GO', 'Goiania - GO'),
    ('TSE - VINHEDO', 10, 'TSE Vinhedo', 'Vinhedo - SP'),
]

# Build obra_map: site_key -> (obra_id, client_id)
obra_map = {}
for i, (site_key, cid, nome, local) in enumerate(obras, 1):
    obra_map[site_key] = (i, cid)

# Handle encoding variants for NESTLE CACAPAVA and BCM METRO
def find_obra(site):
    if site in obra_map:
        return obra_map[site]
    # Fuzzy match for encoding issues
    if 'NESTLE' in site and 'CA' in site and 'APAVA' in site:
        return obra_map.get('NESTLE - ARARAS', (None, None))  # Will handle separately
    if 'BCM' in site:
        return (None, 11)  # BCM Metro client
    return (None, None)

# Add NESTLE CACAPAVA as obra
cacapava_obra_id = len(obras) + 1
obras.append(('NESTLE - CACAPAVA', 3, 'Nestle Cacapava', 'Cacapava - SP'))
obra_map_extra = {}

# Generate SQL
lines = []
lines.append('-- =============================================')
lines.append('-- SESMT - Cadastro Completo de Dados Reais')
lines.append('-- =============================================')
lines.append('')
lines.append('SET FOREIGN_KEY_CHECKS = 0;')
lines.append('')
lines.append('-- 1. Limpar dados de teste')
lines.append('DELETE FROM certificados;')
lines.append('DELETE FROM colaboradores;')
lines.append('DELETE FROM obras;')
lines.append('DELETE FROM clientes;')
lines.append('ALTER TABLE certificados AUTO_INCREMENT = 1;')
lines.append('ALTER TABLE colaboradores AUTO_INCREMENT = 1;')
lines.append('ALTER TABLE obras AUTO_INCREMENT = 1;')
lines.append('ALTER TABLE clientes AUTO_INCREMENT = 1;')
lines.append('')
lines.append('SET FOREIGN_KEY_CHECKS = 1;')
lines.append('')

# Clients
lines.append('-- 2. Clientes')
for cid, razao, fantasia, cnpj in clients:
    cnpj_sql = f"'{cnpj}'" if cnpj else 'NULL'
    lines.append(f"INSERT INTO clientes (id, razao_social, nome_fantasia, cnpj, ativo) VALUES ({cid}, '{esc(razao)}', '{esc(fantasia)}', {cnpj_sql}, 1);")
lines.append('')

# Obras
lines.append('-- 3. Obras/Sites')
for i, (site_key, cid, nome, local) in enumerate(obras, 1):
    lines.append(f"INSERT INTO obras (id, cliente_id, nome, local_obra, status) VALUES ({i}, {cid}, '{esc(nome)}', '{esc(local)}', 'ativa');")

# Also add BCM METRO obra
bcm_obra_id = len(obras) + 1
lines.append(f"INSERT INTO obras (id, cliente_id, nome, local_obra, status) VALUES ({bcm_obra_id}, 11, 'BCM Metro DF', 'Brasilia - DF', 'ativa');")
lines.append('')

# Update obra_map with CACAPAVA and BCM
# Find the CACAPAVA site string from records
for r in records:
    if 'NESTLE' in r['s'] and 'CA' in r['s'] and 'APAVA' in r['s']:
        obra_map[r['s']] = (cacapava_obra_id, 3)
        break
for r in records:
    if 'BCM' in r['s']:
        obra_map[r['s']] = (bcm_obra_id, 11)
        break

# Colaboradores
lines.append('-- 4. Colaboradores ativos')
for i, r in enumerate(records, 1):
    nome = esc(r['n'])
    matricula = esc(r['m'])
    cargo = esc(r['c'])
    site = r['s']

    cargo_sql = f"'{cargo}'" if cargo else 'NULL'
    funcao_sql = cargo_sql

    # Determine setor
    cl = cargo.lower()
    if any(x in cl for x in ['montador', 'auxiliar', 'eletricista', 'soldador', 'pedreiro', 'encarregado', 'mestre', 'andaime', 'operador']):
        setor = 'Producao'
    elif any(x in cl for x in ['administrativo', 'financeiro', 'contabilidade', 'controladoria', 'comercial']):
        setor = 'Administrativo'
    elif 'seguran' in cl:
        setor = 'SESMT'
    elif any(x in cl for x in ['software', 'ti']):
        setor = 'TI'
    elif any(x in cl for x in ['projetista', 'planejamento']):
        setor = 'Engenharia'
    elif 'supervisor' in cl:
        setor = 'Supervisao'
    elif 'almoxarifado' in cl:
        setor = 'Almoxarifado'
    elif 'servi' in cl:
        setor = 'Servicos Gerais'
    elif 'jovem' in cl:
        setor = 'Administrativo'
    elif 'eng' in cl:
        setor = 'Engenharia'
    else:
        setor = ''
    setor_sql = f"'{setor}'" if setor else 'NULL'

    # Find obra/client
    obra_id = 'NULL'
    client_id = 'NULL'
    if site in obra_map:
        oid, cid = obra_map[site]
        obra_id = str(oid)
        client_id = str(cid)

    lines.append(f"INSERT INTO colaboradores (id, nome_completo, matricula, cargo, funcao, setor, cliente_id, obra_id, status, unidade) VALUES ({i}, '{nome}', '{matricula}', {cargo_sql}, {funcao_sql}, {setor_sql}, {client_id}, {obra_id}, 'ativo', 'Goiania');")

lines.append('')
lines.append(f'-- Total: {len(records)} colaboradores, {len(obras)+1} obras, {len(clients)} clientes')

outpath = os.path.join(tmpdir, 'cadastro.sql')
with open(outpath, 'w', encoding='utf-8') as f:
    f.write('\n'.join(lines))

print(f'SQL saved to: {outpath}')
print(f'{len(records)} colaboradores, {len(obras)+1} obras, {len(clients)} clientes')
