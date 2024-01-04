<?php

namespace Deployer;

require_once 'contrib/cachetool.php';

desc('Clears OPcode cache');
task('deploy:clear:opcache', function () {
    if (empty(get('cachetool_args'))) {
        warning('OPcacche clear is skipped because cachetool is not configured');
        return;
    }

    invoke('cachetool:clear:opcache');
});
