<?php

declare(strict_types=1);

/**
 * Writes the demo family tree (tests/Support/DemoFamily.php) as an exported data folder, for trying the website
 * and for the README screenshots: php bin/demo-data.php [folder, default var/demo]. Needs the dev dependencies.
 */

use Rodokmen\Site\Tests\Support\DemoFamily;

require dirname(__DIR__) . '/vendor/autoload.php';

$dir = rtrim($argv[1] ?? dirname(__DIR__) . '/var/demo', '/');
if (!is_dir($dir . '/md') && !mkdir($dir . '/md', 0775, true)) {
	fwrite(STDERR, 'Cannot create ' . $dir . PHP_EOL);
	exit(1);
}

$files = ['site.json' => json_encode(DemoFamily::siteData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)];
foreach (DemoFamily::notes() as $name => $text) {
	$files['md/' . $name . '.md'] = $text . "\n";
}
foreach ($files as $file => $content) {
	if (file_put_contents($dir . '/' . $file, $content) !== strlen($content)) {
		fwrite(STDERR, 'Cannot write ' . $dir . '/' . $file . PHP_EOL);
		exit(1);
	}
}

echo 'Demo data written to ' . $dir . PHP_EOL;
