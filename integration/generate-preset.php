<?php

declare(strict_types=1);

set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if ((error_reporting() & $level) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

/** Exercise the public installer in its own disposable project directory. */
[$script, $workspace, $preset] = $argv;
if (!in_array($preset, ['web', 'api', 'cli'], true)) {
    throw new InvalidArgumentException('Unsupported preset gate.');
}
require $workspace . '/vendor/autoload.php';

$source = $workspace . '/sources/skeleton';
$target = $workspace . '/presets/' . $preset;
if (file_exists($target)) {
    throw new RuntimeException('Preset target must be a new directory.');
}
mkdir($target, 0755, true);
$entries = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $entry) use ($source): bool {
            $path = substr($entry->getPathname(), strlen($source) + 1);

            return !in_array($path, ['vendor', '.git', '.phpunit.cache', 'integration/.work'], true);
        },
    ),
    RecursiveIteratorIterator::SELF_FIRST,
);
foreach ($entries as $entry) {
    $path = $target . substr($entry->getPathname(), strlen($source));
    if ($entry->isLink()) {
        symlink(readlink($entry->getPathname()), $path);
    } elseif ($entry->isDir()) {
        mkdir($path, 0755, true);
    } else {
        copy($entry->getPathname(), $path);
    }
}

chdir($target);
require $target . '/installer/Installer.php';
$io = $preset === 'web'
    ? new Composer\IO\NullIO()
    : new class ($preset) extends Composer\IO\NullIO {
        public function __construct(private readonly string $preset)
        {
        }

        public function isInteractive(): bool
        {
            return true;
        }

        public function ask($question, $default = null)
        {
            return str_starts_with($question, 'Choose application preset')
                ? match ($this->preset) { 'api' => '2', 'cli' => '3' }
                : $default;
        }
    };
$composer = Composer\Factory::create($io, disablePlugins: true, disableScripts: true);
Installer::install(new Composer\Script\Event('post-root-package-install', $composer, $io));
$generated = json_decode(file_get_contents($target . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
copy($target . '/composer.json', $target . '/composer.generated.json');

// Detect an accidental menu/default change instead of silently testing another preset.
$hasHttp = isset($generated['require']['componenta/app-http']);
$hasTemplates = isset($generated['require']['componenta/templater-app']);
if ($hasHttp !== ($preset !== 'cli') || $hasTemplates !== ($preset === 'web')) {
    throw new RuntimeException('Installer did not generate the expected ' . $preset . ' preset.');
}
$workspaceManifest = json_decode(file_get_contents($workspace . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$generated['repositories'] = [[
    'type' => 'path', 'url' => $workspace . '/sources/*',
    'options' => $workspaceManifest['repositories'][0]['options'],
]];
file_put_contents($target . '/composer.json', json_encode($generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo 'Generated ' . $preset . ' using the public Installer::install entry point.' . "\n";
