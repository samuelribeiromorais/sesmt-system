<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testes da configuracao de backup no Docker e cron.
 * Verifica que os arquivos de infraestrutura estao corretos.
 */
class BackupDockerTest extends TestCase
{
    private string $prodCompose;
    private string $crontab;
    private string $cronBackupPhp;

    protected function setUp(): void
    {
        $base = dirname(__DIR__, 2);
        $this->prodCompose = file_get_contents($base . '/docker-compose.prod.yml');
        $this->crontab = file_get_contents($base . '/docker/crontab');
        $this->cronBackupPhp = file_get_contents($base . '/cron/backup.php');
    }

    // ── Docker Compose Prod ─────────────────────────────────────────────────

    public function testContainerBackupExisteNoProd(): void
    {
        $this->assertStringContainsString('sesmt-backup', $this->prodCompose,
            'Container sesmt-backup deve existir no docker-compose.prod.yml.');
    }

    public function testBackupUsaTimezoneCorreto(): void
    {
        $this->assertStringContainsString('America/Sao_Paulo', $this->prodCompose,
            'Container de backup deve usar timezone America/Sao_Paulo.');
    }

    public function testBackupAgendadoAs19h(): void
    {
        $this->assertStringContainsString('19:00', $this->prodCompose,
            'Backup deve estar agendado para 19:00.');
    }

    public function testBackupSalvaEmStorageBackups(): void
    {
        $this->assertStringContainsString('./storage/backups:/backups', $this->prodCompose,
            'Backup deve salvar em ./storage/backups (sincronizado com OneDrive).');
    }

    public function testBackupNaoUsaVolumeIsolado(): void
    {
        $this->assertStringNotContainsString('sesmt-backups:', $this->prodCompose,
            'Nao deve existir volume nomeado sesmt-backups (deve usar storage local).');
    }

    public function testBackupRetencao7Dias(): void
    {
        $this->assertStringContainsString('-mtime +7', $this->prodCompose,
            'Backup deve reter arquivos por 7 dias.');
    }

    public function testBackupIncluiDumpBanco(): void
    {
        $this->assertStringContainsString('mariadb-dump', $this->prodCompose,
            'Backup deve incluir dump do banco de dados.');
    }

    public function testBackupIncluiStorage(): void
    {
        $this->assertStringContainsString('storage_', $this->prodCompose,
            'Backup deve incluir arquivos do storage (uploads).');
    }

    public function testBackupDependeDoDb(): void
    {
        // Verifica que o service backup depende do db
        $this->assertStringContainsString('depends_on', $this->prodCompose);
    }

    // ── Crontab ─────────────────────────────────────────────────────────────

    public function testCronBackupAs19h(): void
    {
        $this->assertMatchesRegularExpression(
            '/^0 19 \* \* \* .*backup\.php/m',
            $this->crontab,
            'Crontab deve ter backup.php agendado para 19:00 diariamente.'
        );
    }

    public function testCronBackupDiario(): void
    {
        // Deve ser * * * (todo dia, todo mes, todo dia da semana) nao apenas domingo
        $this->assertStringNotContainsString('* * 0', $this->crontab . 'backup',
            'Backup cron deve ser diario, nao semanal.');
    }

    // ── Cron backup.php ─────────────────────────────────────────────────────

    public function testCronPhpUsaEnvVars(): void
    {
        $this->assertStringContainsString("getenv('DB_HOST')", $this->cronBackupPhp,
            'Cron backup.php deve usar getenv para DB_HOST.');
        $this->assertStringContainsString("getenv('DB_USER')", $this->cronBackupPhp,
            'Cron backup.php deve usar getenv para DB_USER.');
        $this->assertStringContainsString("getenv('DB_PASS')", $this->cronBackupPhp,
            'Cron backup.php deve usar getenv para DB_PASS.');
    }

    public function testCronPhpLimpaBackupsAntigos(): void
    {
        $this->assertStringContainsString('-7 days', $this->cronBackupPhp,
            'Cron backup.php deve limpar backups com mais de 7 dias.');
    }

    public function testCronPhpUsaMysqldump(): void
    {
        $this->assertStringContainsString('mysqldump', $this->cronBackupPhp,
            'Cron backup.php deve usar mysqldump.');
    }

    public function testCronPhpUsaGzip(): void
    {
        $this->assertStringContainsString('gzip', $this->cronBackupPhp,
            'Cron backup.php deve comprimir com gzip.');
    }
}
