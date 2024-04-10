<?php

namespace Deployer;

require_once 'contrib/crontab.php';

set('crontab:identifier', function () {
    $identifier = [
        get('application', pathinfo(get('repository'))['filename'] ?? 'application'),
        get('stage', null),
    ];
    return implode(':', array_filter($identifier));
});


desc('Sync crontab jobs');
task('deploy:crontab:sync', function () {
    try {
        get('bin/crontab');
    } catch(\Exception $e) {
        warning('crontab is not available, skipping setting up the cron jobs');
        return;
    }
    try {
        invoke('crontab:sync');
    } catch(\Exception $e) {
        warning('could not install the crontab jobs');
    }
});
