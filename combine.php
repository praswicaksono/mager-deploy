#!/usr/bin/env php
<?php

$ini = <<<INI
memory_limit="1G"
INI;

$ini_part = "\xfd\xf6\x69\xe6";
$ini_part .= pack('N', strlen($ini));
$ini_part .= $ini;

$target = file_get_contents('./build/micro.sfx') . $ini_part . file_get_contents('./build/mager.phar');
file_put_contents('./build/mager', $target);
chmod('./build/mager', 0755);