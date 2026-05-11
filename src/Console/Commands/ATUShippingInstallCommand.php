<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ATUShippingInstallCommand extends Command
{
    protected $signature = 'atushipping:install {--skip-env : Do not modify .env files} {--no-overwrite : Skip existing files instead of replacing}';

    protected $description = 'Install ATU Shipping package with all necessary files and configurations';

    public function handle(Installer $installer): int
    {
        $this->displayHeader();

        $overwrite = ! $this->option('no-overwrite');
        $touchEnv = ! $this->option('skip-env');

        $this->step('Copying package files and stubs...');
        $results = $installer->install($overwrite, $touchEnv);
        $this->displayCopyResults($results['copied']);

        $this->step('Updating environment files...');
        if ($touchEnv) {
            $this->displayEnvResults($results['env']);
        } else {
            $this->line('   ⏭️  Environment keys skipped (--skip-env flag used).');
        }

        $this->step('Ensuring API routes (routes/api.php)...');
        $this->displayApiRouteResults($results['routes'] ?? []);

        $this->step('Ensuring Admin routes (routes/web.php)...');
        $this->displayAdminRouteResults($results['admin_routes'] ?? []);

        $this->step('Ensuring Flux sidebar menu...');
        $this->displaySidebarResults($results['sidebar'] ?? []);

        $migrationsRun = $this->handleMigrations();

        if ($migrationsRun) {
            $this->handleSeeders();
        }

        $this->displayCompletionMessage($touchEnv, $migrationsRun);

        return self::SUCCESS;
    }

    private function displayCopyResults(array $copyResults): void
    {
        $copied = $copyResults['copied'] ?? [];
        $skipped = $copyResults['skipped'] ?? [];

        if (empty($copied) && empty($skipped)) {
            $this->line('   ℹ️  No files to copy');
            return;
        }

        $byDirectory = [];
        foreach ($copied as $file) {
            $dir = dirname($file);
            $byDirectory[$dir] ??= [];
            $byDirectory[$dir][] = basename($file);
        }

        foreach ($byDirectory as $dir => $files) {
            $relativeDir = $this->getRelativePath($dir);
            $this->info('   ✅ Copied ' . count($files) . " file(s) to {$relativeDir}/");
        }

        if (! empty($skipped)) {
            $this->warn('   ⚠️  ' . count($skipped) . ' existing file(s) skipped (use --no-overwrite to keep existing files)');
        }
    }

    private function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();
        if (str_starts_with($absolutePath, $basePath)) {
            return ltrim(str_replace($basePath, '', $absolutePath), '/\\');
        }
        return $absolutePath;
    }

    private function displayEnvResults(array $envResults): void
    {
        $changed = false;

        foreach ($envResults as $file => $keys) {
            if (! empty($keys)) {
                $changed = true;
                $this->info('   ✅ Added to ' . basename($file) . ': ' . implode(', ', $keys));
            }
        }

        if (! $changed) {
            $this->info('   ✅ Environment files already contain ATU Shipping configuration.');
        }
    }

    private function displayApiRouteResults(array $routes): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['skipped'] ?? false) {
            $this->warn('   ⚠️  routes/api.php not found. API routes were not added.');
            $this->line('   Create routes/api.php first, then re-run the installer.');
            return;
        }

        if ($routes['added'] ?? false) {
            $this->info('   ✅ Shipping API routes added to routes/api.php');
        } else {
            $this->info('   ✅ Shipping API routes already present in routes/api.php');
        }
    }

    private function displayAdminRouteResults(array $routes): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['skipped'] ?? false) {
            $this->warn('   ⚠️  routes/web.php not found. Admin routes were not added.');
            return;
        }

        if (! ($routes['added'] ?? false)) {
            $this->info('   ✅ Admin routes already present in routes/web.php');
            return;
        }

        $placement = $routes['placement'] ?? 'appended';
        if ($placement === 'auth_group') {
            $this->info('   ✅ Admin routes added inside the auth middleware group in routes/web.php');
        } else {
            $this->info('   ✅ Admin routes appended to routes/web.php');
            $this->warn('   ⚠️  Could not locate an auth middleware group — please verify placement.');
        }
    }

    private function displaySidebarResults(array $sidebar): void
    {
        if ($sidebar === []) {
            return;
        }

        if ($sidebar['skipped'] ?? false) {
            $this->warn('   ⚠️  No Flux sidebar blade found at layouts/app/sidebar.blade.php or components/layouts/app/sidebar.blade.php — skipped.');
            $this->line('   Manually add the menu from src/stubs/reference/sidebar-menu-to-add.blade.php once your admin layout exists.');
            return;
        }

        if (! ($sidebar['added'] ?? false)) {
            $this->info('   ✅ Sidebar menu already present.');
            return;
        }

        $placement = $sidebar['placement'] ?? 'appended';
        if ($placement === 'platform_group') {
            $this->info('   ✅ Sidebar menu injected inside Platform flux:sidebar.group');
        } elseif ($placement === 'sidebar_close') {
            $this->info('   ✅ Sidebar menu injected before </flux:sidebar>');
        } else {
            $this->info('   ✅ Sidebar menu appended to the sidebar file');
            $this->warn('   ⚠️  Could not locate Platform group or </flux:sidebar> — please verify placement.');
        }
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🚀 Installing ATU Shipping Package...');
        $this->line('   Version: ' . ATUShipping::VERSION);
        $this->newLine();
    }

    private function step(string $message): void
    {
        $this->info("📦 {$message}");
    }

    private function handleMigrations(): bool
    {
        $this->step('Running database migrations...');

        if (! $this->confirm('Would you like to run migrations now?', true)) {
            $this->line('   ⏭️  Migrations skipped. You can run them later with: php artisan migrate');
            return false;
        }

        return $this->runMigrations();
    }

    private function runMigrations(): bool
    {
        try {
            $this->line('   Running migrations...');
            $exitCode = Artisan::call('migrate', [], $this->getOutput());

            $output = Artisan::output();
            if (! empty(trim($output))) {
                $this->line($output);
            }

            if ($exitCode === 0) {
                $this->info('   ✅ Migrations completed successfully!');
                return true;
            }

            $this->error('   ❌ Migrations completed with errors (exit code: ' . $exitCode . ')');
            $this->warn('   ⚠️  You can run migrations manually later with: php artisan migrate');
            return false;
        } catch (\Exception $e) {
            $this->error('   ❌ Migration failed: ' . $e->getMessage());
            $this->warn('   ⚠️  You can run migrations manually later with: php artisan migrate');
            return false;
        }
    }

    private function handleSeeders(): void
    {
        $this->step('Running database seeders...');

        if (! $this->confirm('Would you like to seed default couriers now?', true)) {
            $this->line('   ⏭️  Seeders skipped. You can run them later with: php artisan db:seed --class=ATUShippingSeeder');
            return;
        }

        $this->runSeeders();
    }

    private function runSeeders(): void
    {
        try {
            $this->line('   Running seeders...');
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'ATUShippingSeeder',
            ], $this->getOutput());

            $output = Artisan::output();
            if (! empty(trim($output))) {
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

    private function displayCompletionMessage(bool $envTouched, bool $migrationsRun): void
    {
        $this->newLine();
        $this->info('🎉 ATU Shipping package installed successfully!');
        $this->newLine();

        $this->comment('📋 Next steps:');
        $this->line('   1. Review and configure your shipping rules in the database');

        if (! $migrationsRun) {
            $this->line('   2. Run migrations: php artisan migrate');
            $this->line('   3. Run seeders: php artisan db:seed --class=ATUShippingSeeder');
            $this->line('   4. Review the implementation guide: docs/atu-shipping.md');
        } else {
            $this->line('   2. Review the implementation guide: docs/atu-shipping.md');
        }

        $this->newLine();

        if (! $envTouched) {
            $this->warn('⚠️  Note: Environment keys were not modified (--skip-env flag used).');
            $this->line('   Run: php artisan atushipping:help to see available commands.');
            $this->newLine();
        }

        $this->comment('📖 For help and available commands, run: php artisan atushipping:help');
        $this->newLine();

        $this->info('✨ Happy coding with ATU Shipping!');
    }
}
