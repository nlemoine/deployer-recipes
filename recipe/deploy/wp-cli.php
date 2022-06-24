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
use function Deployer\commandExist;
use function Deployer\within;

// Self update wp-cli from time to time
// Set `false` to disable or an integer to update every N days
// Default to 30 days
set('wpcli_self_update', 30);
set('wpcli_force_phar', true);

desc('Install WP-CLI');
set('bin/wp', function () {
    $binPath = '{{deploy_path}}/.dep/wp-cli.phar';
    if (test("[ -f $binPath ]")) {
        // If wp-cli.phar is older than `wpcli_self_update` days, run self update
        if(
            get('wpcli_self_update', 0)
            && strtotime(sprintf('+%d days', (int) get('wpcli_self_update')), (int) run("stat -c %Y $binPath")) <= time()
        ) {
            warning("WP CLI is older than {{wpcli_self_update}} days, updating wp-cli...");
            run("{{bin/php}} $binPath cli update");
            // Avoid running update on each deploy
            run("touch -m $(date +%s) $binPath");
        }

        return "{{bin/php}} $binPath";
    }

    if (commandExist('wp') && !get('wpcli_force_phar')) {
        return which('wp');
    }

    warning("WP CLI binary wasn't found. Installing latest WP CLI to $binPath.");
    run("cd {{deploy_path}} && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar");
    run("mv {{deploy_path}}/wp-cli.phar $binPath");
    return "{{bin/php}} $binPath";
});

desc( 'Runs the WordPress database update procedure' );
task('deploy:wp:upgrade_db', function () {
    try {
        within('{{release_path}}', function () {
            $is_multisite = test( "{{bin/wp}} core is-installed --network" );
            run( "{{bin/wp}} core update-db" . ( $is_multisite ? ' --network' : '' ) );
        });
    } catch ( \Throwable $t ) {
        warning( 'WordPress database could not be updated. Run manually via wp-admin/upgrade.php if necessary.' );
    }
});
