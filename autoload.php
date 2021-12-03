<?php

namespace Hartenthaler\Webtrees\Module\ClippingsCartEnhanced;

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Hartenthaler\\Webtrees\\Module\\ClippingsCartEnhanced\\', __DIR__);
$loader->addPsr4('Hartenthaler\\Webtrees\\Module\\ClippingsCartEnhanced\\', __DIR__ . '/src');
$loader->register();
