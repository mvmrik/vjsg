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
if (!file_exists($file)) {
    echo "File not found: $file\n";
    exit(1);
}

$content = file_get_contents($file);
if ($content === false) {
    echo "Failed to read file: $file\n";
    exit(1);
}

// Match patterns like: const appVersion = '0.1.2' ; or const appVersion = "0.1.2";
// Be tolerant to spaces and optional semicolon. Capture the quote char to reuse in replacement.
if (!preg_match('/(const\s+appVersion\s*=\s*)([\"\'])([^\"\']+)(\2)\s*;?/u', $content, $matches)) {
    echo 'Version not found in GameApp.vue.' . PHP_EOL;
    exit(1);
}

$currentVersion = $matches[3];
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

$quote = $matches[2];
$prefix = $matches[1];

// Build replacement keeping original quote char
$replacement = $prefix . $quote . $newVersion . $quote;

// Replace the first occurrence only
$content = preg_replace('/(const\s+appVersion\s*=\s*)([\"\\\'])([^\"\'\n\r]*)(\2)\s*;?/u', $replacement, $content, 1);

file_put_contents($file, $content);

echo 'Version bumped from ' . $currentVersion . ' to ' . $newVersion . PHP_EOL;