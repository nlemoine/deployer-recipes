<?php

namespace HelloNico\Deployer;

use function Deployer\after;

require_once __DIR__ . '/deploy/assets-compress.php';

after('deploy:update_code', 'deploy:assets:compress');
