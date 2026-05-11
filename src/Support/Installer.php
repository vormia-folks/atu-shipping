<?php

namespace Vormia\ATUShipping\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class Installer
{
    /**
     * Env keys this package writes into .env / .env.example on install,
     * and removes on uninstall.
     */
    private const ENV_KEYS = [
        'ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY' => 'ZA',
        'ATU_SHIPPING_BASE_CURRENCY' => 'USD',
        'ATU_SHIPPING_ENABLE_LOGGING' => 'true',
    ];

    // API route markers (routes/api.php)
    private const API_ROUTE_MARK_START = '// >>> ATU Shipping API Routes START';
    private const API_ROUTE_MARK_END = '// >>> ATU Shipping API Routes END';

    // Admin route markers (routes/web.php)
    private const ADMIN_ROUTE_MARK_START = '// >>> ATU Shipping Admin Routes START';
    private const ADMIN_ROUTE_MARK_END = '// >>> ATU Shipping Admin Routes END';

    // Sidebar markers (blade)
    private const SIDEBAR_MARK_START = '{{-- >>> ATU Shipping Sidebar START --}}';
    private const SIDEBAR_MARK_END = '{{-- >>> ATU Shipping Sidebar END --}}';

    /**
     * Default API route block — uncommented, ready to use.
     * Follows the UILivewireFlux pattern of writing live routes (not comments).
     */
    private const API_ROUTE_BLOCK = <<<'PHP'
// >>> ATU Shipping API Routes START
Route::prefix('atu/shipping')->group(function () {
    Route::post('/calculate', [\App\Http\Controllers\Atu\ShippingController::class, 'calculate'])->name('api.shipping.calculate');
    Route::get('/options', [\App\Http\Controllers\Atu\ShippingController::class, 'options'])->name('api.shipping.options');
    Route::post('/select', [\App\Http\Controllers\Atu\ShippingController::class, 'select'])->name('api.shipping.select');
});
// >>> ATU Shipping API Routes END
PHP;

    /**
     * Default Admin (web) route block — uncommented, requires Livewire 4's Route::livewire().
     */
    private const ADMIN_ROUTE_BLOCK = <<<'PHP'
// >>> ATU Shipping Admin Routes START
Route::prefix('admin/atu/shipping')->name('admin.atu.shipping.')->group(function () {
    Route::livewire('couriers', 'admin.atu.shipping.couriers.index')->name('couriers.index');
    Route::livewire('couriers/create', 'admin.atu.shipping.couriers.create')->name('couriers.create');
    Route::livewire('couriers/{id}/edit', 'admin.atu.shipping.couriers.edit')->name('couriers.edit');

    Route::livewire('rules', 'admin.atu.shipping.rules.index')->name('rules.index');
    Route::livewire('rules/create', 'admin.atu.shipping.rules.create')->name('rules.create');
    Route::livewire('rules/{id}/edit', 'admin.atu.shipping.rules.edit')->name('rules.edit');

    Route::livewire('logs', 'admin.atu.shipping.logs.index')->name('logs.index');
});
// >>> ATU Shipping Admin Routes END
PHP;

    /**
     * Default sidebar menu block — Flux sidebar items, fenced by Blade comment markers
     * so the uninstaller can locate and remove them.
     */
    private const SIDEBAR_BLOCK = <<<'BLADE'
{{-- >>> ATU Shipping Sidebar START --}}
<flux:sidebar.item icon="truck" :href="route('admin.atu.shipping.couriers.index')" wire:navigate>
    {{ __('Shipping couriers') }}
</flux:sidebar.item>
<flux:sidebar.item icon="rectangle-stack" :href="route('admin.atu.shipping.rules.index')" wire:navigate>
    {{ __('Shipping rules') }}
</flux:sidebar.item>
<flux:sidebar.item icon="clipboard-document-list" :href="route('admin.atu.shipping.logs.index')" wire:navigate>
    {{ __('Shipping logs') }}
</flux:sidebar.item>
{{-- >>> ATU Shipping Sidebar END --}}
BLADE;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $stubsPath,
        private readonly string $appBasePath
    ) {}

    /**
     * Install fresh assets, env keys, routes and sidebar menu.
     *
     * @return array{
     *     copied: array{copied: list<string>, skipped: list<string>},
     *     env: array<string, list<string>>,
     *     routes: array,
     *     admin_routes: array,
     *     sidebar: array,
     * }
     */
    public function install(bool $overwrite = true, bool $touchEnv = true): array
    {
        return [
            'copied'        => $this->copyStubs($overwrite),
            'env'           => $touchEnv ? $this->ensureEnvKeys() : [],
            'routes'        => $this->ensureApiRoutes(),
            'admin_routes'  => $this->ensureAdminRoutes(),
            'sidebar'       => $this->ensureSidebarMenu(),
        ];
    }

    /**
     * Update simply re-runs install with overwrite.
     */
    public function update(bool $touchEnv = true): array
    {
        return $this->install(true, $touchEnv);
    }

    /**
     * Remove copied assets, env keys, routes and sidebar.
     */
    public function uninstall(bool $touchEnv = true): array
    {
        return [
            'removed'       => $this->removeStubTargets(),
            'env'           => $touchEnv ? $this->removeEnvKeys() : [],
            'routes'        => $this->removeApiRoutes(),
            'admin_routes'  => $this->removeAdminRoutes(),
            'sidebar'       => $this->removeSidebarMenu(),
        ];
    }

    // ---------------------------------------------------------------------
    // Stub copy
    // ---------------------------------------------------------------------

    /**
     * @return array{copied: list<string>, skipped: list<string>}
     */
    private function copyStubs(bool $overwrite): array
    {
        $results = ['copied' => [], 'skipped' => []];

        if (! $this->files->isDirectory($this->stubsPath)) {
            return $results;
        }

        foreach ($this->files->allFiles($this->stubsPath) as $file) {
            /** @var \SplFileInfo $file */
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $relative = ltrim(Str::after($file->getPathname(), $this->stubsPath), '/\\');
            [$root, $subPath] = $this->splitRoot($relative);
            $target = $this->targetPath($root, $subPath);

            if ($target === null) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($target));

            if (! $overwrite && $this->files->exists($target)) {
                $results['skipped'][] = $target;
                continue;
            }

            $this->files->copy($file->getPathname(), $target);
            $results['copied'][] = $target;
        }

        return $results;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRoot(string $relative): array
    {
        $parts = explode('/', $relative, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Map a stubs/<root>/<subPath> file to its destination in the host app.
     * Returns null for stubs we deliberately don't copy (migrations -> loaded
     * from the package; reference/ -> docs only).
     */
    private function targetPath(string $root, string $subPath): ?string
    {
        $root = trim($root, '/\\');

        $applicationRoots = [
            'app'           => '',
            'controllers'   => 'Http/Controllers',
            'models'        => 'Models',
            'services'      => 'Services',
            'notifications' => 'Notifications',
            'listeners'     => 'Listeners',
            'jobs'          => 'Jobs',
            'events'        => 'Events',
        ];

        if (array_key_exists($root, $applicationRoots)) {
            return $this->appPathWithPrefix($applicationRoots[$root], $subPath);
        }

        return match ($root) {
            'config'    => $this->pathJoin($this->appBasePath, 'config', $subPath),
            // migrations live inside the package and are loaded via
            // loadMigrationsFrom() in the service provider; do not copy.
            'migrations' => null,
            // reference/ holds documentation snippets — never copy.
            'reference' => null,
            'database'  => $this->pathJoin($this->appBasePath, 'database', $subPath),
            'resources' => $this->pathJoin($this->appBasePath, 'resources', $subPath),
            default     => null,
        };
    }

    private function appPathWithPrefix(string $prefix, string $relative): string
    {
        $relative = $this->normalizeAppRelative($relative);
        $segments = [$this->appBasePath, 'app'];

        if ($prefix !== '') {
            $segments[] = trim($prefix, '/\\');
        }

        if ($relative !== '') {
            $segments[] = $relative;
        }

        return $this->pathJoin(...$segments);
    }

    /**
     * Studly-case the first segment of an app-relative path so directories
     * like "atu" become "Atu" (PSR-4-friendly).
     */
    private function normalizeAppRelative(string $relative): string
    {
        $relative = ltrim($relative, '/\\');
        if ($relative === '') {
            return '';
        }

        $parts = explode('/', $relative);
        if (isset($parts[0]) && $parts[0] !== '') {
            $parts[0] = Str::studly($parts[0]);
        }

        return implode('/', $parts);
    }

    private function pathJoin(string ...$parts): string
    {
        $filtered = collect($parts)->filter(fn($p) => $p !== '');

        if ($filtered->isEmpty()) {
            return '';
        }

        $first = $filtered->first();
        $isAbsolute = str_starts_with($first, '/')
            || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:/i', $first));

        if ($isAbsolute) {
            $first = rtrim($first, '/\\');
            $rest = $filtered->skip(1)
                ->map(fn($p) => trim($p, '/\\'))
                ->filter(fn($p) => $p !== '');

            return $rest->isEmpty()
                ? $first
                : $first . DIRECTORY_SEPARATOR . $rest->implode(DIRECTORY_SEPARATOR);
        }

        return $filtered
            ->map(fn($p) => trim($p, '/\\'))
            ->implode(DIRECTORY_SEPARATOR);
    }

    // ---------------------------------------------------------------------
    // .env keys
    // ---------------------------------------------------------------------

    /**
     * @return array<string, list<string>> Map of env-file path to keys added.
     */
    public function ensureEnvKeys(): array
    {
        $paths = [
            $this->pathJoin($this->appBasePath, '.env'),
            $this->pathJoin($this->appBasePath, '.env.example'),
        ];

        $added = [];

        foreach ($paths as $envPath) {
            // Mirror Vormia behavior: only touch env files if they already exist.
            if (! $this->files->exists($envPath)) {
                $added[$envPath] = [];
                continue;
            }

            $existing = $this->files->get($envPath);
            $addedKeys = [];
            $updated = $this->appendEnvBlock($existing, $addedKeys);

            if ($updated !== $existing) {
                $this->files->put($envPath, $updated);
            }

            $added[$envPath] = $addedKeys;
        }

        return $added;
    }

    private function appendEnvBlock(string $current, ?array &$addedKeys = []): string
    {
        $addedKeys = [];
        $lines = rtrim($current) === '' ? [] : preg_split('/\r\n|\r|\n/', $current);
        $presentKeys = $this->extractExistingKeys($lines);

        foreach (self::ENV_KEYS as $key => $value) {
            if (! in_array($key, $presentKeys, true)) {
                $addedKeys[] = $key;
            }
        }

        if ($addedKeys === []) {
            return $current;
        }

        $block = ['# ATU Shipping Configuration'];
        foreach ($addedKeys as $key) {
            $block[] = $key . '=' . self::ENV_KEYS[$key];
        }

        $merged = array_merge($lines, $lines ? [''] : [], $block);

        return implode(PHP_EOL, $merged) . PHP_EOL;
    }

    private function extractExistingKeys(array $lines): array
    {
        $keys = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key] = explode('=', $line, 2);
            $keys[] = trim($key);
        }

        return $keys;
    }

    /**
     * @return array<string, list<string>>
     */
    public function removeEnvKeys(): array
    {
        $paths = [
            $this->pathJoin($this->appBasePath, '.env'),
            $this->pathJoin($this->appBasePath, '.env.example'),
        ];

        $removed = [];

        foreach ($paths as $envPath) {
            if (! $this->files->exists($envPath)) {
                $removed[$envPath] = [];
                continue;
            }

            $content = $this->files->get($envPath);
            $updated = $this->stripEnvKeys($content, $removedKeys);

            if ($updated !== $content) {
                $this->files->put($envPath, $updated);
            }

            $removed[$envPath] = $removedKeys;
        }

        return $removed;
    }

    private function stripEnvKeys(string $content, ?array &$removedKeys = []): string
    {
        $removedKeys = [];
        $lines = rtrim($content) === '' ? [] : preg_split('/\r\n|\r|\n/', $content);
        $remaining = [];

        foreach ($lines as $line) {
            // Drop the section header we wrote in.
            if (str_contains($line, '# ATU Shipping Configuration')) {
                continue;
            }

            // Keep other comments verbatim.
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, '#')) {
                $remaining[] = $line;
                continue;
            }

            if (str_contains($line, '=')) {
                [$key] = explode('=', $line, 2);
                $key = trim($key);

                if (array_key_exists($key, self::ENV_KEYS)) {
                    $removedKeys[] = $key;
                    continue;
                }
            }

            $remaining[] = $line;
        }

        // Collapse any runs of 3+ blank lines we may have left behind.
        $normalized = preg_replace("/[\r\n]{3,}/", "\n\n", implode(PHP_EOL, $remaining));

        return rtrim($normalized) . PHP_EOL;
    }

    // ---------------------------------------------------------------------
    // API routes (routes/api.php)
    // ---------------------------------------------------------------------

    /**
     * @return array{path: string, added: bool, skipped: bool}
     */
    public function ensureApiRoutes(): array
    {
        $apiPath = $this->pathJoin($this->appBasePath, 'routes', 'api.php');

        if (! $this->files->exists($apiPath)) {
            return ['path' => $apiPath, 'added' => false, 'skipped' => true];
        }

        $contents = $this->files->get($apiPath);

        if (str_contains($contents, self::API_ROUTE_MARK_START)) {
            return ['path' => $apiPath, 'added' => false, 'skipped' => false];
        }

        $contents = rtrim($contents) . "\n\n" . self::API_ROUTE_BLOCK . "\n";
        $this->files->put($apiPath, $contents);

        return ['path' => $apiPath, 'added' => true, 'skipped' => false];
    }

    /**
     * @return array{path: string, removed: bool}
     */
    public function removeApiRoutes(): array
    {
        return $this->removeMarkedBlock(
            $this->pathJoin($this->appBasePath, 'routes', 'api.php'),
            self::API_ROUTE_MARK_START,
            self::API_ROUTE_MARK_END
        );
    }

    // ---------------------------------------------------------------------
    // Admin routes (routes/web.php) — UILivewireFlux-style
    // ---------------------------------------------------------------------

    /**
     * Inject admin routes inside the auth middleware group in routes/web.php.
     * Falls back to appending at the end of the file (still marker-fenced)
     * if no auth group can be located.
     *
     * @return array{path: string, added: bool, skipped: bool, placement: string}
     */
    public function ensureAdminRoutes(): array
    {
        $webPath = $this->pathJoin($this->appBasePath, 'routes', 'web.php');

        if (! $this->files->exists($webPath)) {
            return ['path' => $webPath, 'added' => false, 'skipped' => true, 'placement' => 'none'];
        }

        $contents = $this->files->get($webPath);

        if (str_contains($contents, self::ADMIN_ROUTE_MARK_START)) {
            return ['path' => $webPath, 'added' => false, 'skipped' => false, 'placement' => 'existing'];
        }

        $indented = $this->indentBlock(self::ADMIN_ROUTE_BLOCK, '    ');
        $authPattern = '/(Route::middleware\(\s*\[[^\]]*[\'"]auth[\'"][^\]]*\]\s*\)\s*->\s*group\s*\(\s*function\s*\(\s*\)\s*\{)/s';

        if (preg_match($authPattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $insertAt = $matches[1][1] + strlen($matches[1][0]);
            $contents = substr_replace($contents, "\n" . $indented . "\n", $insertAt, 0);
            $this->files->put($webPath, $contents);

            return ['path' => $webPath, 'added' => true, 'skipped' => false, 'placement' => 'auth_group'];
        }

        // Fallback: append at the bottom; still marker-fenced for removal.
        $contents = rtrim($contents) . "\n\n" . self::ADMIN_ROUTE_BLOCK . "\n";
        $this->files->put($webPath, $contents);

        return ['path' => $webPath, 'added' => true, 'skipped' => false, 'placement' => 'appended'];
    }

    public function removeAdminRoutes(): array
    {
        return $this->removeMarkedBlock(
            $this->pathJoin($this->appBasePath, 'routes', 'web.php'),
            self::ADMIN_ROUTE_MARK_START,
            self::ADMIN_ROUTE_MARK_END
        );
    }

    // ---------------------------------------------------------------------
    // Sidebar menu (Flux sidebar blade)
    // ---------------------------------------------------------------------

    /**
     * Inject the Flux sidebar menu items inside the Platform group, falling
     * back to before </flux:sidebar> if no Platform group is present.
     *
     * @return array{path: string|null, added: bool, skipped: bool, placement: string}
     */
    public function ensureSidebarMenu(): array
    {
        $sidebarPath = $this->resolveSidebarPath();

        if ($sidebarPath === null) {
            return ['path' => null, 'added' => false, 'skipped' => true, 'placement' => 'none'];
        }

        $contents = $this->files->get($sidebarPath);

        if (str_contains($contents, self::SIDEBAR_MARK_START)) {
            return ['path' => $sidebarPath, 'added' => false, 'skipped' => false, 'placement' => 'existing'];
        }

        $indented = $this->indentBlock(self::SIDEBAR_BLOCK, '        ');

        // 1) Inject inside the Platform flux:sidebar.group, before its closing tag.
        // We can't match balanced quotes (the heading is often __('Platform')),
        // so just confirm the opening tag mentions "Platform" between its <...>.
        if (preg_match(
            '/<flux:sidebar\.group\b[^>]*Platform[^>]*>/i',
            $contents,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            $afterOpen = $match[0][1] + strlen($match[0][0]);
            $closeRel = strpos($contents, '</flux:sidebar.group>', $afterOpen);

            if ($closeRel !== false) {
                $contents = substr_replace($contents, "\n" . $indented . "\n    ", $closeRel, 0);
                $this->files->put($sidebarPath, $contents);

                return ['path' => $sidebarPath, 'added' => true, 'skipped' => false, 'placement' => 'platform_group'];
            }
        }

        // 2) Fallback: before </flux:sidebar>.
        $closeSidebar = strpos($contents, '</flux:sidebar>');
        if ($closeSidebar !== false) {
            $contents = substr_replace($contents, $indented . "\n", $closeSidebar, 0);
            $this->files->put($sidebarPath, $contents);

            return ['path' => $sidebarPath, 'added' => true, 'skipped' => false, 'placement' => 'sidebar_close'];
        }

        // 3) Last resort: append at end of file.
        $contents = rtrim($contents) . "\n\n" . self::SIDEBAR_BLOCK . "\n";
        $this->files->put($sidebarPath, $contents);

        return ['path' => $sidebarPath, 'added' => true, 'skipped' => false, 'placement' => 'appended'];
    }

    public function removeSidebarMenu(): array
    {
        $sidebarPath = $this->resolveSidebarPath();

        if ($sidebarPath === null) {
            return ['path' => null, 'removed' => false];
        }

        return $this->removeMarkedBlock(
            $sidebarPath,
            self::SIDEBAR_MARK_START,
            self::SIDEBAR_MARK_END
        );
    }

    /**
     * Locate the host app's main Flux sidebar blade file. Checks the
     * commonly-used locations used by livewire/flux starter kits.
     */
    public function resolveSidebarPath(): ?string
    {
        $candidates = [
            $this->pathJoin($this->appBasePath, 'resources', 'views', 'layouts', 'app', 'sidebar.blade.php'),
            $this->pathJoin($this->appBasePath, 'resources', 'views', 'components', 'layouts', 'app', 'sidebar.blade.php'),
        ];

        foreach ($candidates as $path) {
            if ($this->files->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Stub removal
    // ---------------------------------------------------------------------

    /**
     * Remove files we previously copied. Migrations are never touched here
     * since they're now loaded from the package itself.
     *
     * @return list<string>
     */
    private function removeStubTargets(): array
    {
        $removed = [];

        if (! $this->files->isDirectory($this->stubsPath)) {
            return $removed;
        }

        foreach ($this->files->allFiles($this->stubsPath) as $file) {
            /** @var \SplFileInfo $file */
            $relative = ltrim(Str::after($file->getPathname(), $this->stubsPath), '/\\');
            [$root, $subPath] = $this->splitRoot($relative);
            $target = $this->targetPath($root, $subPath);

            if ($target === null || ! $this->files->exists($target)) {
                continue;
            }

            $this->files->delete($target);
            $removed[] = $target;
            $this->pruneEmptyParents(dirname($target), $this->rootPathFor($root));
        }

        return $removed;
    }

    private function rootPathFor(string $root): ?string
    {
        $root = trim($root, '/\\');

        $applicationRoots = [
            'app'           => '',
            'controllers'   => 'Http/Controllers',
            'models'        => 'Models',
            'services'      => 'Services',
            'notifications' => 'Notifications',
            'listeners'     => 'Listeners',
            'jobs'          => 'Jobs',
            'events'        => 'Events',
        ];

        if (array_key_exists($root, $applicationRoots)) {
            return $this->pathJoin($this->appBasePath, 'app', $applicationRoots[$root]);
        }

        return match ($root) {
            'config'    => $this->pathJoin($this->appBasePath, 'config'),
            'database'  => $this->pathJoin($this->appBasePath, 'database'),
            'resources' => $this->pathJoin($this->appBasePath, 'resources'),
            default     => null,
        };
    }

    private function pruneEmptyParents(string $path, ?string $stopAt): void
    {
        if ($stopAt === null) {
            return;
        }

        $stopAt = rtrim($stopAt, '/\\');
        $path = rtrim($path, '/\\');

        while (str_starts_with($path, $stopAt)) {
            if (! $this->files->exists($path) || ! $this->files->isDirectory($path)) {
                break;
            }

            $contents = array_diff(scandir($path) ?: [], ['.', '..']);
            if ($contents !== []) {
                break;
            }

            $this->files->deleteDirectory($path);
            if ($path === $stopAt) {
                break;
            }

            $path = dirname($path);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Strip a START..END marker block from a file (with surrounding
     * blank-line cleanup). Used for routes and sidebar removal.
     *
     * @return array{path: string, removed: bool}
     */
    private function removeMarkedBlock(string $path, string $startMarker, string $endMarker): array
    {
        if (! $this->files->exists($path)) {
            return ['path' => $path, 'removed' => false];
        }

        $contents = $this->files->get($path);

        $pattern = sprintf(
            '#\n?[ \t]*%s.*?%s[ \t]*\n?#s',
            preg_quote($startMarker, '#'),
            preg_quote($endMarker, '#')
        );

        $updated = preg_replace($pattern, "\n", $contents, 1, $count);

        if ($count > 0) {
            $normalized = preg_replace("/[\r\n]{3,}/", "\n\n", $updated ?? '');
            $this->files->put($path, rtrim($normalized) . "\n");
        }

        return ['path' => $path, 'removed' => $count > 0];
    }

    /**
     * Indent every line of $block by $indent (preserving the existing newlines).
     */
    private function indentBlock(string $block, string $indent): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $block);
        $indented = array_map(fn(string $line) => $line === '' ? '' : $indent . $line, $lines);
        return implode("\n", $indented);
    }
}
