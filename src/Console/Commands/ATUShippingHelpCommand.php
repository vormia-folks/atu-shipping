<?php

namespace Vormia\ATUShipping\Console\Commands;

use Vormia\ATUShipping\ATUShipping;
use Illuminate\Console\Command;

class ATUShippingHelpCommand extends Command
{
    protected $signature = 'atushipping:help';

    protected $description = 'Display help information for ATU Shipping package commands';

    public function handle(): int
    {
        $this->displayHeader();
        $this->displayCommands();
        $this->displayUsageExamples();
        $this->displayEnvironmentKeys();
        $this->displayRoutes();
        $this->displayFooter();

        return self::SUCCESS;
    }

    /**
     * Display the header
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                     ATU SHIPPING HELP                       ║');
        $this->info('║                  Version ' . str_pad(ATUShipping::VERSION, 25) . '║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->comment('🚚 ATU Shipping provides rule-based shipping fee and tax calculation');
        $this->comment('   for A2 Commerce with support for multiple couriers and flexible rules.');
        $this->newLine();
    }

    /**
     * Display available commands
     */
    private function displayCommands(): void
    {
        $this->info('📋 AVAILABLE COMMANDS:');
        $this->newLine();

        $commands = [
            [
                'command' => 'atushipping:install',
                'description' => 'Install ATU Shipping package with all files and configurations',
                'options' => '--no-overwrite (keep existing files), --skip-env (leave .env untouched)'
            ],
            [
                'command' => 'atushipping:update',
                'description' => 'Update ATU Shipping package files and configurations',
                'options' => '--skip-env (leave .env untouched), --force (skip confirmation)'
            ],
            [
                'command' => 'atushipping:uninstall',
                'description' => 'Remove all ATU Shipping package files and configurations',
                'options' => '--keep-env (preserve env keys), --force (skip confirmation prompts)'
            ],
            [
                'command' => 'atushipping:help',
                'description' => 'Display this help information',
                'options' => null
            ]
        ];

        foreach ($commands as $cmd) {
            $this->line("  <fg=green>{$cmd['command']}</>");
            $this->line("    {$cmd['description']}");
            if ($cmd['options']) {
                $this->line("    <fg=yellow>Options:</> {$cmd['options']}");
            }
            $this->newLine();
        }
    }

    /**
     * Display usage examples
     */
    private function displayUsageExamples(): void
    {
        $this->info('💡 USAGE EXAMPLES:');
        $this->newLine();

        $examples = [
            [
                'title' => 'Installation',
                'command' => 'php artisan atushipping:install',
                'description' => 'Install ATU Shipping with all files and configurations'
            ],
            [
                'title' => 'Install (Preserve Existing Files)',
                'command' => 'php artisan atushipping:install --no-overwrite',
                'description' => 'Install without overwriting existing files'
            ],
            [
                'title' => 'Install (Skip Environment)',
                'command' => 'php artisan atushipping:install --skip-env',
                'description' => 'Install without modifying .env files'
            ],
            [
                'title' => 'Update Package',
                'command' => 'php artisan atushipping:update',
                'description' => 'Update package files and configurations'
            ],
            [
                'title' => 'Update (Force)',
                'command' => 'php artisan atushipping:update --force',
                'description' => 'Update without confirmation prompts'
            ],
            [
                'title' => 'Uninstall Package',
                'command' => 'php artisan atushipping:uninstall',
                'description' => 'Remove all ATU Shipping files and configurations'
            ],
            [
                'title' => 'Uninstall (Keep Environment)',
                'command' => 'php artisan atushipping:uninstall --keep-env',
                'description' => 'Uninstall but preserve environment variables'
            ],
            [
                'title' => 'Force Uninstall',
                'command' => 'php artisan atushipping:uninstall --force',
                'description' => 'Uninstall without confirmation prompts'
            ]
        ];

        foreach ($examples as $example) {
            $this->line("  <fg=cyan>{$example['title']}:</>");
            $this->line("    <fg=white>{$example['command']}</>");
            $this->line("    <fg=gray>{$example['description']}</>");
            $this->newLine();
        }
    }

    /**
     * Display environment keys
     */
    private function displayEnvironmentKeys(): void
    {
        $this->info('⚙️  ENVIRONMENT VARIABLES:');
        $this->newLine();

        $this->line('  <fg=white>Currently, ATU Shipping does not require any environment variables.</>');
        $this->line('  <fg=gray>Future versions may add configuration options here.</>');
        $this->newLine();
    }

    /**
     * Display routes information
     */
    private function displayRoutes(): void
    {
        $this->info('🛣️  API ROUTES:');
        $this->newLine();

        $this->line('  <fg=white>The following route block is added to routes/api.php (commented out by default):</>');
        $this->newLine();

        $this->line('  <fg=cyan>// >>> ATU Shipping Routes START</>');
        $this->line('  <fg=cyan>// Route::prefix(\'atu/shipping\')->group(function () {</>');
        $this->line('  <fg=cyan>//     Route::post(\'/calculate\', [</>');
        $this->line('  <fg=cyan>//         \\App\\Http\\Controllers\\ATU\\Shipping\\ShippingController::class,</>');
        $this->line('  <fg=cyan>//         \'calculate\'</>');
        $this->line('  <fg=cyan>//     ])->name(\'api.shipping.calculate\');</>');
        $this->line('  <fg=cyan>// });</>');
        $this->line('  <fg=cyan>// >>> ATU Shipping Routes END</>');

        $this->newLine();
        $this->line('  <fg=gray>Note: Routes are commented out by default. Uncomment and implement as needed.</>');
        $this->newLine();
    }

    /**
     * Display footer
     */
    private function displayFooter(): void
    {
        $this->info('📚 ADDITIONAL RESOURCES:');
        $this->newLine();

        $this->line('  <fg=white>Implementation Guide:</> docs/atu-shipping.md');
        $this->line('  <fg=white>Package Repository:</> vormia-folks/atu-shipping');

        $this->newLine();
        $this->comment('💡 For more detailed documentation, review the docs/atu-shipping.md file.');
        $this->newLine();

        $this->info('📖 USAGE IN CODE:');
        $this->newLine();
        $this->line('  <fg=cyan>// Get shipping options</>');
        $this->line('  <fg=white>$options = ATU::shipping()</>');
        $this->line('  <fg=white>    ->forCart($cart)</>');
        $this->line('  <fg=white>    ->to(\'KE\')</>');
        $this->line('  <fg=white>    ->options();</>');
        $this->newLine();
        $this->line('  <fg=cyan>// Select courier at checkout</>');
        $this->line('  <fg=white>ATU::shipping()</>');
        $this->line('  <fg=white>    ->forOrder($order)</>');
        $this->line('  <fg=white>    ->select(\'DHL\');</>');
        $this->newLine();

        $this->info('🎉 Thank you for using ATU Shipping!');
        $this->newLine();
    }
}
