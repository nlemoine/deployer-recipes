<?php declare(strict_types=1);

namespace HelloNico\Deployer;

use function Deployer\set;
use function Deployer\get;
use function Deployer\test;
use function Deployer\warning;
use function Deployer\desc;
use function Deployer\task;
use function Deployer\run;
use function Deployer\which;
use function Deployer\cd;
use function Deployer\commandExist;

set('vendor_dirs', ['{{release_path}}']);
set('composer_action', 'install');
set('composer_force_phar', true);

// Self update composer from time to time
// Set `false` to disable or an integer to update every N days
// Default to 30 days
set('composer_self_update', 30);

set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader --classmap-authoritative');

// Returns Composer binary path if found. Otherwise try to install latest
// composer version to `.dep/composer.phar`. To use specific composer version
// download desired phar and place it at `.dep/composer.phar`.
set('bin/composer', function () {
    $binPath = '{{deploy_path}}/.dep/composer.phar';
    if (test("[ -f $binPath ]")) {
        // If composer.phar is older than `composer_self_update` days, run self update
        if(
            get('composer_self_update', 0)
            && strtotime(sprintf('+%d days', (int) get('composer_self_update')), (int) run("stat -c %Y $binPath")) <= time()
        ) {
            warning("Composer is older than {{composer_self_update}} days, updating composer...");
            run("{{bin/php}} $binPath self-update");
            // Avoid running update on each deploy
            run("touch -m $(date +%s) $binPath");
        }

        return "{{bin/php}} $binPath";
    }

    if (commandExist('composer') && !get('composer_force_phar')) {
        return '{{bin/php}} ' . which('composer');
    }

    warning("Composer binary wasn't found. Installing latest composer to $binPath.");
    run("cd {{deploy_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}");
    run("mv {{deploy_path}}/composer.phar $binPath");
    return "{{bin/php}} $binPath";
});

desc('Installs vendors');
task('deploy:vendors', function () {
    cd('{{release_path}}');

    if (!commandExist('unzip')) {
        warning('To speed up composer installation setup "unzip" command with PHP zip extension.');
    }
    foreach (get('vendor_dirs', []) as $path) {
        run("cd $path && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1");
    }
});
