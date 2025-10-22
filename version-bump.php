<?php

// Simple semantic version bump script that updates APP_VERSION in .env
if ($argc < 4) {
    echo 'Usage: php version-bump.php <major_inc> <minor_inc> <patch_inc>' . PHP_EOL;
    exit(1);
}

$majorInc = (int)$argv[1];
$minorInc = (int)$argv[2];
$patchInc = (int)$argv[3];

echo "Args: major=$majorInc, minor=$minorInc, patch=$patchInc\n";

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "Env file not found: $envFile\n";
    exit(1);
}

$envContent = file_get_contents($envFile);
if ($envContent === false) {
    echo "Failed to read env file: $envFile\n";
    exit(1);
}

// Read current version from .env if present
if (preg_match('/^APP_VERSION=(.*)$/m', $envContent, $m)) {
    $currentVersion = trim($m[1]);
    $currentVersion = trim($currentVersion, "\"' \t");
} else {
    // Fallback to reading GameApp.vue for older projects
    $vueFile = __DIR__ . '/resources/js/components/GameApp.vue';
    $currentVersion = '0.0.0';
    if (file_exists($vueFile) && ($v = file_get_contents($vueFile)) !== false) {
        if (preg_match('/const\s+appVersion\s*=\s*(["\'])([^"\']+)\1/u', $v, $vm)) {
            $currentVersion = trim($vm[2]);
        }
    }
}

echo "Current version: $currentVersion\n";

// Parse semantic version into parts
$parts = preg_split('/\./', $currentVersion);
for ($i = 0; $i < 3; $i++) {
    if (!isset($parts[$i]) || $parts[$i] === '') {
        $parts[$i] = '0';
    }
}

$major = (int)$parts[0];
$minor = (int)$parts[1];
$patch = (int)$parts[2];

echo "Parsed: major=$major, minor=$minor, patch=$patch\n";

// Apply increments according to typical semver bump rules
if ($majorInc > 0) {
    $major += $majorInc;
    $minor = 0;
    $patch = 0;
    echo "Major bump - resetting minor and patch\n";
} elseif ($minorInc > 0) {
    $minor += $minorInc;
    $patch = 0;
    echo "Minor bump - resetting patch\n";
} else {
    $patch += $patchInc;
}

$newVersion = $major . '.' . $minor . '.' . $patch;

echo "New version: $newVersion\n";

// Update tracked config file `config/app.php` so version is present in repo
$configFile = __DIR__ . '/config/app.php';
if (!file_exists($configFile)) {
    echo "Config file not found: $configFile\n";
    exit(1);
}

$configContent = file_get_contents($configFile);
if ($configContent === false) {
    echo "Failed to read config file: $configFile\n";
    exit(1);
}

// Replace the 'version' => 'x.y.z' line (supports single or double quotes)
$updated = preg_replace(
    '/(\'version\'\s*=>\s*)([\'\"])([0-9]+\.[0-9]+\.[0-9]+)([\'\"])(\s*,)/',
    "\\1\\2" . $newVersion . "\\4\\5",
    $configContent,
    1
);

if ($updated === null) {
    echo "Failed to update config content (regex error).\n";
    exit(1);
}

// If regex didn't match (older format), try a more lenient replacement
if ($updated === $configContent) {
    $updated = preg_replace(
        '/(\'version\'\s*=>\s*)([\'\"][^\'\"]*[\'\"])(\s*,)/',
        "\\1'" . $newVersion . "'\\3",
        $configContent,
        1
    );
}

if ($updated === $configContent) {
    echo "Could not find a 'version' entry to replace in config/app.php.\n";
    exit(1);
}

file_put_contents($configFile, $updated);

echo 'Version bumped from ' . $currentVersion . ' to ' . $newVersion . ' (updated config/app.php)' . PHP_EOL;
