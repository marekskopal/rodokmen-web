<?php

declare(strict_types=1);

/**
 * Writes the demo family tree (tests/Support/DemoFamily.php) as an exported data folder, for trying the website
 * and for the README screenshots: php bin/demo-data.php [folder, default var/demo]. Needs the dev dependencies.
 */

use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\DemoRecords;

require dirname(__DIR__) . '/vendor/autoload.php';

$dir = rtrim($argv[1] ?? dirname(__DIR__) . '/var/demo', '/');

$fail = static function (string $message): never {
	fwrite(STDERR, $message . PHP_EOL);
	exit(1);
};
$folder = static function (string $file) use ($dir, $fail): string {
	$path = $dir . '/' . $file;
	if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true)) {
		$fail('Cannot create ' . dirname($path));
	}

	return $path;
};

$files = ['site.json' => json_encode(DemoFamily::siteData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)];
foreach (DemoFamily::notes() as $name => $text) {
	$files['md/' . $name . '.md'] = $text . "\n";
}
foreach ($files as $file => $content) {
	if (file_put_contents($folder($file), $content) !== strlen($content)) {
		$fail('Cannot write ' . $dir . '/' . $file);
	}
}
foreach (DemoRecords::Scans as $key) {
	foreach (['img', 'full'] as $size) {
		$file = $size . '/' . $key . '.jpg';
		if (!copy(DemoRecords::ImageDir . '/' . $file, $folder($file))) {
			$fail('Cannot copy ' . $file . ' to ' . $dir);
		}
	}
}

echo 'Demo data written to ' . $dir . PHP_EOL;
