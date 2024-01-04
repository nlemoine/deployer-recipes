<?php

declare(strict_types=1);

namespace Deployer;

set('h5pb_target', '6.0.0');

/**
 * H5BP apache config repository
 */
set('h5pb_repository', 'https://github.com/h5bp/server-configs-apache.git');

/**
 * The destination path of the generated .htaccess file.
 */
set('htaccess_path', '{{release_or_current_path}}/public/.htaccess');

/**
 * The config file to use.
 * Can be a relative path to the repository root or an absolute path.
 * Note that Deployer variables are evaluated, the conf file can make use of them (e.g. {{release_or_current_path}}).
 *
 * Defaults to H5BP's config.
 *
 * @see https://github.com/h5bp/server-configs-apache/blob/main/bin/htaccess.conf
 */
set('htaccess_conf', '{{deploy_path}}/.dep/h5bp/bin/htaccess.conf');

desc('Generate .htaccess file from h5bp');
task('deploy:htaccess', function () {
    $git = get('bin/git');
    $repository = get('h5pb_repository');
    $target = get('h5pb_target');
    $htaccessConf = get('htaccess_conf');

    $repositoryPath = '{{deploy_path}}/.dep/h5bp';

    $bare = parse($repositoryPath);
    $env = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_SSH_COMMAND'     => get('git_ssh_command'),
    ];

    start:
    // Clone the repository to a bare repo.
    run("[ -d {$bare} ] || mkdir -p {$bare}");
    run("[ -f {$bare}/.git/HEAD ] || {$git} clone {$repository} {$bare} 2>&1", [
        'env' => $env,
    ]);

    cd($bare);

    // If remote url changed, drop `.dep/h5bp` and reinstall.
    if (run("{$git} config --get remote.origin.url") !== $repository) {
        cd('{{deploy_path}}');
        run("rm -rf {$bare}");
        goto start;
    }

    run("{$git} remote update 2>&1", [
        'env' => $env,
    ]);
    run("{$git} checkout --force {$target}");

    // Get the config file.
    $checkPaths = [
        "{{release_or_current_path}}/{$htaccessConf}",
        $htaccessConf,
    ];

    $htaccessConfPath = null;
    foreach ($checkPaths as $checkPath) {
        if (test("[ -f {$checkPath} ]")) {
            $htaccessConfPath = $checkPath;
            break;
        }
    }

    if (!$htaccessConfPath) {
        warning('Unable to find a valid htaccess.conf file. Please check your configuration.');
        return;
    }

    $confContent = run("cat {$htaccessConfPath}");
    $confContent = parse($confContent);
    run("echo '{$confContent}' > {$htaccessConfPath} 2>&1");
    run("bash {$repositoryPath}/bin/build.sh {{htaccess_path}} {$htaccessConfPath}");
});
