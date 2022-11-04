<?php

namespace HelloNico\Deployer;

use function Deployer\before;

require_once __DIR__ . '/deploy/assets-compress.php';

before('deploy:publish', 'deploy:assets:compress');
