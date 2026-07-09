<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

is_file($autoload) ? require $autoload : require dirname(__DIR__) . '/dev/autoload.php';
