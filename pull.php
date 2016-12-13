#!/usr/bin/env php -f
<?php
require_once dirname(__FILE__).'/common.php';

$config = parse_ini_file(CONFIG, true);

if ($argc < 2) {
	echo "ERROR: You must specify the group to pull\n";
	exit(1);
}

if (isset($config['all'])) {
	unset($config['all']);
}

$rsync_result = 0;

$groups = $argv;
array_shift($groups);

// If 'all' was passed, this means pull all sites down
if(in_array('all', $groups)) {
	$groups = array_keys($config);
}

// Do a few sanity checks before we start the pull
foreach($groups as $group) {
	if(!isset($config[$group])) {
		echo "ERROR: The group $group does not exist\n";
		GrowlMessage('Pull ERROR', 'Pull of '.$group.' failed. The specified site does not exist.');
		exit(1);
	}

	$settings = $config[$group];
	if ($settings['local'] == '' || $settings['local'] == '/') {
		echo "ERROR: The local directory was not set for $group which is very dangerous, exiting.";
		GrowlMessage('Pull ERROR', 'Pull of '.$group.' failed. No local directory was set.');
		exit(1);
	}
}

// Bring the sites down locally using rsync
foreach($groups as $section) {
	$settings = $config[$group];

	// If an exclude file isn't explicitly set, then use the default location for this site
	if (!isset($settings['exclude'])) {
		$settings['exclude'] = HOME.'/.push-pull/exclude/'.$section;
	}

	if (!file_exists($settings['exclude']) && file_exists(HOME.'/.push-pull/exclude/skel')) {
		if (copy(HOME.'/.push-pull/exclude/skel', $settings['exclude'])) {
			echo "No exclude file found, copying the default skeleton.\n";
		} else {
			echo "No exclude file found and there was an error copying the skeleton, exiting.";
			GrowlMessage('Pull ERROR', 'Pull of '.$section.' failed.');
			exit(1);
		}
	}

	if (isset($settings['git_branch'])) {
		chdir($settings['local']);

		exec("git-branch --no-color 2>/dev/null", $sys_output_arr, $return_var);
		foreach ($sys_output_arr as $line) {
			if (preg_match('#^\* #', $line)) {
				list($star, $current_git_branch) = explode(' ', $line, 2);
				break;
			}
		}

		if ($return_var != 0 || $current_git_branch != $settings['git_branch']) {
			echo "ERROR: You must be on the git branch '".$settings['git_branch']."' to pull\n";
			exit(1);
		}
	}

	$exclude = '--exclude-from='.$settings['exclude'];
	$local = escapeshellarg($settings['local'].'/');
	$remote = escapeshellarg($settings['remote'].'/');
	$command = '/usr/bin/rsync -av -e ssh --progress '.$exclude.' '.$remote.' '.$local;
	echo "$command\n";
	passthru($command, $rsync_result);
	if ($rsync_result == 0) {
		GrowlMessage('Pull Complete', 'Pull of '.$section.' completed successfully.');
	}
	else {
		GrowlMessage('Pull FAILED', 'Pull of '.$section.' failed.');
		exit(1);
	}
}

// If we pulled down more than one site, say that we're now completely finished
if(count($groups) > 1) {
	GrowlMessage('Pull Session Complete', 'Pulled of '.count($groups).' sites completed successfully.');
}
