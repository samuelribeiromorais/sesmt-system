# SESMT TSE - Guia de Deploy e Manutencao

## Requisitos do Servidor

- PHP 8.2+ com extensoes: pdo_mysql, openssl, mbstring, fileinfo, gd
- MariaDB 10.11+ (ou MySQL 8.0+)
- Apache 2.4+ com mod_rewrite habilitado
- Composer 2.x
- HTTPS (certificado SSL)

## Instalacao

### 1. Copiar arquivos para o servidor

```bash
# Clonar ou copiar o projeto para o servidor
cp -r sesmt-system /var/www/sesmt
cd /var/www/sesmt
```

### 2. Instalar dependencias PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar .env

```bash
cp .env.example .env
nano .env
```

Preencher:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` - credenciais do MariaDB
- `AES_KEY` - gerar chave de 32 caracteres (ex: `openssl rand -hex 16`)
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` - servidor de email TSE
- `APP_URL` - URL do sistema (ex: https://sesmt.tsea.com.br)

### 4. Criar banco de dados e tabelas

```bash
php tools/install.php
```

Ou manualmente:
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p sesmt_tse < database/seed.sql
```

### 5. Configurar Apache

Criar arquivo `/etc/apache2/sites-available/sesmt.conf`:

```apache
<VirtualHost *:443>
    ServerName sesmt.tsea.com.br
    DocumentRoot /var/www/sesmt/public

    <Directory /var/www/sesmt/public>
        AllowOverride All
        Require all granted
    </Directory>

    # IMPORTANTE: Bloquear acesso a pastas fora do public
    <Directory /var/www/sesmt/app>
        Require all denied
    </Directory>
    <Directory /var/www/sesmt/storage>
        Require all denied
    </Directory>
    <Directory /var/www/sesmt/database>
        Require all denied
    </Directory>

    SSLEngine on
    SSLCertificateFile /caminho/cert.pem
    SSLCertificateKeyFile /caminho/key.pem

    ErrorLog ${APACHE_LOG_DIR}/sesmt_error.log
    CustomLog ${APACHE_LOG_DIR}/sesmt_access.log combined
</VirtualHost>

# Redirect HTTP -> HTTPS
<VirtualHost *:80>
    ServerName sesmt.tsea.com.br
    Redirect permanent / https://sesmt.tsea.com.br/
</VirtualHost>
```

```bash
a2ensite sesmt
a2enmod rewrite ssl
systemctl restart apache2
```

### 6. Permissoes de diretorio

```bash
chown -R www-data:www-data /var/www/sesmt/storage
chmod -R 750 /var/www/sesmt/storage
chmod -R 755 /var/www/sesmt/public
```

### 7. Configurar Cron Jobs

```bash
crontab -e
```

Adicionar:
```
0 6 * * * php /var/www/sesmt/cron/check_validades.php >> /var/www/sesmt/storage/logs/cron.log 2>&1
0 7 * * * php /var/www/sesmt/cron/gerar_alertas.php >> /var/www/sesmt/storage/logs/cron.log 2>&1
30 7 * * * php /var/www/sesmt/cron/enviar_emails.php >> /var/www/sesmt/storage/logs/cron.log 2>&1
```

### 8. Primeiro acesso

- URL: https://sesmt.tsea.com.br
- Email: `samuel.morais@tsea.com.br`
- Senha: `TseAdmin@2026`
- **TROCAR A SENHA NO PRIMEIRO ACESSO**

---

## Migracao de Dados do Sistema Antigo

### Exportar dados do localStorage

1. Abrir o sistema antigo no navegador
2. Fazer login
3. Ir em "Funcionarios" > "Exportar dados"
4. Salvar o arquivo JSON

### Importar no sistema novo

```bash
cp funcionarios_exportados.json /var/www/sesmt/tools/funcionarios.json
php /var/www/sesmt/tools/migrar_localstorage.php
```

---

## Backup

### Banco de dados (configurar para a TI)

```bash
# Backup diario do MariaDB
mysqldump -u sesmt_user -p sesmt_tse | gzip > /var/www/sesmt/storage/backups/db_$(date +%Y%m%d).sql.gz
```

### Arquivos (PDFs)

```bash
# Backup dos uploads
tar czf /var/www/sesmt/storage/backups/uploads_$(date +%Y%m%d).tar.gz /var/www/sesmt/storage/uploads/
```

### Cron de backup sugerido

```
0 2 * * * mysqldump -u sesmt_user -p'SENHA' sesmt_tse | gzip > /var/www/sesmt/storage/backups/db_$(date +\%Y\%m\%d).sql.gz
0 3 * * 0 tar czf /var/www/sesmt/storage/backups/uploads_$(date +\%Y\%m\%d).tar.gz /var/www/sesmt/storage/uploads/
# Manter ultimos 30 dias
0 4 * * * find /var/www/sesmt/storage/backups/ -mtime +30 -delete
```

---

## Usuarios do Sistema

| Nome | Email | Perfil | Senha Inicial |
|------|-------|--------|---------------|
| Mariana Toscano Rios | mariana.rios@tsea.com.br | Admin | TseAdmin@2026 |
| Samuel Morais | samuel.morais@tsea.com.br | Admin | TseAdmin@2026 |
| Allyff Sousa | allyff.sousa@tsea.com.br | Admin | TseAdmin@2026 |

### Gerar nova senha

```bash
php tools/gerar_senha.php "NovaSenha123"
```

---

## Perfis de Acesso

| Perfil | Permissoes |
|--------|-----------|
| **Admin** | Tudo + Configuracoes do sistema |
| **SESMT** | Tudo exceto configuracoes (upload, alertas, relatorios, logs, usuarios) |
| **RH** | Somente consulta + download de PDFs |

---

## Estrutura de Diretorios

```
sesmt-system/
├── public/          <- DocumentRoot do Apache (UNICO dir acessivel via web)
├── app/             <- Backend PHP (FORA do DocumentRoot)
├── storage/         <- Uploads, relatorios, logs, backups (FORA do DocumentRoot)
├── database/        <- Scripts SQL
├── cron/            <- Jobs agendados
├── tools/           <- Scripts utilitarios
├── vendor/          <- Dependencias Composer
├── .env             <- Configuracoes sensiveis (NAO versionar)
└── composer.json
```

---

## Troubleshooting

| Problema | Solucao |
|----------|---------|
| Pagina em branco | Verificar logs Apache: `tail -f /var/log/apache2/sesmt_error.log` |
| Erro 500 | Ativar debug: `APP_DEBUG=true` no .env (desativar depois) |
| Login nao funciona | Verificar conexao DB e rodar `php tools/install.php` |
| Emails nao enviam | Verificar SMTP no .env, testar com `php cron/enviar_emails.php` |
| Upload falha | Verificar permissoes: `chown www-data storage/uploads` |
| Certificado PDF em branco | Verificar se images-data.js carrega (F12 no browser) |
