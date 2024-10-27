<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private string $projectDir;

    public function getProjectDir(): string
    {
        if (!isset($this->projectDir)) {
            $r = new \ReflectionObject($this);

            if (!is_file($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = \dirname($dir);
            while (!is_file($dir . '/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return \dirname(__DIR__);
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }
}
