#!/usr/bin/php -f
<?php
require_once dirname(__FILE__).'/common.php';

$config = parse_ini_file(CONFIG, true);
$sites = array();
$local = '';
foreach ($config as $section => $settings) {
	if (preg_match('#^'.preg_quote($settings['local'],'#').'/#', $_ENV['PWD'].'/')) {
		if($local && $local != $settings['local']) {
			echo "ERROR: You cannot have two overlapping local site paths configured (".$local." and ".$settings['local']."\n";
			exit(1);
		}

		$local = $settings['local'];
		// If an exclude file isn't explicitly set, then use the default location for this site
		if (!isset($settings['exclude'])) {
			$settings['exclude'] = $_ENV['HOME'].'/.push-pull/exclude/'.$section;
		}

		if (!isset($settings['git_branch'])) {
			$settings['git_branch'] = '';
		}

		if ($settings['git_branch']) {
			exec("git-branch --no-color 2>/dev/null", $sys_output_arr, $return_var);
			foreach ($sys_output_arr as $line) {
				if (preg_match('#^\* #', $line)) {
					list($star, $current_git_branch) = explode(' ', $line, 2);
					break;
				}
			}

			if ($return_var != 0 || $current_git_branch != $settings['git_branch']) {
				echo "ERROR: You must be on the git branch '".$settings['git_branch']."' to push to '".$section."'\n";
				exit(1);
			}
		}

		$sites[$section] = $settings;
	}
}

// No sites matched with the current path
if(empty($sites)) {
	echo "ERROR: You are not in a push configured directory\n";
	exit(1);
}

if ($argc < 2) {
	echo "ERROR: You must specify the files/directories to push\n";
	exit(1);
}

$output = 0;
$result = 0;
for ($i=1; $i < $argc; $i++) {
	$extension = '';
	if (is_file($argv[$i])) {
		$extension = substr(strrchr($argv[$i], '.'), 1);
	}

	// Do a parse check on php files
	if ($extension == 'php') {
		exec('/usr/bin/php -l '.escapeshellarg($argv[$i]), $output, $result);
		if ($result > 0) {
			echo implode("\n", $output)."\n";
			GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed', true);
			exit(1);
		}
	}
	if (!is_file($argv[$i]) && !is_dir($argv[$i])) {
		echo "ERROR: ".$argv[$i]." does not exist\n";
		GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed', true);
		exit(1);
	}
}

$files = array_slice($argv, 1);
rsort($files);
$subdir= '';
$to_push = array();

foreach ($files as $file) {
	if (is_dir($file)) {
		$file = rtrim($file, '/');
	}

	$relative_path = str_replace($local.'/', '', realpath($file));
	$file_dir = dirname($relative_path);
	$file_name = basename($relative_path);

	// Push . from the site root
	if($relative_path == $local) {
		$file_name = '.';
		$file_dir = '';
	}
	// Push an entire directory from a relative path
	else if($file == '.') {
		$file_name = '.';
		$file_dir = $relative_path;
	}
	$to_push[$file_dir][] =  escapeshellarg($file_name);
}

foreach($sites as $section => $site) {
	if (!file_exists($site['exclude']) && file_exists(EXCLUDE_SKEL)) {
		if (copy(EXCLUDE_SKEL, $site['exclude'])) {
			echo "No exclude file found, copying the default skeleton.\n";
		} else {
			echo "No exclude file found and there was an error copying the skeleton, exiting.";
			GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed', true);
			exit(1);
		}
	}

	$exclude = '--exclude-from='.$site['exclude'];

	foreach ($to_push as $filedir => $files) {
		$local = implode(' ', $files);

		if (empty($filedir)) {
			$remote = escapeshellarg($site['remote'].'/');
		} else {
			$remote = escapeshellarg($site['remote'].'/'.$filedir.'/');
		}

		$command = '/usr/bin/rsync -av -e ssh --progress '.$exclude.' '.$local.' '.$remote;
		echo "$command\n";

		$rsync_result = 0;
		passthru($command, $rsync_result);
		if ($rsync_result == 0 ) {
			GrowlMessage('Push Complete', 'Push of '.($argc-1).' files in '.$section.' completed successfully.');
		} else {
			GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed.', true);
			exit(1);
		}
		echo "\n";
	}
}


// If we pushed up to more than one site, say that we're now completely finished
if(count($sites) > 1) {
	GrowlMessage('Pull Session Complete', 'Push of '.($argc-1).' files to '.count($sites).' completed successfully.');
}