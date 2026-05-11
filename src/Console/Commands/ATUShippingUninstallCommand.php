<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Support\AtuShippingPackageMigrationNames;
use Vormia\ATUShipping\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ATUShippingUninstallCommand extends Command
{
    protected $signature = 'atushipping:uninstall {--keep-env : Leave env keys untouched} {--force : Skip confirmation prompts}';

    protected $description = 'Remove all ATU Shipping package files and configurations';

    /**
     * Tables the package owns; safe across MySQL/Postgres/SQLite via Schema.
     */
    private const TABLES = [
        // Drop child tables first to avoid FK errors.
        'atu_shipping_logs',
        'atu_shipping_fees',
        'atu_shipping_rules',
        'atu_shipping_couriers',
    ];

    public function handle(Installer $installer): int
    {
        $this->displayHeader();

        $force = $this->option('force');
        $keepEnv = $this->option('keep-env');

        $this->error('⚠️  DANGER: This will remove ATU Shipping from your application!');
        $this->warn('   This action will:');
        $this->warn('   • Remove all ATU Shipping copied files and stubs');
        $this->warn('   • Remove API routes from routes/api.php');
        $this->warn('   • Remove Admin routes from routes/web.php');
        $this->warn('   • Remove Flux sidebar menu entries (if injected)');
        $this->warn('   • Note: Composer packages are NOT uninstalled');
        $this->warn('   • Migrations live in vendor; use composer remove when you drop the dependency');
        $this->newLine();

        if (! $force && ! $this->confirm('Are you absolutely sure you want to uninstall ATU Shipping?', false)) {
            $this->info('❌ Uninstall cancelled.');
            return self::SUCCESS;
        }

        $undoMigrations = false;
        if (! $force) {
            $this->newLine();
            $this->error('⚠️  WARNING: Dropping shipping tables will DELETE ALL DATA in them!');
            $this->warn('   This includes: couriers, rules, fees, and logs.');
            $undoMigrations = $this->confirm('Do you wish to drop ATU Shipping database tables?', false);
        }

        $removeEnvVars = false;
        if (! $keepEnv && ! $force) {
            $this->newLine();
            $removeEnvVars = $this->confirm('Do you wish to remove ATU Shipping environment variables from .env and .env.example?', false);
        } elseif (! $keepEnv) {
            // In force mode without --keep-env, default to removing env vars.
            $removeEnvVars = true;
        }

        $this->step('Creating final backup...');
        $this->createFinalBackup();

        $this->step('Removing ATU Shipping files and stubs...');
        $touchEnv = $removeEnvVars;
        $results = $installer->uninstall($touchEnv);

        $this->displayRemovedFiles($results['removed'] ?? []);

        $this->step('Cleaning up environment files...');
        if ($removeEnvVars) {
            $this->handleEnvResults($results['env'] ?? []);
        } else {
            $this->line('   ⏭️  Environment keys preserved (skipped by user choice).');
        }

        $this->step('Removing API routes (routes/api.php)...');
        $this->handleRoutes($results['routes'] ?? [], 'API');

        $this->step('Removing Admin routes (routes/web.php)...');
        $this->handleRoutes($results['admin_routes'] ?? [], 'Admin');

        $this->step('Removing Flux sidebar menu entries...');
        $this->handleSidebar($results['sidebar'] ?? []);

        if ($undoMigrations) {
            $this->step('Dropping ATU Shipping database tables...');
            $this->dropTables();
            $this->cleanupMigrationsTable();
        } else {
            $this->step('Skipping table drop...');
            $this->line('   ⏭️  Tables preserved (skipped by user choice).');
        }

        $this->step('Clearing application caches...');
        $this->clearCaches();

        $this->displayCompletionMessage($removeEnvVars, $undoMigrations);

        return self::SUCCESS;
    }

    private function displayRemovedFiles(array $removedFiles): void
    {
        $count = count($removedFiles);

        if ($count === 0) {
            $this->warn('   ⚠️  No installed files found to remove.');
            $this->line('   This could mean files were already deleted or the package was never installed.');
            return;
        }

        foreach ($removedFiles as $file) {
            $this->line('   ✅ Removed: ' . $this->getRelativePath($file));
        }

        $this->info("   ✅ {$count} installed file(s) removed successfully.");
    }

    private function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();
        if (str_starts_with($absolutePath, $basePath)) {
            return ltrim(str_replace($basePath, '', $absolutePath), '/\\');
        }
        return $absolutePath;
    }

    private function handleEnvResults(array $envResults): void
    {
        $envCleaned = false;
        $filesChecked = [];

        foreach ($envResults as $file => $keys) {
            $filesChecked[] = basename($file);

            if ($keys !== []) {
                $this->info('   ✅ Removed from ' . basename($file) . ': ' . implode(', ', $keys));
                $envCleaned = true;
            } else {
                $this->line('   ℹ️  ' . basename($file) . ' does not contain ATU Shipping keys');
            }
        }

        if (empty($filesChecked)) {
            $this->warn('   ⚠️  No .env or .env.example files found.');
        } elseif (! $envCleaned) {
            $this->info('   ✅ No ATU Shipping environment keys found to remove.');
        }
    }

    private function handleRoutes(array $routes, string $label): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['removed'] ?? false) {
            $this->info("   ✅ {$label} routes removed.");
        } else {
            $this->info("   ℹ️  No {$label} route block found to remove.");
        }
    }

    private function handleSidebar(array $sidebar): void
    {
        if ($sidebar === []) {
            return;
        }

        if ($sidebar['removed'] ?? false) {
            $this->info('   ✅ Sidebar menu entries removed.');
        } else {
            $this->info('   ℹ️  No sidebar menu block found to remove.');
        }
    }

    private function clearCaches(): void
    {
        $cacheCommands = [
            'config:clear' => 'Configuration cache',
            'route:clear'  => 'Route cache',
            'view:clear'   => 'View cache',
            'cache:clear'  => 'Application cache',
        ];

        foreach ($cacheCommands as $command => $description) {
            try {
                Artisan::call($command);
                $this->line("   ✅ Cleared: {$description}");
            } catch (\Exception $e) {
                $this->line("   ⚠️  Skipped: {$description} (not available)");
            }
        }
    }

    private function createFinalBackup(): void
    {
        $backupDir = storage_path('app/atushipping-final-backup-' . date('Y-m-d-H-i-s'));

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filesToBackup = [
            config_path('atu-shipping.php')                  => $backupDir . '/config/atu-shipping.php',
            database_path('seeders/ATUShippingSeeder.php')   => $backupDir . '/seeders/ATUShippingSeeder.php',
            base_path('routes/api.php')                      => $backupDir . '/routes/api.php',
            base_path('routes/web.php')                      => $backupDir . '/routes/web.php',
            base_path('.env')                                => $backupDir . '/.env',
        ];

        foreach ($filesToBackup as $source => $destination) {
            if (File::exists($source)) {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($source, $destination);
            }
        }

        $this->info('   ✅ Final backup created in: ' . $this->getRelativePath($backupDir));
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🗑️  Uninstalling ATU Shipping Package...');
        $this->line('   Version: ' . ATUShipping::VERSION);
        $this->newLine();
    }

    private function step(string $message): void
    {
        $this->info("🗂️  {$message}");
    }

    /**
     * Drop the package's tables using the Schema facade — portable across
     * MySQL, PostgreSQL and SQLite (unlike raw "SHOW TABLES" / FK_CHECKS).
     */
    private function dropTables(): void
    {
        try {
            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    Schema::dropIfExists($table);
                    $this->line("   ✅ Dropped table: {$table}");
                } else {
                    $this->line("   ℹ️  Table not found: {$table}");
                }
            }

            $this->info('   ✅ Database tables removed successfully.');
        } catch (\Exception $e) {
            $this->error('   ❌ Error removing database tables: ' . $e->getMessage());
            $this->warn('   ⚠️  You may need to manually drop the tables.');
        }
    }

    /**
     * Remove the package's migration entries from the migrations table so
     * that re-installation re-runs them cleanly.
     */
    private function cleanupMigrationsTable(): void
    {
        try {
            if (! Schema::hasTable('migrations')) {
                $this->line('   ℹ️  No migrations table; skipping row prune.');

                return;
            }

            $names = AtuShippingPackageMigrationNames::basenames();
            if ($names === []) {
                $this->line('   ℹ️  No package migration names resolved; skipping row prune.');

                return;
            }

            $deleted = DB::table('migrations')->whereIn('migration', $names)->delete();

            if ($deleted > 0) {
                $this->info("   ✅ Removed {$deleted} row(s) for ATU Shipping package migrations from the migrations table.");
            } else {
                $this->line('   ℹ️  No matching ATU Shipping migration rows in the migrations table.');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Could not prune migrations table: ' . $e->getMessage());
        }
    }

    private function displayCompletionMessage(bool $envRemoved, bool $migrationsUndone): void
    {
        $this->newLine();
        $this->info('🎉 ATU Shipping package uninstalled successfully!');
        $this->newLine();

        $this->comment('📋 What was removed:');
        $this->line('   ✅ All ATU Shipping copied files and stubs');
        $this->line('   ✅ API routes (routes/api.php) and Admin routes (routes/web.php)');
        $this->line('   ✅ Sidebar menu entries (when present)');

        if ($envRemoved) {
            $this->line('   ✅ ATU Shipping environment variables');
        } else {
            $this->line('   ⏭️  Environment variables preserved (skipped by user choice)');
        }

        if ($migrationsUndone) {
            $this->line('   ✅ ATU Shipping database tables dropped and migration entries cleaned');
        } else {
            $this->line('   ⏭️  Database tables preserved (skipped by user choice)');
        }

        $this->line('   ✅ Application caches cleared');
        $this->line('   ✅ Final backup created in storage/app/');
        $this->newLine();

        $this->comment('📖 Final steps:');
        $this->line('   1. Remove "vormia-folks/atu-shipping" from your composer.json');
        $this->line('   2. Run: composer remove vormia-folks/atu-shipping');
        $this->line('   (Migration PHP files remain in vendor until composer remove.)');

        if (! $migrationsUndone) {
            $this->line('   3. Manually drop the atu_shipping_* tables if you no longer need the data');
        }

        $this->newLine();

        if (! $envRemoved) {
            $this->warn('⚠️  Note: Environment variables were preserved. Remove them manually if needed.');
            $this->newLine();
        }

        $this->info('✨ Thank you for using ATU Shipping!');
    }
}
