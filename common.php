<?php
define('CONFIG', $_ENV['HOME'].'/.push-pull/config');
define('EXCLUDE_SKEL', $_ENV['HOME'].'/.push-pull/exclude/skel');

if (!file_exists(CONFIG)) {
	echo "ERROR: There is no config file at ".CONFIG."\n";
	exit(1);
}

/**
 * Print a message using the OS X notification platform (Growl) if it is available.
 *
 * @param string $title The title of the message.
 * @param string $content The message content.
 * @param boolean $sticky Set to true to set the message as "sticky" and not disappear automatically.
 */
function GrowlMessage($title, $content, $sticky=false)
{
	if (!is_executable('/usr/local/bin/growlnotify')) {
		return;
	}

	$stickyarg = '';
	if ($sticky) {
		$stickyarg = "-s";
	}

	system("/usr/local/bin/growlnotify $stickyarg -t ".escapeshellarg($title)." -m ".escapeshellarg($content));
}