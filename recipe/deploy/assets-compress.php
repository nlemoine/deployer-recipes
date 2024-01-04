<?php

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\RunException;

/**
 * Get find command
 */
function getFindCommand(string $path, array $extensions): string
{
    $findFiles = implode(' -o ', array_map(fn ($ext) => "-iname \*.{$ext}", $extensions));
    return "find {$path} -type f \( {$findFiles} \)";
}

/**
 * Compress assets
 */
function compressAssets(array $dirs, string $compression, string $compressionArgs): void
{
    $compression = "{{bin/{$compression}}}";

    foreach ($dirs as $findArgs => $dir) {
        $findArgs = \is_string($findArgs) ? $findArgs : '';
        $remotePath = get('release_or_current_path') . "/{$dir}";
        $findCmd = getFindCommand($remotePath, get('assets_compress_extensions')) . ' ' . $findArgs;
        $findAndCompressCmd = $findCmd . " -exec {$compression} {$compressionArgs} -f {} \;";

        // Compress
        try {
            run($findAndCompressCmd);
        } catch (RunException $e) {
            warning("Failed to remotely compress assets in {$remotePath}: {$e->getMessage()}");
        }
    }
}

/**
 * Get Brotli binary depending on OS arch
 */
function getBrotliDownloadUrl(): string
{
    $arch = run('uname -m');
    $baseUrlformat = 'https://raw.githubusercontent.com/nlemoine/brotli-php/master/bin/linux/%s/brotli';
    return sprintf($baseUrlformat, $arch);
}

/**
 * Download and install brotli binary
 */
function downloadAndInstallBrotli(string $targetPath)
{
    $brotliUrl = getBrotliDownloadUrl();
    run("cd {{deploy_path}} && curl -O {$brotliUrl} && chmod +x brotli");
    run("mv {{deploy_path}}/brotli {$targetPath}");
}

// Self update brotli from time to time
// Set `false` to disable or an integer to update every N days
// Default to 360 days
set('brotli_self_update', 365);
// Force brotli installation
set('brotli_force_local', true);

set('bin/brotli', function () {
    $binPath = '{{deploy_path}}/.dep/brotli';
    if (test("[ -f {$binPath} ]")) {
        // If brotli is older than `brotli_self_update` days, run update
        if (
            get('brotli_self_update', 0)
            && strtotime(sprintf('+%d days', (int) get('brotli_self_update')), (int) run("stat -c %Y {$binPath}")) <= time()
        ) {
            warning('Brotli is older than {{brotli_self_update}} days, updating brotli...');
            downloadAndInstallBrotli($binPath);
            // Avoid running update on each deploy
            run("touch -m $(date +%s) {$binPath}");
        }

        return $binPath;
    }

    if (commandExist('brotli') && !get('brotli_force_local', true)) {
        return which('brotli');
    }

    warning("Brotli binary wasn't found. Installing latest brotli to {$binPath}.");
    downloadAndInstallBrotli($binPath);

    return $binPath;
});

set('bin/gzip', function () {
    if (commandExist('gzip')) {
        return which('gzip');
    }

    throw new \RuntimeException('gzip binary not found');
});

/**
 * Directories where to look for assets to compress
 *
 * @example ['' => 'public/assets']
 * @example ['-maxdepth 1' => 'public/assets'] Additional `find` args can be passed as key
 */
set('assets_compress_dirs', []);

/**
 * Extensions of files to compress
 *
 * @see https://github.com/h5bp/server-configs-apache/blob/main/h5bp/web_performance/pre-compressed_content_gzip.conf
 */
set('assets_compress_extensions', [
    'css',
    'js',
    'svg',
    'json',
    'html',
    'ics',
]);

/**
 * Compressions and args
 */
set('assets_compress_compressions', [
    'gzip'   => '-k -9',
    'brotli' => '',
]);

desc('Pre compress static assets');
task('deploy:assets:compress', function () {
    $assetsDirs = get('assets_compress_dirs');
    if (empty($assetsDirs)) {
        return;
    }

    $assetsCompressions = get('assets_compress_compressions');

    if (\count(array_intersect(array_keys($assetsCompressions), ['brotli', 'gzip'])) === 0) {
        throw new \Exception('Illegal compression');
    }

    foreach ($assetsCompressions as $compression => $compressionArgs) {
        try {
            get('bin/' . $compression);
        } catch (\Exception $e) {
            warning("Compression {$compression} is not available: {$e->getMessage()}");
            unset($assetsCompressions[$compression]);
            continue;
        }
        compressAssets($assetsDirs, $compression, $compressionArgs);
    }
});
