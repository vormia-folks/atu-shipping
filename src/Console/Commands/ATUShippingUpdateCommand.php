<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ATUShippingUpdateCommand extends Command
{
    protected $signature = 'atushipping:update {--skip-env : Do not modify .env files} {--force : Skip confirmation prompts}';

    protected $description = 'Update ATU Shipping package files and configurations';

    public function handle(Installer $installer): int
    {
        $this->displayHeader();

        $force = $this->option('force');
        $touchEnv = !$this->option('skip-env');

        if (!$force && !$this->confirm('This will overwrite existing package files. Continue?', true)) {
            $this->info('❌ Update cancelled.');
            return self::SUCCESS;
        }

        $this->step('Updating package files and stubs...');
        $results = $installer->update($touchEnv);
        $this->displayCopyResults($results['copied']);

        $this->step('Updating environment files...');
        if ($touchEnv) {
            $this->displayEnvResults($results['env'] ?? []);
        } else {
            $this->line('   ⏭️  Environment keys skipped (--skip-env flag used).');
        }

        $this->step('Ensuring API routes (routes/api.php)...');
        $this->handleApiRoutes($results['routes'] ?? []);

        $this->step('Ensuring Admin routes (routes/web.php)...');
        $this->handleAdminRoutes($results['admin_routes'] ?? []);

        $this->step('Ensuring Flux sidebar menu...');
        $this->handleSidebar($results['sidebar'] ?? []);

        $this->step('Clearing application caches...');
        $this->clearCaches();

        $this->displayCompletionMessage();

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
            $this->line('   ℹ️  No files to update');
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
            $this->info("   ✅ Updated " . count($files) . " file(s) in {$relativeDir}/");
        }

        if (!empty($skipped)) {
            $this->warn("   ⚠️  " . count($skipped) . " file(s) skipped");
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

    private function handleApiRoutes(array $routes): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['skipped'] ?? false) {
            $this->warn('   ⚠️  routes/api.php not found.');
            return;
        }

        if ($routes['added'] ?? false) {
            $this->info('   ✅ API routes added to routes/api.php');
        } else {
            $this->info('   ✅ API routes already present in routes/api.php');
        }
    }

    private function handleAdminRoutes(array $routes): void
    {
        if ($routes === []) {
            return;
        }

        if ($routes['skipped'] ?? false) {
            $this->warn('   ⚠️  routes/web.php not found.');
            return;
        }

        if (! ($routes['added'] ?? false)) {
            $this->info('   ✅ Admin routes already present in routes/web.php');
            return;
        }

        if (($routes['placement'] ?? null) === 'auth_group') {
            $this->info('   ✅ Admin routes added inside the auth middleware group in routes/web.php');
        } else {
            $this->info('   ✅ Admin routes appended to routes/web.php');
        }
    }

    private function handleSidebar(array $sidebar): void
    {
        if ($sidebar === []) {
            return;
        }

        if ($sidebar['skipped'] ?? false) {
            $this->warn('   ⚠️  Flux sidebar blade not found — skipped.');
            return;
        }

        if (! ($sidebar['added'] ?? false)) {
            $this->info('   ✅ Sidebar menu already present.');
            return;
        }

        $this->info('   ✅ Sidebar menu injected (' . ($sidebar['placement'] ?? 'unknown') . ').');
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
     * Display the header
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🔄 Updating ATU Shipping Package...');
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
     * Display completion message
     */
    private function displayCompletionMessage(): void
    {
        $this->newLine();
        $this->info('🎉 ATU Shipping package updated successfully!');
        $this->newLine();
        $this->comment('📖 For help and available commands, run: php artisan atushipping:help');
        $this->newLine();
    }
}
