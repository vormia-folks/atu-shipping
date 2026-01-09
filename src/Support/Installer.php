<?php

namespace Vormia\ATUShipping\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class Installer
{
    // Define environment keys to add/remove (empty for now, can be added later)
    private const ENV_KEYS = [
        // 'ATU_SHIPPING_DEFAULT_COUNTRY' => '',
    ];

    // Route markers for injection
    private const ROUTE_MARK_START = '// >>> ATU Shipping Routes START';
    private const ROUTE_MARK_END = '// >>> ATU Shipping Routes END';
    private const ROUTE_BLOCK = <<<'PHP'
// >>> ATU Shipping Routes START
// Shipping calculation endpoints (if needed)
// Route::prefix('atu/shipping')->group(function () {
//     Route::post('/calculate', [\App\Http\Controllers\Atu\ShippingController::class, 'calculate'])->name('api.shipping.calculate');
//     Route::get('/options', [\App\Http\Controllers\Atu\ShippingController::class, 'options'])->name('api.shipping.options');
//     Route::post('/select', [\App\Http\Controllers\Atu\ShippingController::class, 'select'])->name('api.shipping.select');
// });
// >>> ATU Shipping Routes END
PHP;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $stubsPath,
        private readonly string $appBasePath
    ) {}

    /**
     * Install fresh assets and env keys.
     *
     * @return array{copied: array, env: array, routes: array}
     */
    public function install(bool $overwrite = true, bool $touchEnv = true): array
    {
        $copied = $this->copyStubs($overwrite);
        $envChanges = $touchEnv ? $this->ensureEnvKeys() : [];
        $routes = $this->ensureRoutes();

        return ['copied' => $copied, 'env' => $envChanges, 'routes' => $routes];
    }

    /**
     * Update simply re-runs install with overwrite.
     */
    public function update(bool $touchEnv = true): array
    {
        return $this->install(true, $touchEnv);
    }

    /**
     * Remove copied assets and env keys.
     *
     * @return array{removed: array, env: array, routes: array}
     */
    public function uninstall(bool $touchEnv = true): array
    {
        $removed = $this->removeStubTargets();
        $env = $touchEnv ? $this->removeEnvKeys() : [];
        $routes = $this->removeRoutes();

        return ['removed' => $removed, 'env' => $env, 'routes' => $routes];
    }

    private function copyStubs(bool $overwrite): array
    {
        $results = ['copied' => [], 'skipped' => []];
        $stubFiles = $this->files->allFiles($this->stubsPath);

        foreach ($stubFiles as $file) {
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

            if (!$overwrite && $this->files->exists($target)) {
                $results['skipped'][] = $target;
                continue;
            }

            $this->files->copy($file->getPathname(), $target);
            $results['copied'][] = $target;
        }

        return $results;
    }

    private function splitRoot(string $relative): array
    {
        $parts = explode('/', $relative, 2);
        $root = $parts[0] ?? '';
        $rest = $parts[1] ?? '';

        return [$root, $rest];
    }

    private function targetPath(string $root, string $subPath): ?string
    {
        $root = trim($root, '/\\');

        $applicationRoots = [
            'app' => '',
            'controllers' => 'Http/Controllers',
            'models' => 'Models',
            'services' => 'Services',
            'notifications' => 'Notifications',
            'listeners' => 'Listeners',
            'jobs' => 'Jobs',
            'events' => 'Events',
        ];

        if (array_key_exists($root, $applicationRoots)) {
            return $this->appPathWithPrefix($applicationRoots[$root], $subPath);
        }

        return match ($root) {
            'config' => $this->pathJoin($this->appBasePath, 'config', $subPath),
            'migrations' => $this->pathJoin($this->appBasePath, 'database', 'migrations', $subPath),
            'database' => $this->pathJoin($this->appBasePath, 'database', $subPath),
            'resources' => $this->pathJoin($this->appBasePath, 'resources', $subPath),
            default => null,
        };
    }

    private function appPath(string $relative): string
    {
        return $this->appPathWithPrefix('', $relative);
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
        $isAbsolute = str_starts_with($first, '/') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:/i', $first));

        // Preserve absolute path prefix
        if ($isAbsolute) {
            // For absolute paths, only trim trailing slashes from first part
            $first = rtrim($first, '/\\');
            $rest = $filtered->skip(1)
                ->map(fn($p) => trim($p, '/\\'))
                ->filter(fn($p) => $p !== '');

            return $rest->isEmpty()
                ? $first
                : $first . DIRECTORY_SEPARATOR . $rest->implode(DIRECTORY_SEPARATOR);
        }

        // For relative paths, trim all slashes from all parts
        return $filtered
            ->map(fn($p) => trim($p, '/\\'))
            ->implode(DIRECTORY_SEPARATOR);
    }

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
                $added[$envPath] = $addedKeys;
            } else {
                $added[$envPath] = [];
            }
        }

        return $added;
    }

    private function appendEnvBlock(string $current, ?array &$addedKeys = []): string
    {
        $addedKeys = [];
        $lines = rtrim($current) === '' ? [] : preg_split('/\r\n|\r|\n/', $current);
        $presentKeys = $this->extractExistingKeys($lines);

        foreach (self::ENV_KEYS as $key => $value) {
            if (!in_array($key, $presentKeys, true)) {
                $addedKeys[] = $key;
            }
        }

        if ($addedKeys === []) {
            return $current;
        }

        $block = [];
        $block[] = '# ATU Shipping Configuration';
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
            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key] = explode('=', $line, 2);
            $keys[] = trim($key);
        }

        return $keys;
    }

    public function removeEnvKeys(): array
    {
        $paths = [
            $this->pathJoin($this->appBasePath, '.env'),
            $this->pathJoin($this->appBasePath, '.env.example'),
        ];

        $removed = [];

        foreach ($paths as $envPath) {
            if (!$this->files->exists($envPath)) {
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

    public function ensureRoutes(): array
    {
        $apiPath = $this->pathJoin($this->appBasePath, 'routes', 'api.php');
        $updated = false;

        if (! $this->files->exists($apiPath)) {
            return [
                'path' => $apiPath,
                'added' => false,
                'import_added' => false,
                'skipped' => true,
            ];
        }

        $contents = $this->files->get($apiPath);

        if (! str_contains($contents, self::ROUTE_MARK_START)) {
            $contents = rtrim($contents) . "\n\n" . self::ROUTE_BLOCK . "\n";
            $this->files->put($apiPath, $contents);
            $updated = true;
        }

        return [
            'path' => $apiPath,
            'added' => $updated,
            'import_added' => false,
            'skipped' => false,
        ];
    }

    public function removeRoutes(): array
    {
        $apiPath = $this->pathJoin($this->appBasePath, 'routes', 'api.php');
        if (!$this->files->exists($apiPath)) {
            return ['path' => $apiPath, 'removed' => false];
        }

        $contents = $this->files->get($apiPath);
        $pattern = sprintf(
            '#\\n?%s.*?%s\\s*\\n?#s',
            preg_quote(self::ROUTE_MARK_START, '#'),
            preg_quote(self::ROUTE_MARK_END, '#')
        );

        $updated = preg_replace($pattern, "\n", $contents, 1, $count);

        if ($count > 0) {
            $normalized = preg_replace("/[\r\n]{3,}/", "\n\n", $updated ?? '');
            $this->files->put($apiPath, rtrim($normalized) . "\n");
        }

        return ['path' => $apiPath, 'removed' => $count > 0];
    }

    private function stripEnvKeys(string $content, ?array &$removedKeys = []): string
    {
        $removedKeys = [];
        $lines = rtrim($content) === '' ? [] : preg_split('/\r\n|\r|\n/', $content);
        $remaining = [];

        foreach ($lines as $line) {
            // Skip the ATU Shipping Configuration comment line
            if (str_contains($line, '# ATU Shipping Configuration')) {
                continue;
            }

            // Skip lines that are comments
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, '#')) {
                $remaining[] = $line;
                continue;
            }

            // Check if this line contains an ATU env key
            if (str_contains($line, '=')) {
                [$key] = explode('=', $line, 2);
                $key = trim($key);

                // Remove any leading/trailing whitespace and check against ENV_KEYS
                if (array_key_exists($key, self::ENV_KEYS)) {
                    $removedKeys[] = $key;
                    continue; // Skip this line
                }
            }

            $remaining[] = $line;
        }

        // Normalize extra blank lines (remove 3+ consecutive newlines)
        $normalized = preg_replace("/[\r\n]{3,}/", "\n\n", implode(PHP_EOL, $remaining));

        return rtrim($normalized) . PHP_EOL;
    }

    private function removeStubTargets(): array
    {
        $removed = [];
        $stubFiles = $this->files->allFiles($this->stubsPath);

        foreach ($stubFiles as $file) {
            /** @var \SplFileInfo $file */
            $relative = ltrim(Str::after($file->getPathname(), $this->stubsPath), '/\\');
            [$root, $subPath] = $this->splitRoot($relative);
            $target = $this->targetPath($root, $subPath);

            if ($target === null || !$this->files->exists($target)) {
                continue;
            }

            // Skip if the file is a migration file
            if (str_contains($target, 'migrations')) {
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
            'app' => '',
            'controllers' => 'Http/Controllers',
            'models' => 'Models',
            'services' => 'Services',
            'notifications' => 'Notifications',
            'listeners' => 'Listeners',
            'jobs' => 'Jobs',
            'events' => 'Events',
        ];

        if (array_key_exists($root, $applicationRoots)) {
            return $this->pathJoin($this->appBasePath, 'app', $applicationRoots[$root]);
        }

        return match ($root) {
            'config' => $this->pathJoin($this->appBasePath, 'config'),
            'migrations' => $this->pathJoin($this->appBasePath, 'database', 'migrations'),
            'database' => $this->pathJoin($this->appBasePath, 'database'),
            'resources' => $this->pathJoin($this->appBasePath, 'resources'),
            default => null,
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
            if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
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
}
