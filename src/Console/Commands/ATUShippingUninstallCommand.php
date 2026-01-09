<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ATUShippingUninstallCommand extends Command
{
    protected $signature = 'atushipping:uninstall {--keep-env : Leave env keys untouched} {--force : Skip confirmation prompts}';

    protected $description = 'Remove all ATU Shipping package files and configurations';

    public function handle(Installer $installer): int
    {
        $this->displayHeader();

        $force = $this->option('force');
        $keepEnv = $this->option('keep-env');

        // Warning message
        $this->error('⚠️  DANGER: This will remove ATU Shipping from your application!');
        $this->warn('   This action will:');
        $this->warn('   • Remove all ATU Shipping copied files and stubs');
        $this->warn('   • Remove shipping routes from routes/api.php');
        $this->warn('   • Note: Composer packages are NOT uninstalled');
        $this->newLine();

        if (!$force && !$this->confirm('Are you absolutely sure you want to uninstall ATU Shipping?', false)) {
            $this->info('❌ Uninstall cancelled.');
            return self::SUCCESS;
        }

        // Ask about .env variables
        $removeEnvVars = false;
        if (!$keepEnv && !$force) {
            $this->newLine();
            $removeEnvVars = $this->confirm('Do you wish to remove ATU Shipping environment variables from .env and .env.example?', false);
        } elseif ($keepEnv) {
            $removeEnvVars = false;
        } else {
            // In force mode without --keep-env, default to removing env vars
            $removeEnvVars = true;
        }

        // Step 1: Create backup
        $this->step('Creating final backup...');
        $this->createFinalBackup();

        // Step 2: Remove files
        $this->step('Removing ATU Shipping files and stubs...');
        $touchEnv = $removeEnvVars;
        $results = $installer->uninstall($touchEnv);

        $removedFiles = $results['removed'] ?? [];
        $removedCount = count($removedFiles);

        if ($removedCount > 0) {
            foreach ($removedFiles as $file) {
                $this->line("   ✅ Removed: " . $this->getRelativePath($file));
            }
            $this->info("   ✅ {$removedCount} installed file(s) removed successfully.");
        } else {
            $this->warn('   ⚠️  No installed files found to remove.');
            $this->line('   This could mean files were already deleted or the package was never installed.');
        }

        // Step 3: Environment variables
        $this->step('Cleaning up environment files...');
        if ($removeEnvVars) {
            $this->handleEnvResults($results['env'] ?? []);
        } else {
            $this->line('   ⏭️  Environment keys preserved (skipped by user choice).');
        }

        // Step 4: Routes
        $this->step('Removing API routes...');
        $this->handleRoutes($results['routes'] ?? []);

        // Step 5: Clear caches
        $this->step('Clearing application caches...');
        $this->clearCaches();

        $this->displayCompletionMessage($removeEnvVars);

        return self::SUCCESS;
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
     * Handle environment file results
     */
    private function handleEnvResults(array $envResults): void
    {
        $envCleaned = false;
        $filesChecked = [];

        foreach ($envResults as $file => $keys) {
            $filesChecked[] = basename($file);

            if ($keys !== []) {
                $this->info("   ✅ Removed from " . basename($file) . ": " . implode(', ', $keys));
                $envCleaned = true;
            } else {
                $this->line("   ℹ️  " . basename($file) . " does not contain ATU Shipping keys");
            }
        }

        if (empty($filesChecked)) {
            $this->warn('   ⚠️  No .env or .env.example files found.');
        } elseif (!$envCleaned) {
            $this->info('   ✅ No ATU Shipping environment keys found to remove.');
        }
    }

    /**
     * Handle routes results
     */
    private function handleRoutes(array $routes): void
    {
        if ($routes !== []) {
            if ($routes['removed'] ?? false) {
                $this->info('   ✅ Shipping routes removed from routes/api.php');
            } else {
                $this->info('   ✅ No route block found to remove.');
            }
        }
    }

    /**
     * Clear application caches
     */
    private function clearCaches(): void
    {
        $cacheCommands = [
            'config:clear' => 'Configuration cache',
            'route:clear' => 'Route cache',
            'view:clear' => 'View cache',
            'cache:clear' => 'Application cache',
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

    /**
     * Create final backup before uninstallation
     */
    private function createFinalBackup(): void
    {
        $backupDir = storage_path('app/atushipping-final-backup-' . date('Y-m-d-H-i-s'));

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filesToBackup = [
            config_path('atu-shipping.php') => $backupDir . '/config/atu-shipping.php',
            base_path('routes/api.php') => $backupDir . '/routes/api.php',
            base_path('.env') => $backupDir . '/.env',
        ];

        foreach ($filesToBackup as $source => $destination) {
            if (File::exists($source)) {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($source, $destination);
            }
        }

        $this->info("   ✅ Final backup created in: " . $this->getRelativePath($backupDir));
    }

    /**
     * Display the header
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🗑️  Uninstalling ATU Shipping Package...');
        $this->line('   Version: ' . ATUShipping::VERSION);
        $this->newLine();
    }

    /**
     * Display a step message
     */
    private function step(string $message): void
    {
        $this->info("🗂️  {$message}");
    }

    /**
     * Display completion message
     */
    private function displayCompletionMessage(bool $envRemoved): void
    {
        $this->newLine();
        $this->info('🎉 ATU Shipping package uninstalled successfully!');
        $this->newLine();

        $this->comment('📋 What was removed:');
        $this->line('   ✅ All ATU Shipping copied files and stubs');
        $this->line('   ✅ Shipping routes from routes/api.php');
        if ($envRemoved) {
            $this->line('   ✅ ATU Shipping environment variables');
        } else {
            $this->line('   ⏭️  Environment variables preserved (skipped by user choice)');
        }
        $this->line('   ✅ Application caches cleared');
        $this->line('   ✅ Final backup created in storage/app/');
        $this->newLine();

        $this->comment('📖 Final steps:');
        $this->line('   1. Remove "vormia-folks/atu-shipping" from your composer.json');
        $this->line('   2. Run: composer remove vormia-folks/atu-shipping');
        $this->line('   3. Review your application for any remaining ATU Shipping references');
        $this->newLine();

        if (!$envRemoved) {
            $this->warn('⚠️  Note: Environment variables were preserved. Remove them manually if needed.');
            $this->newLine();
        }

        $this->info('✨ Thank you for using ATU Shipping!');
    }
}
