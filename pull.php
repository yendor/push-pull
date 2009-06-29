#!/usr/bin/env php -f
<?php

define('CONFIG', $_ENV['HOME'].'/.push-pull/config');

if (!file_exists(CONFIG)) {
	echo "ERROR: There is no config file at ".CONFIG."\n";
	exit(1);
}

$config = parse_ini_file(CONFIG, true);

if ($argc <> 2) {
	echo "ERROR: You must specify the group to pull\n";
	exit(1);
}

if (isset($config['all'])) {
	unset($config['all']);
}

$rsync_result = 0;

$group = $argv[1];

if ($group == 'all') {
	foreach ($config as $section => $settings) {
		if (!isset($config[$section])) {
			echo "ERROR: The group $section does not exist\n";
			system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($section)." failed.'");
			exit(1);
		}

		if ($config[$section]['local'] == '' || $config[$section]['local'] == '/') {
			echo "ERROR: The local directory was not set which is very dangerous, exiting.";
			system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($section)." failed.'");
			exit(1);
		}

		if (!file_exists($_ENV['HOME'].'/.push-pull/exclude/'.$section) && file_exists($_ENV['HOME'].'/.push-pull/exclude/skel')) {
			if (copy($_ENV['HOME'].'/.push-pull/exclude/skel', $_ENV['HOME'].'/.push-pull/exclude/'.$section)) {
				echo "No exclude file found, copying the default skeleton.\n";
			} else {
				echo "No exclude file found and there was an error copying the skeleton, exiting.";
				system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($section)." failed.'");
				exit(1);
			}
		}

		if (isset($config[$section]['git_branch'])) {
			echo 'DEBUG: File: '.__FILE__.' at line '.__LINE__."<br />\n";
			chdir($config[$section]['local']);

			exec("git-branch --no-color 2>/dev/null", $sys_output_arr, $return_var);
			foreach ($sys_output_arr as $line) {
				if (preg_match('#^\* #', $line)) {
					list($star, $current_git_branch) = explode(' ', $line, 2);
					break;
				}
			}

			if ($return_var != 0 || $current_git_branch != $config[$section]['git_branch']) {
			  echo "ERROR: You must be on the git branch '".$config[$section]['git_branch']."' to pull\n";
			  exit(1);
			}
		}

		$exclude = '--exclude-from='.$_ENV['HOME'].'/.push-pull/exclude/'.$section;
		$local = escapeshellarg($config[$section]['local'].'/');
		$remote = escapeshellarg($config[$section]['remote'].'/');
		$command = '/usr/bin/rsync -av -e ssh --progress '.$exclude.' '.$remote.' '.$local;
		echo "$command\n";
		passthru($command, $rsync_result);
		if (is_executable('/usr/local/bin/growlnotify')) {
			if ($rsync_result == 0) {
				system("/usr/local/bin/growlnotify -t 'Pull Complete' -m 'Pull of ".escapeshellarg($section)." complete.'");
		 	} else {
				system("/usr/local/bin/growlnotify -t 'Pull EROR' -s -m 'Pull of ".escapeshellarg($section)." failed.'");
			}
		}
	}
} else {
	if (!isset($config[$group])) {
		echo "ERROR: The group $group does not exist\n";
		system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($group)." failed.'");
		exit(1);
	}

	define('LOCAL', $config[$group]['local']);
	define('REMOTE', $config[$group]['remote']);
	define('EXCLUDE', $_ENV['HOME'].'/.push-pull/exclude/'.$group);
	define('EXCLUDE_SKEL', $_ENV['HOME'].'/.push-pull/exclude/skel');
	if (isset($config[$group]['git_branch'])) {
		define('REQUIRED_GIT_BRANCH', $config[$group]['git_branch']);
	}

	if (LOCAL == '' || LOCAL == '/') {
		echo "ERROR: The local directory was not set which is very dangerous, exiting.";
		system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($group)." failed.'");
		exit(1);
	}

	if (!file_exists(EXCLUDE) && file_exists(EXCLUDE_SKEL)) {
		if (copy(EXCLUDE_SKEL, EXCLUDE)) {
			echo "No exclude file found, copying the default skeleton.\n";
		} else {
			echo "No exclude file found and there was an error copying the skeleton, exiting.";
			system("/usr/local/bin/growlnotify -s -t 'Pull ERROR' -m 'Pull of ".($group)." failed.'");
			exit(1);
		}
	}

	if (defined('REQUIRED_GIT_BRANCH')) {
		chdir(LOCAL);
		exec("git-branch --no-color 2>/dev/null", $sys_output_arr, $return_var);
		foreach ($sys_output_arr as $line) {
			if (preg_match('#^\* #', $line)) {
				list($star, $current_git_branch) = explode(' ', $line, 2);
				break;
			}
		}

		if ($return_var != 0 || $current_git_branch != REQUIRED_GIT_BRANCH) {
		  echo "ERROR: You must be on the git branch '".REQUIRED_GIT_BRANCH."' to pull\n";
		  exit(1);
		}
	}

	$exclude = '--exclude-from='.EXCLUDE;
	$local = escapeshellarg(LOCAL.'/');
	$remote = escapeshellarg(REMOTE.'/');
	$command = '/usr/bin/rsync -av --delete -e ssh --progress '.$exclude.' '.$remote.' '.$local;
	echo "$command\n";

	passthru($command, $rsync_result);
}

if (is_executable('/usr/local/bin/growlnotify')) {
	if ($rsync_result == 0) {
		system("/usr/local/bin/growlnotify -t 'Pull Complete' -m 'Pull of ".escapeshellarg($group)." complete.'");
 	} else {
		system("/usr/local/bin/growlnotify -t 'Pull EROR' -s -m 'Pull of ".escapeshellarg($group)." failed.'");
	}
}

