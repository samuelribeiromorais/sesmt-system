# SESMT TSE - Guia de Deploy

## Primeira Instalacao

No servidor (Debian com Apache, acesso root SSH):

```bash
git clone https://github.com/samuelribeiromorais/sesmt-system.git /opt/sesmt-system
cd /opt/sesmt-system
chmod +x deploy.sh
sudo ./deploy.sh instalar
```

O script faz tudo sozinho:
- Instala Docker, Apache, Git
- Clona o repositorio
- Gera .env com senhas seguras
- Sobe os containers (web + banco + backup)
- Importa o schema + dump de producao
- Configura Apache como reverse proxy

## Importar Dados

Se o banco subiu vazio, importe o dump:

```bash
# Copiar o dump para o servidor
scp dump_sesmt.sql root@SERVIDOR:/tmp/

# No servidor:
cd /opt/sesmt-system
sudo ./deploy.sh importar /tmp/dump_sesmt.sql
```

## Transferir Documentos (PDFs)

Os PDFs dos colaboradores nao estao no GitHub (21 GB).
Transfira o conteudo da pasta `storage/uploads/` separadamente:

```bash
# No servidor, apos receber o ZIP:
cd /opt/sesmt-system/storage/
unzip uploads.zip
chown -R www-data:www-data uploads/
chmod -R 750 uploads/
```

## Atualizar Sistema

Quando houver novas versoes no GitHub:

```bash
cd /opt/sesmt-system
sudo ./deploy.sh atualizar
```

Isso puxa o codigo novo e reconstroi o container web.
O banco de dados NAO e alterado.

## Verificar Status

```bash
cd /opt/sesmt-system
sudo ./deploy.sh status
```

## Comandos Uteis

```bash
# Ver logs do sistema web
docker logs sesmt-web --tail 50

# Ver logs do banco
docker logs sesmt-db --tail 50

# Acessar banco manualmente
docker exec -it sesmt-db mariadb -u sesmt -pSENHA sesmt_tse

# Reiniciar containers
cd /opt/sesmt-system
docker-compose -f docker-compose.prod.yml restart

# Backup manual
docker exec sesmt-db mariadb-dump -u sesmt -pSENHA sesmt_tse > backup_manual.sql
```

## Credenciais Padrao

- **URL:** https://sesmt.tsea.com.br
- **Login:** samuel.morais@tsea.com.br
- **Senha:** TseAdmin@2026
- **Senha do banco:** ver no arquivo `/opt/sesmt-system/.env`
