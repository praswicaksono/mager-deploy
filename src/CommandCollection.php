<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Command\Command;

final readonly class CommandCollection
{
    /**
     * @param iterable<Command> $commands
     */
    public function __construct(public iterable $commands) {}
}
