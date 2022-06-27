<?php declare(strict_types=1);

namespace HelloNico\Deployer;

use function Deployer\set;
use function Deployer\get;
use function Deployer\warning;
use function Deployer\desc;
use function Deployer\task;
use function Deployer\run;
use function Deployer\which;
use function Deployer\runLocally;
use Deployer\Exception\RunException;
use function Deployer\upload;
use function HelloNico\Deployer\whichLocally;

/**
 * Get find command
 *
 * @param string $path
 * @param array $extensions
 * @return string
 */
function getFindCommand(string $path, array $extensions): string {
    $findFiles = implode(' -o ', array_map(fn($ext) => "-iname \*.$ext", $extensions));
    return "find $path -type f \( $findFiles \)";
}

/**
 * Compress assets
 *
 * @param array $dirs
 * @param string $compression
 * @param string $compressionArgs
 * @return void
 */
function compressAssets(array $dirs, string $compression, string $compressionArgs): void {
    $local = false;
    try {
        which($compression);
    } catch (RunException $e) {
        warning("$compression is not installed remotely, trying locally");
        $local = true;
    }

    if ($local) {
        try {
            whichLocally($compression);
        } catch (RunException $e) {
            warning("$compression was not found, either locally or remotely, skipping compression: {$e->getMessage()}");
            return;
        }
    }

    foreach ($dirs as $findArgs => $dir) {
        $findArgs = $findArgs ? $findArgs : '';
        $localPath = $dir;
        $remotePath = get('release_path') . "/$dir";
        $path = $local ? $localPath : $remotePath;

        $findCmd = getFindCommand($path, get('assets_compress_extensions')) . ' ' . $findArgs;
        $findAndCompressCmd = $findCmd . " -exec $compression $compressionArgs -f {} \;";

        // Compress remotely
        if (!$local) {
            try {
                run($findAndCompressCmd);
            } catch (RunException $e) {
                warning("Failed to remotely compress assets in $path: {$e->getMessage()}");
            }
            continue;
        }

        // Compress locally
        try {
            runLocally($findAndCompressCmd);
        } catch (RunException $e) {
            warning("Failed to locally compress assets in $path: {$e->getMessage()}");
            continue;
        }

        // Upload to remote
        $compressionExtensionsMap = [
            'gzip' => 'gz',
            'brotli' => 'br',
        ];
        $compressionExtension = $compressionExtensionsMap[$compression] ?? null;
        if (!$compressionExtension) {
            warning("Unknown compression extension for $compression");
            continue;
        }

        // Find compressed files
        $files = runLocally(getFindCommand($dir, [$compressionExtension]) . ' ' . $findArgs);

        // Make files path relative to $dir
        $files = array_map(function($f) use($dir) {
            return str_replace("$dir/", '', $f);
        }, explode("\n", $files));

        if (empty($files)) {
            warning("No $compression compressed files found to upload in $dir");
            continue;
        }

        // Create tmp dir
        $filesPath = md5($dir) . ".txt";
        // Save files list
        if (!file_put_contents($filesPath, implode("\n", $files))) {
            warning("Could not save files list to $filesPath");
            continue;
        }

        // $rsyncOptions = [
        //     "--ignore-existing",
        //     "--include='*/'",
        // ];
        // $rsyncOptions[] = "--include='*.{$compressionExtensionsMap[$compression]}'";
        // $rsyncOptions[] = "--exclude='*'";

        // $rsyncOptions[] = '--dry-run';
        $rsyncOptions[] = "--files-from=$filesPath";

        // Upload files
        upload($dir  . "/", $remotePath, [
            'options' => $rsyncOptions,
            'display_stats' => true,
        ]);

        // Remove tmp dir
        runLocally("rm $filesPath");
    }
}

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
    'gzip' => '-k -9',
    'brotli' => '',
]);

desc('Pre compress static assets');
task('deploy:assets:compress', function() {

    $assetsDirs = get('assets_compress_dirs');
    if (empty($assetsDirs)) {
        return;
    }

    $assetsCompressions = get('assets_compress_compressions');

    if (count(array_intersect(array_keys($assetsCompressions), ['brotli', 'gzip'])) === 0) {
        throw new \Exception('Illegal compression');
    }

    foreach ($assetsCompressions as $compression => $compressionArgs) {
        compressAssets($assetsDirs, $compression, $compressionArgs);
    }
});

