<?php

namespace Deployer;

require_once __DIR__ . '/deploy/htaccess.php';

before('deploy:publish', 'deploy:htaccess');
