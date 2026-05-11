<?php

namespace Vormia\ATUShipping\Tests\Support;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Vormia\ATUShipping\Support\Installer;

/**
 * Black-box test for the Installer against a real (temporary) filesystem.
 * We don't need orchestra/testbench because the Installer only depends on
 * Illuminate\Filesystem\Filesystem and string paths, not on a booted app.
 */
class InstallerTest extends TestCase
{
    private string $stubsPath;
    private string $appPath;
    private Filesystem $files;
    private Installer $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();

        // 1) Real package stubs (the source we're shipping).
        $this->stubsPath = realpath(__DIR__ . '/../../src/stubs');
        $this->assertNotFalse($this->stubsPath, 'src/stubs must exist');

        // 2) A throwaway "Laravel app" rooted in a unique temp dir.
        $this->appPath = sys_get_temp_dir() . '/atushipping-installer-' . uniqid('', true);
        $this->files->makeDirectory($this->appPath, 0755, true);

        // The host app needs these dirs to exist; we'll add files per-test.
        $this->files->makeDirectory($this->appPath . '/routes', 0755, true);
        $this->files->makeDirectory($this->appPath . '/config', 0755, true);
        $this->files->makeDirectory($this->appPath . '/app', 0755, true);
        $this->files->makeDirectory($this->appPath . '/resources/views', 0755, true);

        $this->installer = new Installer($this->files, $this->stubsPath, $this->appPath);
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->appPath)) {
            $this->files->deleteDirectory($this->appPath);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // copyStubs
    // ---------------------------------------------------------------------

    public function test_install_copies_controller_to_app_http_controllers(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertFileExists(
            $this->appPath . '/app/Http/Controllers/Atu/ShippingController.php'
        );
    }

    public function test_install_copies_config_file(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertFileExists($this->appPath . '/config/atu-shipping.php');
    }

    public function test_install_copies_seeder_to_database_seeders(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertFileExists(
            $this->appPath . '/database/seeders/ATUShippingSeeder.php'
        );
    }

    public function test_install_copies_livewire_views_under_resources(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $base = $this->appPath . '/resources/views/livewire/admin/atu/shipping';

        $this->assertFileExists($base . '/couriers/index.blade.php');
        $this->assertFileExists($base . '/couriers/create.blade.php');
        $this->assertFileExists($base . '/couriers/edit.blade.php');
        $this->assertFileExists($base . '/rules/index.blade.php');
        $this->assertFileExists($base . '/rules/create.blade.php');
        $this->assertFileExists($base . '/rules/edit.blade.php');
        $this->assertFileExists($base . '/logs/index.blade.php');
    }

    public function test_install_does_not_copy_migrations_into_app(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertDirectoryDoesNotExist(
            $this->appPath . '/database/migrations',
            'Migrations should be loaded from the package (loadMigrationsFrom), not copied.'
        );
    }

    public function test_install_does_not_copy_reference_files(): void
    {
        $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertDirectoryDoesNotExist(
            $this->appPath . '/reference',
            'reference/ directory holds docs and must never be copied.'
        );
    }

    public function test_install_with_no_overwrite_preserves_existing_files(): void
    {
        $configPath = $this->appPath . '/config/atu-shipping.php';
        $this->files->put($configPath, "<?php return ['user_customized' => true];\n");

        $results = $this->installer->install(overwrite: false, touchEnv: false);

        $this->assertStringContainsString(
            "user_customized",
            $this->files->get($configPath),
            'Existing user config must be preserved when overwrite=false.'
        );
        $this->assertContains($configPath, $results['copied']['skipped'] ?? []);
    }

    // ---------------------------------------------------------------------
    // .env keys
    // ---------------------------------------------------------------------

    public function test_install_writes_documented_env_keys_to_env_files(): void
    {
        $env = $this->appPath . '/.env';
        $envExample = $this->appPath . '/.env.example';
        $this->files->put($env, "APP_NAME=Laravel\nAPP_ENV=local\n");
        $this->files->put($envExample, "APP_NAME=Laravel\n");

        $results = $this->installer->install(overwrite: true, touchEnv: true);

        $envContents = $this->files->get($env);
        $exampleContents = $this->files->get($envExample);

        // Three documented keys must be present in both files now.
        foreach (['ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY=ZA', 'ATU_SHIPPING_BASE_CURRENCY=USD', 'ATU_SHIPPING_ENABLE_LOGGING=true'] as $expected) {
            $this->assertStringContainsString($expected, $envContents, "Missing in .env: {$expected}");
            $this->assertStringContainsString($expected, $exampleContents, "Missing in .env.example: {$expected}");
        }

        // Section header should be present too.
        $this->assertStringContainsString('# ATU Shipping Configuration', $envContents);

        // Results should report exactly what was added.
        $this->assertContains('ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY', $results['env'][$env]);
    }

    public function test_install_is_idempotent_for_env_keys(): void
    {
        $env = $this->appPath . '/.env';
        $this->files->put($env, "APP_NAME=Laravel\n");

        $this->installer->install(overwrite: true, touchEnv: true);
        $firstPass = $this->files->get($env);

        $results = $this->installer->install(overwrite: true, touchEnv: true);
        $secondPass = $this->files->get($env);

        $this->assertSame($firstPass, $secondPass, 'Re-running install must not re-append env keys.');
        $this->assertSame([], $results['env'][$env], 'No new keys should be reported on the second run.');
    }

    public function test_install_skips_env_files_that_dont_exist(): void
    {
        $results = $this->installer->install(overwrite: true, touchEnv: true);

        $this->assertSame([], $results['env'][$this->appPath . '/.env']);
        $this->assertFileDoesNotExist($this->appPath . '/.env');
    }

    public function test_uninstall_removes_documented_env_keys(): void
    {
        $env = $this->appPath . '/.env';
        $this->files->put($env, "APP_NAME=Laravel\nAPP_ENV=local\n");

        $this->installer->install(overwrite: true, touchEnv: true);
        $this->installer->uninstall(true);

        $contents = $this->files->get($env);
        $this->assertStringNotContainsString('ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY', $contents);
        $this->assertStringNotContainsString('ATU_SHIPPING_BASE_CURRENCY', $contents);
        $this->assertStringNotContainsString('ATU_SHIPPING_ENABLE_LOGGING', $contents);
        $this->assertStringNotContainsString('# ATU Shipping Configuration', $contents);
        $this->assertStringContainsString('APP_NAME=Laravel', $contents);
    }

    // ---------------------------------------------------------------------
    // API routes (routes/api.php)
    // ---------------------------------------------------------------------

    public function test_install_injects_uncommented_api_routes_between_markers(): void
    {
        $apiPath = $this->appPath . '/routes/api.php';
        $this->files->put($apiPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        $this->installer->install(overwrite: true, touchEnv: false);
        $contents = $this->files->get($apiPath);

        $this->assertStringContainsString('// >>> ATU Shipping API Routes START', $contents);
        $this->assertStringContainsString('// >>> ATU Shipping API Routes END', $contents);
        // Must be live code (no // before Route::).
        $this->assertMatchesRegularExpression('/^\s*Route::prefix\(\'atu\/shipping\'\)/m', $contents);
        $this->assertStringContainsString('App\\Http\\Controllers\\Atu\\ShippingController', $contents);
        // We must not regress to the old commented form.
        $this->assertStringNotContainsString('// Route::prefix(\'atu/shipping\')', $contents);
    }

    public function test_install_skips_api_routes_when_file_missing(): void
    {
        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['routes']['skipped']);
        $this->assertFalse($results['routes']['added']);
    }

    public function test_install_is_idempotent_for_api_routes(): void
    {
        $apiPath = $this->appPath . '/routes/api.php';
        $this->files->put($apiPath, "<?php\n");

        $this->installer->install(overwrite: true, touchEnv: false);
        $first = $this->files->get($apiPath);
        $this->installer->install(overwrite: true, touchEnv: false);
        $second = $this->files->get($apiPath);

        $this->assertSame($first, $second, 'API route block must only be injected once.');
        $this->assertSame(1, substr_count($second, '// >>> ATU Shipping API Routes START'));
    }

    public function test_uninstall_removes_api_route_block(): void
    {
        $apiPath = $this->appPath . '/routes/api.php';
        $original = "<?php\n\n// my routes\nRoute::get('/me', fn() => 'me');\n";
        $this->files->put($apiPath, $original);

        $this->installer->install(overwrite: true, touchEnv: false);
        $this->installer->uninstall(false);

        $contents = $this->files->get($apiPath);
        $this->assertStringNotContainsString('ATU Shipping API Routes', $contents);
        $this->assertStringContainsString('Route::get(\'/me\'', $contents);
    }

    // ---------------------------------------------------------------------
    // Admin routes (routes/web.php)
    // ---------------------------------------------------------------------

    public function test_install_injects_admin_routes_inside_auth_middleware_group(): void
    {
        $webPath = $this->appPath . '/routes/web.php';
        $this->files->put($webPath, <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn() => 'home');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn() => 'dashboard');
});
PHP);

        $results = $this->installer->install(overwrite: true, touchEnv: false);
        $contents = $this->files->get($webPath);

        $this->assertTrue($results['admin_routes']['added']);
        $this->assertSame('auth_group', $results['admin_routes']['placement']);
        $this->assertStringContainsString('// >>> ATU Shipping Admin Routes START', $contents);
        $this->assertStringContainsString('// >>> ATU Shipping Admin Routes END', $contents);

        // Order check: START marker must appear AFTER the auth group opener
        // and BEFORE the existing dashboard route.
        $authPos = strpos($contents, "Route::middleware(['auth'])->group");
        $startPos = strpos($contents, '// >>> ATU Shipping Admin Routes START');
        $dashboardPos = strpos($contents, "/dashboard");

        $this->assertNotFalse($authPos);
        $this->assertNotFalse($startPos);
        $this->assertNotFalse($dashboardPos);
        $this->assertLessThan($startPos, $authPos, 'Block must appear after auth group opener.');
        $this->assertLessThan($dashboardPos, $startPos, 'Block must appear before existing routes inside the group.');
    }

    public function test_install_injects_admin_routes_for_auth_verified_middleware_group(): void
    {
        $webPath = $this->appPath . '/routes/web.php';
        $this->files->put($webPath, <<<'PHP'
<?php

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn() => 'dashboard');
});
PHP);

        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['admin_routes']['added']);
        $this->assertSame('auth_group', $results['admin_routes']['placement']);
    }

    public function test_install_appends_admin_routes_when_no_auth_group_found(): void
    {
        $webPath = $this->appPath . '/routes/web.php';
        $this->files->put($webPath, "<?php\n\nRoute::get('/', fn() => 'home');\n");

        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['admin_routes']['added']);
        $this->assertSame('appended', $results['admin_routes']['placement']);
        $this->assertStringContainsString('// >>> ATU Shipping Admin Routes START', $this->files->get($webPath));
    }

    public function test_install_skips_admin_routes_when_web_php_missing(): void
    {
        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['admin_routes']['skipped']);
        $this->assertFalse($results['admin_routes']['added']);
    }

    public function test_install_is_idempotent_for_admin_routes(): void
    {
        $webPath = $this->appPath . '/routes/web.php';
        $this->files->put($webPath, "<?php\n\nRoute::middleware(['auth'])->group(function () {});\n");

        $this->installer->install(overwrite: true, touchEnv: false);
        $first = $this->files->get($webPath);
        $this->installer->install(overwrite: true, touchEnv: false);
        $second = $this->files->get($webPath);

        $this->assertSame($first, $second);
        $this->assertSame(1, substr_count($second, '// >>> ATU Shipping Admin Routes START'));
    }

    public function test_uninstall_removes_admin_route_block(): void
    {
        $webPath = $this->appPath . '/routes/web.php';
        $this->files->put($webPath, "<?php\n\nRoute::middleware(['auth'])->group(function () {\n    Route::get('/d', fn() => 'd');\n});\n");

        $this->installer->install(overwrite: true, touchEnv: false);
        $this->installer->uninstall(false);

        $contents = $this->files->get($webPath);
        $this->assertStringNotContainsString('ATU Shipping Admin Routes', $contents);
        $this->assertStringContainsString("Route::middleware(['auth'])", $contents);
    }

    // ---------------------------------------------------------------------
    // Sidebar
    // ---------------------------------------------------------------------

    public function test_install_injects_sidebar_inside_platform_group_when_present(): void
    {
        $sidebar = $this->appPath . '/resources/views/layouts/app/sidebar.blade.php';
        $this->files->ensureDirectoryExists(dirname($sidebar));
        $this->files->put($sidebar, <<<'BLADE'
<flux:sidebar>
    <flux:sidebar.group :heading="__('Platform')">
        <flux:sidebar.item icon="home" href="/dashboard">Dashboard</flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar>
BLADE);

        $results = $this->installer->install(overwrite: true, touchEnv: false);
        $contents = $this->files->get($sidebar);

        $this->assertTrue($results['sidebar']['added']);
        $this->assertSame('platform_group', $results['sidebar']['placement']);
        $this->assertStringContainsString('{{-- >>> ATU Shipping Sidebar START --}}', $contents);
        $this->assertStringContainsString('{{-- >>> ATU Shipping Sidebar END --}}', $contents);
        $this->assertStringContainsString("route('admin.atu.shipping.couriers.index')", $contents);

        // Ensure block landed inside the Platform group.
        $platformPos = strpos($contents, ":heading=\"__('Platform')\"");
        $startPos = strpos($contents, '{{-- >>> ATU Shipping Sidebar START --}}');
        $closePos = strpos($contents, '</flux:sidebar.group>');
        $this->assertNotFalse($platformPos);
        $this->assertNotFalse($startPos);
        $this->assertNotFalse($closePos);
        $this->assertLessThan($startPos, $platformPos, 'Sidebar block must come after Platform group opener.');
        $this->assertLessThan($closePos, $startPos, 'Sidebar block must come before </flux:sidebar.group>.');
    }

    public function test_install_falls_back_to_components_layouts_app_sidebar_path(): void
    {
        $sidebar = $this->appPath . '/resources/views/components/layouts/app/sidebar.blade.php';
        $this->files->ensureDirectoryExists(dirname($sidebar));
        $this->files->put($sidebar, '<flux:sidebar></flux:sidebar>');

        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['sidebar']['added']);
        $this->assertStringContainsString('ATU Shipping Sidebar START', $this->files->get($sidebar));
    }

    public function test_install_skips_sidebar_when_no_blade_present(): void
    {
        $results = $this->installer->install(overwrite: true, touchEnv: false);

        $this->assertTrue($results['sidebar']['skipped']);
        $this->assertFalse($results['sidebar']['added']);
    }

    public function test_install_is_idempotent_for_sidebar(): void
    {
        $sidebar = $this->appPath . '/resources/views/layouts/app/sidebar.blade.php';
        $this->files->ensureDirectoryExists(dirname($sidebar));
        $this->files->put($sidebar, '<flux:sidebar></flux:sidebar>');

        $this->installer->install(overwrite: true, touchEnv: false);
        $first = $this->files->get($sidebar);
        $this->installer->install(overwrite: true, touchEnv: false);
        $second = $this->files->get($sidebar);

        $this->assertSame($first, $second);
        $this->assertSame(1, substr_count($second, '{{-- >>> ATU Shipping Sidebar START --}}'));
    }

    public function test_uninstall_removes_sidebar_block(): void
    {
        $sidebar = $this->appPath . '/resources/views/layouts/app/sidebar.blade.php';
        $this->files->ensureDirectoryExists(dirname($sidebar));
        $this->files->put($sidebar, '<flux:sidebar></flux:sidebar>');

        $this->installer->install(overwrite: true, touchEnv: false);
        $this->installer->uninstall(false);

        $contents = $this->files->get($sidebar);
        $this->assertStringNotContainsString('ATU Shipping Sidebar', $contents);
        $this->assertStringContainsString('<flux:sidebar>', $contents);
    }

    // ---------------------------------------------------------------------
    // Full uninstall round-trip
    // ---------------------------------------------------------------------

    public function test_uninstall_removes_copied_stub_files(): void
    {
        $this->files->put($this->appPath . '/routes/api.php', "<?php\n");
        $this->files->put($this->appPath . '/routes/web.php', "<?php\n");

        $this->installer->install(overwrite: true, touchEnv: false);

        $controller = $this->appPath . '/app/Http/Controllers/Atu/ShippingController.php';
        $config = $this->appPath . '/config/atu-shipping.php';
        $seeder = $this->appPath . '/database/seeders/ATUShippingSeeder.php';
        $view = $this->appPath . '/resources/views/livewire/admin/atu/shipping/couriers/index.blade.php';

        $this->assertFileExists($controller);
        $this->assertFileExists($config);
        $this->assertFileExists($seeder);
        $this->assertFileExists($view);

        $this->installer->uninstall(false);

        $this->assertFileDoesNotExist($controller);
        $this->assertFileDoesNotExist($config);
        $this->assertFileDoesNotExist($seeder);
        $this->assertFileDoesNotExist($view);
    }
}
