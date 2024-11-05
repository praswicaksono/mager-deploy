<?php

declare(strict_types=1);

namespace App\Component\App;

interface AppInstallerInterface
{
    public function install(string $name): void;
}
