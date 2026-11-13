<?php

declare(strict_types=1);

// Prints a password hash for SITE_PASSWORD_HASH. Usage: php bin/hash-password.php
fwrite(STDOUT, 'Password: ');
system('stty -echo 2>/dev/null');
$password = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
fwrite(STDOUT, PHP_EOL);

if ($password === '') {
	fwrite(STDERR, "Empty password.\n");
	exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
