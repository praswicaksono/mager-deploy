<?php

declare(strict_types=1);

namespace App\Component\Config\Trait;

trait VolumeParser
{
    /**
     * @return array<int, string>
     */
    public function parseToDockerVolume(string $namespace): array
    {
        $dockerMounts = [];
        foreach ($this->volumes as $volume) {
            @[$src, $dest, $flag] = explode(':', $volume);

            if (file_exists($src) || is_dir($src)) {
                $mount = "type=bind,source={$src},destination={$dest}";
                if (null !== $flag) {
                    $mount .= ",{$flag}";
                }
                $dockerMounts[] = $mount;
            } else {
                $dockerMounts[] = "type=volume,source={$namespace}-{$src},destination={$dest}";
            }
        }

        return $dockerMounts;
    }
}
