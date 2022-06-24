<?php

namespace HelloNico\Deployer;

use function Deployer\before;

require_once __DIR__ . '/deploy/wp-cli.php';

before('deploy:symlink', 'deploy:wp:upgrade_db');
