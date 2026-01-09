<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ATUShippingInstallCommand extends Command
{
    protected $signature = 'atushipping:install {--skip-env : Do not modify .env files} {--no-overwrite : Skip existing files instead of replacing}';

    protected $description = 'Install ATU Shipping package with all necessary files and configurations';

    public function handle(Installer $installer): int
    {
        $this->displayHeader();

        $overwrite = !$this->option('no-overwrite');
        $touchEnv = !$this->option('skip-env');

        // Copy files
        $this->step('Copying package files and stubs...');
        $results = $installer->install($overwrite, false);
        $this->displayCopyResults($results['copied']);

        // Environment variables
        $this->step('Updating environment files...');
        if ($touchEnv) {
            $this->updateEnvFiles();
        } else {
            $this->line('   ⏭️  Environment keys skipped (--skip-env flag used).');
        }

        // Routes
        $this->step('Ensuring routes...');
        $this->handleRoutes($results['routes'] ?? []);

        // Migrations
        $migrationsRun = $this->handleMigrations();

        // Seeders
        if ($migrationsRun) {
            $this->handleSeeders();
        }

        $this->displayCompletionMessage($touchEnv, $migrationsRun);

        return self::SUCCESS;
    }

    /**
     * Display copy results grouped by directory
     */
    private function displayCopyResults(array $copyResults): void
    {
        $copied = $copyResults['copied'] ?? [];
        $skipped = $copyResults['skipped'] ?? [];

        if (empty($copied) && empty($skipped)) {
            $this->line('   ℹ️  No files to copy');
            return;
        }

        // Group files by directory for better output
        $byDirectory = [];
        foreach ($copied as $file) {
            $dir = dirname($file);
            if (!isset($byDirectory[$dir])) {
                $byDirectory[$dir] = [];
            }
            $byDirectory[$dir][] = basename($file);
        }

        foreach ($byDirectory as $dir => $files) {
            $relativeDir = $this->getRelativePath($dir);
            $this->info("   ✅ Copied " . count($files) . " file(s) to {$relativeDir}/");
        }

        if (!empty($skipped)) {
            $this->warn("   ⚠️  " . count($skipped) . " existing file(s) skipped (use --no-overwrite to keep existing files)");
        }
    }

    /**
     * Get relative path from base path for display
     */
    private function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();
        if (str_starts_with($absolutePath, $basePath)) {
            return ltrim(str_replace($basePath, '', $absolutePath), '/\\');
        }
        return $absolutePath;
    }

    /**
     * Update .env and .env.example files with ATU Shipping configuration
     */
    private function updateEnvFiles(): void
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        // Use Installer's ensureEnvKeys method
        $installer = app(Installer::class);
        $results = $installer->ensureEnvKeys();

        $hasChanges = false;
        foreach ($results as $file => $keys) {
            if (!empty($keys)) {
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->info('   ✅ Environment files updated successfully (ATU Shipping configuration).');
        } else {
            $this->info('   ✅ Environment files already contain ATU Shipping configuration.');
        }
    }

    /**
     * Handle routes results
     */
    private function handleRoutes(array $routes): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['skipped'] ?? false) {
            $this->warn('   ⚠️  routes/api.php not found. Shipping routes were not added.');
            $this->line('   Create routes/api.php first, then re-run the installer to add the routes.');
            return;
        }

        if ($routes['added'] ?? false) {
            $this->info('   ✅ Shipping routes added to routes/api.php');
        } else {
            $this->info('   ✅ Shipping routes already exist in routes/api.php');
        }
    }

    /**
     * Display the header
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🚀 Installing ATU Shipping Package...');
        $this->line('   Version: ' . ATUShipping::VERSION);
        $this->newLine();
    }

    /**
     * Display a step message
     */
    private function step(string $message): void
    {
        $this->info("📦 {$message}");
    }

    /**
     * Handle migrations prompt and execution
     */
    private function handleMigrations(): bool
    {
        $this->step('Running database migrations...');

        if (!$this->confirm('Would you like to run migrations now?', true)) {
            $this->line('   ⏭️  Migrations skipped. You can run them later with: php artisan migrate');
            return false;
        }

        return $this->runMigrations();
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): bool
    {
        try {
            $this->line('   Running migrations...');
            $exitCode = Artisan::call('migrate', [], $this->getOutput());

            // Display any output from the migrate command
            $output = Artisan::output();
            if (!empty(trim($output))) {
                $this->line($output);
            }

            if ($exitCode === 0) {
                $this->info('   ✅ Migrations completed successfully!');
                return true;
            } else {
                $this->error('   ❌ Migrations completed with errors (exit code: ' . $exitCode . ')');
                $this->warn('   ⚠️  You can run migrations manually later with: php artisan migrate');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Migration failed: ' . $e->getMessage());
            $this->warn('   ⚠️  You can run migrations manually later with: php artisan migrate');
            return false;
        }
    }

    /**
     * Handle seeders execution
     */
    private function handleSeeders(): void
    {
        $this->step('Running database seeders...');

        if (!$this->confirm('Would you like to seed default couriers now?', true)) {
            $this->line('   ⏭️  Seeders skipped. You can run them later with: php artisan db:seed --class=ATUShippingSeeder');
            return;
        }

        $this->runSeeders();
    }

    /**
     * Run database seeders
     */
    private function runSeeders(): void
    {
        try {
            $this->line('   Running seeders...');
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'ATUShippingSeeder'
            ], $this->getOutput());

            // Display any output from the seeder command
            $output = Artisan::output();
            if (!empty(trim($output))) {
                $this->line($output);
            }

            if ($exitCode === 0) {
                $this->info('   ✅ Seeders completed successfully!');
            } else {
                $this->error('   ❌ Seeders completed with errors (exit code: ' . $exitCode . ')');
                $this->warn('   ⚠️  You can run seeders manually later with: php artisan db:seed --class=ATUShippingSeeder');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Seeder failed: ' . $e->getMessage());
            $this->warn('   ⚠️  You can run seeders manually later with: php artisan db:seed --class=ATUShippingSeeder');
        }
    }

    /**
     * Display completion message with next steps
     */
    private function displayCompletionMessage(bool $envTouched, bool $migrationsRun): void
    {
        $this->newLine();
        $this->info('🎉 ATU Shipping package installed successfully!');
        $this->newLine();

        $this->comment('📋 Next steps:');
        $this->line('   1. Review and configure your shipping rules in the database');

        if (!$migrationsRun) {
            $this->line('   2. Run migrations: php artisan migrate');
            $this->line('   3. Run seeders: php artisan db:seed --class=ATUShippingSeeder');
            $this->line('   4. Review the implementation guide: docs/atu-shipping.md');
        } else {
            $this->line('   2. Review the implementation guide: docs/atu-shipping.md');
        }

        $this->newLine();

        if (!$envTouched) {
            $this->warn('⚠️  Note: Environment keys were not modified (--skip-env flag used).');
            $this->line('   Run: php artisan atushipping:help to see available commands.');
            $this->newLine();
        }

        $this->comment('📖 For help and available commands, run: php artisan atushipping:help');
        $this->newLine();

        $this->info('✨ Happy coding with ATU Shipping!');
    }
}
