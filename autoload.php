<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Hartenthaler\\Webtrees\\Module\\ClippingsCart\\', __DIR__);
$loader->register();
