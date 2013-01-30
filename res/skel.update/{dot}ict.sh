#!/usr/bin/env php
<?php
/**
 * This script is executed before composer dependencies are executed,
 * and as such must be included in each project as part of the skeleton.
 */
$path   = getenv('HOME') . '/.composer/config.json';
$dir    = dirname($path);
$config = <<<EOD
{
    "config" : {
        "github-oauth" : {
            "github.com": %token%
        }
    }
}
EOD;

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents(
    $path,
    str_replace('%token%', getenv('ICT_TOKEN'), $config)
);