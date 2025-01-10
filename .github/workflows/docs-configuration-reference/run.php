#!/usr/bin/env php
<?php

if ($argc !== 4) {
    echo "./run.php path-to-symfony path-to-docs-page FrameworkBundle \n";
    exit(2);
}

// Input
$symfony = $argv[1];
$docPage = $argv[2];
$bundleName = $argv[3];
require $symfony.'/vendor/autoload.php';

$referenceContent = file_get_contents($docPage);
$process = new \Symfony\Component\Process\Process(['bin/console','config:dump-reference', $bundleName, '--format', 'yaml'], $symfony);
$process->run();
if (0 !== $process->getExitCode()) {
    error_log("We could not get configuration reference\n");
    error_log($process->getErrorOutput());
    exit(3);
}
$output = $process->getOutput();
$config = \Symfony\Component\Yaml\Yaml::parse($output);

// always remove the first key
$config = $config[$key = array_key_first($config)];

$missingKeys = [];
parseConfigKeys($referenceContent, $config, $key, $missingKeys);

if (count($missingKeys) === 0) {
    error_log("We found nothing\n");
}

foreach ($missingKeys as $key) {
    echo '- '.$key.PHP_EOL;
}

exit(0);

function parseConfigKeys(string $doc, array $config, string $base, array &$missingKeys) {
    foreach ($config as $key => $value) {
        if (!is_numeric($key) && !str_contains($doc, $key)) {
            $missingKeys[] = $base . '.' . $key;
        }
        if (is_array($value)) {
            parseConfigKeys($doc, $value, $base . '.' . $key, $missingKeys);
        }
    }
}
