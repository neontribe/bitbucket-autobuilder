<?php

use uk\co\neontabs\bbucket\Git;
use uk\co\neontabs\bbucket\Payload;
use uk\co\neontabs\bbucket\Makefile;

include 'autoload.php';
/*

$fh=fopen("post-$date.log", w);
fwrite($fh, print_r($_POST, TRUE));
fclose($fh);

$fh=fopen("get-$date.log", w);
fwrite($fh, print_r($_GET, TRUE));
fclose($fh);

$fh=fopen("server-$date.log", w);
fwrite($fh, print_r($_SERVER, TRUE));
fclose($fh);
 */

$date = date('Y-m-d.H:i:s');
$payload = file_get_contents('php://input');
$fh=fopen("payload-$date.log", 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);

define('_AUTOTAG_ROOT', '/var/tmp/autotag/');
define('_BIT_BUCKET', 'bitbucket.org');
define('_GIT_HUB', 'github.com');
define('_NTDRPAS_REPO', 'neontribe/ntdr-pas');

if (!file_exists(_AUTOTAG_ROOT)) {
    mkdir(_AUTOTAG_ROOT, 0755, TRUE);
}

# $payload = json_decode(file_get_contents('php://input'));
$payload = new Payload(file_get_contents('payload.json'));

// We know this is a merge so get the repo name.
$repo_full_name = $payload->getFullName();
$repo_target = _AUTOTAG_ROOT . '/' . $repo_full_name;
$repo_url = sprintf('git@%s:%s', _BIT_BUCKET, $repo_full_name);

$repo_git = new Git($repo_url, $repo_target);
$repo_git->createPullClone();

// Grab last commit message
$last_log = $repo_git->lastLog(2);

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

// Tag the merged repo.
if (!$bump) {
  // No bump specified.  Exit
  syslog(LOG_WARNING, basename(__FILE__) . ': No bump specified in PR ' . $payload->getPR());
  exit(1);
}

syslog(LOG_INFO, basename(__FILE__) . ': Brands = ' . implode(', ', $brands));
syslog(LOG_INFO, basename(__FILE__) . ': Bump = ' . $bump);

$cmd_ntmc = sprintf('drush ntmc %s %s --allow-empty', $repo_target, $bump);
exec($cmd_ntmc);

$cmd_get_tag = sprintf('git -C %s describe --abbrev=0 --tags', $repo_target);
syslog(LOG_INFO, basename(__FILE__) . ': Get Tag = ' . $cmd_get_tag);
$new_tag = exec($cmd_get_tag);
syslog(LOG_INFO, basename(__FILE__) . ': New Tag = ' . $new_tag);

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
  syslog(LOG_INFO, basename(__FILE__) . ': Pushed to ' . $brand);
  $brand_file = new Makefile($makefile);
  $brand_file->replace(basename($repo_full_name), $new_tag);
  $brand_file->save();
  $ntdr_git->commit('Update make file for ' . $brand);
  $ntdr_git->push();
  syslog(LOG_INFO, basename(__FILE__) . ': Pushed to ' . $brand);
}
