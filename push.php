#!/usr/bin/env php -f
<?php

define('CONFIG', $_ENV['HOME'].'/.push-pull/config');

if (!file_exists(CONFIG)) {
  echo "ERROR: There is no config file at ".CONFIG."\n";
  exit(1);
}

$config = parse_ini_file(CONFIG, true);

foreach ($config as $section => $settings) {
  if (preg_match('#^'.preg_quote($settings['local'],'#').'#', $_ENV['PWD'])) {
    define('LOCAL', $settings['local']);
    define('REMOTE', $settings['remote']);
    define('EXCLUDE', $_ENV['HOME'].'/.push-pull/exclude/'.$section);
    define('EXCLUDE_SKEL', $_ENV['HOME'].'/.push-pull/exclude/skel');

	if (isset($settings['git_branch'])) {
		define('REQUIRED_GIT_BRANCH', $settings['git_branch']);
	}
    break;
  }
}

if (!defined('LOCAL')) {
  echo "ERROR: You are not in a push configured directory\n";
  exit(1);
}

if ($argc < 2) {
  echo "ERROR: You must specify the files/directories to push\n";
  exit(1);
}

if (defined('REQUIRED_GIT_BRANCH')) {
	exec("git-branch --no-color 2>/dev/null", $sys_output_arr, $return_var);
	foreach ($sys_output_arr as $line) {
		if (preg_match('#^\* #', $line)) {
			list($star, $current_git_branch) = explode(' ', $line, 2);
			break;
		}
	}

	if ($return_var != 0 || $current_git_branch != REQUIRED_GIT_BRANCH) {
	  echo "ERROR: You must be on the git branch '".REQUIRED_GIT_BRANCH."' to push\n";
	  exit(1);
	}
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

if (!file_exists(EXCLUDE) && file_exists(EXCLUDE_SKEL)) {
  if (copy(EXCLUDE_SKEL, EXCLUDE)) {
    echo "No exclude file found, copying the default skeleton.\n";
  } else {
    echo "No exclude file found and there was an error copying the skeleton, exiting.";
        GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed', true);
    exit(1);
  }
}
$exclude = '--exclude-from='.EXCLUDE;

$files = array_slice($argv, 1);
rsort($files);
$subdir= '';
$to_push = array();
foreach ($files as $file) {
		if (is_dir($file)) {
			$file = rtrim($file, '/');
		}
		if ($file != '.') {
            $filedir = dirname(realpath($file));
        } else {
            $filedir = '.';
        }

        $filedir = str_replace(LOCAL.'/', '', $filedir);
        $filedir = str_replace(LOCAL, '', $filedir);

        $to_push[$filedir][] = escapeshellarg($file);
}

foreach ($to_push as $filedir => $files) {
        $local = implode(' ', $files);

        if (empty($filedir)) {
                $remote = escapeshellarg(REMOTE.'/');
        } else {
                $remote = escapeshellarg(REMOTE.'/'.$filedir.'/');
        }

        $command = '/usr/bin/rsync -av -e ssh --progress '.$exclude.' '.$local.' '.$remote;
        echo "$command\n";

        $rsync_result = 0;
        passthru($command, $rsync_result);
        if ($rsync_result == 0 ) {
                GrowlMessage('Push Complete', 'Push of '.($argc-1).' files in '.$section.' complete');
        } else {
                GrowlMessage('Push ERROR', 'Push of '.($argc-1).' files in '.$section.' failed', true);
                exit(1);
        }
        echo "\n";
}


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
