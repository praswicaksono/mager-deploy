<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Service;
use Doctrine\Common\Collections\Collection;

interface DefinitionBuilder
{
    /**
     * @return Collection<int, Service>
     */
    public function build(string $definitionPath = '.mager.yaml'): Collection;
}
