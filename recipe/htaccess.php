<?php

namespace HelloNico\Deployer;

use function Deployer\before;

require_once __DIR__ . '/deploy/htaccess.php';

before('deploy:symlink', 'deploy:htaccess');
