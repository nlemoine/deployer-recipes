<?php

namespace Deployer;

require_once 'recipe/common.php';
require_once __DIR__ . '/wordpress.php';
require_once __DIR__ . '/crontab.php';
require_once __DIR__ . '/cachetool.php';

add('crontab:jobs', [
    '{{wordpress_cron_job}}',
]);

task('deploy:success', function () {
    info('Successfully deployed!');

    if (get('wordpress_home_url')) {
        writeln('🚀');
        writeln('{{wordpress_home_url}}');
    }
})->hidden();

// DEPLOY
// Step 1: Prepare
desc('Prepares a new release');
task('deploy:prepare', [
    'deploy:info', // Show info
    'deploy:setup', // Prepare server
    'deploy:lock', // Lock deployment
    'deploy:release', // Prepare release
    'deploy:update_code', // Update code
    'deploy:shared', // Create symlinks for shared dirs and files
    'deploy:writable', // Make writable dirs
]);

// Step 2: Install dependencies
desc('Installs vendors, core, plugins, themes, languages');
task('deploy:dependencies', [
    'deploy:vendors', // Install vendors
]);

// Step 3: Clear cache, paths, etc.
desc('Clear caches, paths, etc.');
task('deploy:clear', [
    'deploy:clear_paths', // Clear paths
    'wordpress:cache:flush', // Flush object cache
    'wordpress:db:migrate', // Migrate database
    'wordpress:rewrite:flush', // Flush rewrite rules
    'wordpress:commands', // Custom wp-cli commands
]);

// Step 4: Publish release
desc('Publishes the release');
task('deploy:publish', [
    'deploy:crontab:sync',
    'wordpress:check', // Last check before release
    'deploy:symlink', // Symlink release to current (⚠️ new release is live)
    'deploy:clear:opcache', // Clear opcache cache
    'deploy:unlock', // Unlock deployment
    'deploy:cleanup', // Cleanup old releases
    'deploy:success', // Show success message
]);

// Main task
task('deploy', [
    'deploy:prepare', // Prepare
    'deploy:dependencies', // Install dependencies
    'deploy:clear', // Clear stuffs
    'deploy:publish', // Publish release
]);

after('deploy:failed', 'deploy:unlock');
