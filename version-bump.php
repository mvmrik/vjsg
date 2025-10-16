<?php

if ($argc < 4) {
    echo 'Usage: php version-bump.php <major_inc> <minor_inc> <patch_inc>' . PHP_EOL;
    exit(1);
}

$majorInc = (int)$argv[1];
$minorInc = (int)$argv[2];
$patchInc = (int)$argv[3];

echo "Args: major=$majorInc, minor=$minorInc, patch=$patchInc\n";

$file = 'resources/js/components/GameApp.vue';
$content = file_get_contents($file);

preg_match('/const appVersion = \'([^\']+)\'/u', $content, $matches);
if (!$matches) {
    echo 'Version not found in GameApp.vue.' . PHP_EOL;
    exit(1);
}

$currentVersion = $matches[1];
echo "Current version: $currentVersion\n";

list($major, $minor, $patch) = explode('.', $currentVersion);

echo "Parsed: major=$major, minor=$minor, patch=$patch\n";

$major += $majorInc;
$minor += $minorInc;
$patch += $patchInc;

echo "After adding: major=$major, minor=$minor, patch=$patch\n";

if ($majorInc > 0) {
    $minor = 0;
    $patch = 0;
    echo "Major bump - resetting minor and patch\n";
} elseif ($minorInc > 0) {
    $patch = 0;
    echo "Minor bump - resetting patch\n";
}

$newVersion = $major . '.' . $minor . '.' . $patch;
echo "New version: $newVersion\n";

$content = preg_replace('/(const appVersion = )\'[^\']*\'/u', '$1\'' . $newVersion . '\'', $content);
file_put_contents($file, $content);

echo 'Version bumped from ' . $currentVersion . ' to ' . $newVersion . PHP_EOL;