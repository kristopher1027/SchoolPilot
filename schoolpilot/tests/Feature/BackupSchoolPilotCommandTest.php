<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupSchoolPilotCommandTest extends TestCase
{
    public function test_backup_contains_consistent_central_and_tenant_database_snapshots_uploads_and_manifest(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schoolpilot-backup-test-'.bin2hex(random_bytes(6));
        $tenantDirectory = $root.'/tenants';
        $publicDirectory = $root.'/public';
        $destinationDirectory = $root.'/exports';
        File::ensureDirectoryExists($tenantDirectory);
        File::ensureDirectoryExists($publicDirectory);
        File::ensureDirectoryExists($destinationDirectory);

        $central = $root.'/central.sqlite';
        $tenant = $tenantDirectory.'/greenfield.sqlite';
        foreach ([$central, $tenant] as $database) {
            $pdo = new PDO('sqlite:'.$database);
            $pdo->exec('CREATE TABLE backup_probe (value TEXT)');
            $pdo->exec("INSERT INTO backup_probe (value) VALUES ('preserved')");
            $pdo = null;
        }

        File::ensureDirectoryExists($publicDirectory.'/schools/greenfield');
        file_put_contents($publicDirectory.'/schools/greenfield/logo.png', 'logo-bytes');

        $previousCentral = config('database.connections.central_sqlite.database');
        $previousTenantPath = config('tenancy.database.sqlite.path');
        $previousPublicRoot = config('filesystems.disks.public.root');

        try {
            config([
                'database.connections.central_sqlite.database' => $central,
                'tenancy.database.sqlite.path' => $tenantDirectory,
                'filesystems.disks.public.root' => $publicDirectory,
            ]);

            $destination = $destinationDirectory.'/schoolpilot-backup.zip';
            $this->artisan('schoolpilot:backup', ['--destination' => $destination])->assertExitCode(0);

            $this->assertFileExists($destination);
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($destination) === true);
            $this->assertNotFalse($zip->locateName('databases/central.sqlite'));
            $this->assertNotFalse($zip->locateName('databases/greenfield.sqlite'));
            $this->assertSame('logo-bytes', $zip->getFromName('public/schools/greenfield/logo.png'));

            $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('databases/central.sqlite', $manifest['files_sha256']);
            $this->assertArrayHasKey('databases/greenfield.sqlite', $manifest['files_sha256']);
            $this->assertArrayHasKey('public/schools/greenfield/logo.png', $manifest['files_sha256']);
            $zip->close();

            $snapshotPath = $root.'/snapshot.sqlite';
            $snapshotFile = new ZipArchive();
            $snapshotFile->open($destination);
            file_put_contents($snapshotPath, $snapshotFile->getFromName('databases/central.sqlite'));
            $snapshotFile->close();
            $snapshot = new PDO('sqlite:'.$snapshotPath);
            $this->assertSame('preserved', $snapshot->query('SELECT value FROM backup_probe')->fetchColumn());
        } finally {
            config([
                'database.connections.central_sqlite.database' => $previousCentral,
                'tenancy.database.sqlite.path' => $previousTenantPath,
                'filesystems.disks.public.root' => $previousPublicRoot,
            ]);
            File::deleteDirectory($root);
        }
    }

    public function test_backup_requires_an_absolute_new_destination_path(): void
    {
        $this->artisan('schoolpilot:backup', ['--destination' => 'relative-backup.zip'])
            ->assertExitCode(1);
    }
}
