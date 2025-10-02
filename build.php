<?php
declare(strict_types=1);
require_once 'abivia-shortlinks/vendor/autoload.php';

/**
 * Copy required files into a staging directory and run composer in non-dev mode.
 */
function stageUp($toDir, $name) {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $home = "$toDir/$name";
    echo "Removing Existing build\n";
    $filesystem->remove($home);
    $filesystem->mkdir($home);
    echo "Copy composer.json\n";
    $filesystem->copy(
        __DIR__ . "/$name/composer.json",
        "$toDir/$name/composer.json",
        true
    );
    echo "Copy files\n";
    $filesystem->mirror(__DIR__ . "/$name", $home);
    echo "Remove development /vendor\n";
    $filesystem->remove("$home/vendor");
    chdir($home);
    echo "Run composer\n";
    exec('composer install --no-dev');
    chdir(__DIR__);
    echo "Build completed.\n";
}

stageUp(__DIR__ . '/_build', 'abivia-shortlinks');
