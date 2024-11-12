<?php

declare(strict_types=1);

namespace App\Component\Config;

interface DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml', ?string $override = null): Definition;
}
