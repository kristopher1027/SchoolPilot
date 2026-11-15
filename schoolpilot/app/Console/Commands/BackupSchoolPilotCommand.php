<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class BackupSchoolPilotCommand extends Command
{
    protected $signature = 'schoolpilot:backup {--destination= : Absolute path for the new ZIP archive}';

    protected $description = 'Create a consistent archive of the central database, school databases, and public uploads';

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('The PHP ZIP extension is required to create backups.');

            return self::FAILURE;
        }

        $destination = (string) $this->option('destination');

        if ($destination === '' || ! str_starts_with($destination, DIRECTORY_SEPARATOR)) {
            $this->error('Provide an absolute --destination path outside the application data directories.');

            return self::FAILURE;
        }

        $destination = rtrim($destination, DIRECTORY_SEPARATOR);
        $parent = dirname($destination);

        if (! is_dir($parent) || ! is_writable($parent) || file_exists($destination)) {
            $this->error('The destination directory must exist and be writable, and the archive must not already exist.');

            return self::FAILURE;
        }

        $central = (string) config('database.connections.central_sqlite.database');
        $tenantDirectory = (string) config('tenancy.database.sqlite.path', database_path('tenants'));
        $publicDirectory = (string) config('filesystems.disks.public.root', storage_path('app/public'));
        $realParent = realpath($parent);

        foreach ([dirname($central), $tenantDirectory, $publicDirectory] as $sourceDirectory) {
            $realSource = realpath($sourceDirectory);
            if ($realParent !== false && $realSource !== false
                && ($realParent === $realSource || str_starts_with($realParent.DIRECTORY_SEPARATOR, rtrim($realSource, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
                $this->error('The backup destination must be outside the central database, tenant database, and public upload directories.');

                return self::FAILURE;
            }
        }

        if (! is_file($central)) {
            $this->error('The configured central SQLite database does not exist.');

            return self::FAILURE;
        }

        $workDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schoolpilot-backup-'.bin2hex(random_bytes(8));
        $databaseDirectory = $workDirectory.DIRECTORY_SEPARATOR.'databases';

        try {
            if (! mkdir($databaseDirectory, 0700, true) && ! is_dir($databaseDirectory)) {
                throw new RuntimeException('Unable to create the temporary backup directory.');
            }

            $files = [];
            $this->snapshotDatabase($central, $databaseDirectory.DIRECTORY_SEPARATOR.'central.sqlite');
            $files[] = $databaseDirectory.DIRECTORY_SEPARATOR.'central.sqlite';

            if (is_dir($tenantDirectory)) {
                foreach (glob(rtrim($tenantDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $tenantDatabase) {
                    if (! is_file($tenantDatabase) || is_link($tenantDatabase)) {
                        continue;
                    }

                    $name = basename($tenantDatabase);
                    $snapshot = $databaseDirectory.DIRECTORY_SEPARATOR.$name;
                    $this->snapshotDatabase($tenantDatabase, $snapshot);
                    $files[] = $snapshot;
                }
            }

            $manifestFiles = [];
            foreach ($files as $file) {
                $manifestFiles['databases/'.basename($file)] = hash_file('sha256', $file);
            }

            if (is_dir($publicDirectory)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($publicDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $item) {
                    if (! $item->isFile() || $item->isLink()) {
                        continue;
                    }

                    $relative = substr($item->getPathname(), strlen(rtrim($publicDirectory, DIRECTORY_SEPARATOR)) + 1);
                    $archivePath = 'public/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                    $manifestFiles[$archivePath] = hash_file('sha256', $item->getPathname());
                }
            }

            $manifest = [
                'application' => 'SchoolPilot',
                'created_at' => now()->toIso8601String(),
                'format_version' => 1,
                'files_sha256' => $manifestFiles,
            ];

            $archive = new ZipArchive();
            if ($archive->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Unable to create the backup archive at the requested destination.');
            }

            foreach ($files as $file) {
                $archive->addFile($file, 'databases/'.basename($file));
            }

            if (is_dir($publicDirectory)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($publicDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $item) {
                    if ($item->isFile() && ! $item->isLink()) {
                        $relative = substr($item->getPathname(), strlen(rtrim($publicDirectory, DIRECTORY_SEPARATOR)) + 1);
                        $archive->addFile($item->getPathname(), 'public/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative));
                    }
                }
            }

            $archive->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $archive->close();

            $this->info('Backup created: '.$destination);
            $this->info('Databases: '.count($files).'; upload files: '.count(array_filter(array_keys($manifestFiles), fn ($path) => str_starts_with($path, 'public/'))));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            if (is_file($destination)) {
                @unlink($destination);
            }

            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_dir($workDirectory)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($workDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }

                @rmdir($workDirectory);
            }
        }
    }

    private function snapshotDatabase(string $source, string $destination): void
    {
        $pdo = new PDO('sqlite:'.$source, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $quotedDestination = str_replace("'", "''", $destination);
        $pdo->exec("VACUUM INTO '".$quotedDestination."'");
        $pdo = null;

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('SQLite did not produce a valid snapshot for '.$source);
        }
    }
}
