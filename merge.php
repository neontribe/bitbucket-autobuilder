<?php

use uk\co\neontabs\bbucket\Git;
use uk\co\neontabs\bbucket\Payload;
use uk\co\neontabs\bbucket\Makefile;

include 'autoload.php';
require_once __DIR__ . '/neontabs/nt_util/nt_util.opts.inc';

$UID = time(TRUE);

function debug_out($msg, $lvl = LOG_INFO) {
  global $UID;

  syslog($lvl, $UID . ': ' . $msg);
  $strlvl = '';
  switch ($lvl) {
    case LOG_EMERG:
      $strlvl = 'EMERG';
      break;
    case LOG_ALERT:
      $strlvl = 'ALERT';
      break;
    case LOG_CRIT:
      $strlvl = 'CRIT';
      break;
    case LOG_ERR:
      $strlvl = 'ERR';
      break;
    case LOG_WARNING:
      $strlvl = 'WARNING';
      break;
    case LOG_NOTICE:
      $strlvl =  'NOTICE';
      break;
    case LOG_INFO:
    default:
      $strlvl = 'INFO';
      break;
    case LOG_DEBUG:
      $strlvl = 'DEBUG';
      break;
  }
  echo $strlvl .  ":\t" . $msg . "\n";
}


$date = date('Y-m-d.H:i:s');
$payload = file_get_contents('php://input');
#$fname = "payload-$date.log";
#debug_out(basename(__FILE__) . ': Writting data to ' . $fname);
#$fh=fopen($fname, 'w');
#fwrite($fh, print_r($payload, TRUE));
#fclose($fh);

define('_AUTOTAG_ROOT', '/var/tmp/autotag/');
define('_BIT_BUCKET', 'bitbucket.org');
define('_GIT_HUB', 'github.com');
define('_NTDRPAS_REPO', 'neontribe/ntdr-pas');

if (!file_exists(_AUTOTAG_ROOT)) {
    mkdir(_AUTOTAG_ROOT, 0755, TRUE);
}

$payload = new Payload(file_get_contents('php://input'));
# $payload = new Payload(file_get_contents('payload.json'));

$state = $payload->getState();
if (!$state) {
  debug_out(basename(__FILE__) . ': No merge state found.', LOG_ERR);
  exit(1);
}
elseif (!$state == 'MERGED') {
  debug_out(basename(__FILE__) . ': Bad merge state: ' . $state, LOG_ERR);
  exit(1);
}

// We know this is a merge so get the repo name.
$repo_full_name = $payload->getFullName();
$repo_target = _AUTOTAG_ROOT . '/' . $repo_full_name;
$repo_url = sprintf('git@%s:%s', _BIT_BUCKET, $repo_full_name);

debug_out(basename(__FILE__) . ': repo_url = ' . $repo_url, LOG_INFO);

$repo_git = new Git($repo_url, $repo_target);
$repo_git->createPullClone();

// Grab last commit message
$last_log = $repo_git->lastLog(2);

debug_out(basename(__FILE__) . ': lastlog = ' . implode(", ", $last_log), LOG_INFO);

# Hunt the log for the first instances of BUM
$bump = FALSE;
$brands = array();
foreach ($last_log as $line) {
    $_line = trim($line);
    if (strpos($_line, 'BUMP') === 0) {
        $bump = trim(substr($_line, strpos($_line, ':') + 1));
    }
    elseif (strpos($_line, 'BRAND') === 0) {
        $_brands = trim(substr($_line, strpos($_line, ':') + 1));
        $brands = explode(',', $_brands);
    }
}

debug_out(basename(__FILE__) . ': bump = ' . $bump, LOG_INFO);
debug_out(basename(__FILE__) . ': brands = ' . implode(", ", $brands), LOG_INFO);

/*
if (!in_array('zz', $brands)) {
  debug_out(basename(__FILE__) . ': INVALID BRANDS = ' . implode(', ', $brands), LOG_ERR);
  exit(1);
}
*/

// Tag the merged repo.
if (!$bump) {
  // No bump specified.  Exit
  debug_out(basename(__FILE__) . ': No bump specified in PR ' . $payload->getPR(), LOG_WARNING);
  exit(1);
}

try {
    nt_util_module_changelog($repo_target, $bump, TRUE);
}
catch (RuntimeException $exc) {
  $msg = $exc->getMessage();
  debug_out(basename(__FILE__) . ': ' . $msg, LOG_ERR);
  exit(1);
}

$cmd_get_tag = sprintf('git -C %s describe --abbrev=0 --tags', $repo_target);
debug_out(basename(__FILE__) . ': Get Tag = ' . $cmd_get_tag, LOG_INFO);
$new_tag = exec($cmd_get_tag);
debug_out(basename(__FILE__) . ': New Tag = ' . $new_tag, LOG_INFO);

# Checkout/clone ntdr-pas
$ntdr_full_name = _NTDRPAS_REPO;
$ntdr_target = _AUTOTAG_ROOT . '/' . $ntdr_full_name;
$ntdr_url = sprintf('git@%s:%s', _GIT_HUB, $ntdr_full_name);

$ntdr_git = new Git($ntdr_url, $ntdr_target);
$ntdr_git->createPullClone();

# For each Brand
foreach ($brands as $brand) {
  # Update the make file
  $makefile = $ntdr_target . '/files/' . $brand . '.make';
  debug_out(basename(__FILE__) . ': Pushed to ' . $brand, LOG_INFO);
  $brand_file = new Makefile($makefile);
  $brand_file->replace(basename($repo_full_name), $new_tag);
  $brand_file->save();
  debug_out(basename(__FILE__) . ': ' . $makefile . ' has been updated', LOG_INFO);
  $ntdr_git->commit('Update make file for ' . $brand);
  $ntdr_git->push();
  debug_out(basename(__FILE__) . ': Pushed to ' . $brand, LOG_INFO);
}
