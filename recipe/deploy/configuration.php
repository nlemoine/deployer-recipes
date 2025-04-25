<?php

declare(strict_types=1);

namespace Deployer;

use Symfony\Component\Process\Process;

$repository = get('repository');
if (empty($repository)) {
    set('repository', function () {
        $process = Process::fromShellCommandline('git remote get-url origin');
        $default = $process->mustRun()->getOutput();
        return trim($default);
    });
}
unset($repository);
