<?php

use uk\co\neontabs\bbucket\Git;
use uk\co\neontabs\bbucket\Payload;
use uk\co\neontabs\bbucket\Makefile;

include 'autoload.php';
require_once __DIR__ . '/config.php';

function pr_debug($message) {
  echo $message . "\n";
  syslog(LOG_INFO, trim($message));
}

$UID = time(TRUE);

$date = date('Y-m-d.H:i:s');
$_payload = file_get_contents('logs/pr-merge-2016-11-07.14:49:46.log');
/*
$_payload = file_get_contents('php://input');
$fname = "logs/pr-merge-$date.log";
pr_debug(': Writting data to ' . $fname);
$fh=fopen($fname, 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);
*/
$payload = new Payload($_payload);

$state = $payload->getState();
if (!$state) {
  pr_debug('No merge state found.');
  exit(1);
}
elseif (!$state == 'MERGED') {
  pr_debug('Bad merge state: ' . $state);
  exit(1);
}

// We know this is a merge so get the repo name.
$repo_full_name = $payload->getFullName();
$repo_target = _AUTOTAG_ROOT . '/' . $repo_full_name;
$repo_url = sprintf('git@%s:%s', _BIT_BUCKET, $repo_full_name);

pr_debug('Repo_url = ' . $repo_url);

$repo_git = new Git($repo_url, $repo_target, TRUE);
$repo_git->createPullClone();

// Grab last commit message
$last_log = $repo_git->lastLog();

pr_debug('Lastlog = ' . json_encode($last_log));

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
  // No bump specified.
  pr_debug('No bump specified in PR ' . $payload->getPR());
  $bump = 'patch';
}

pr_debug('Bump = ' . $bump);
pr_debug('Brands = ' . implode(", ", $brands));

// Get latest tag.
$latest_tag = $repo_git->getLatestTag();
// Increment bump level
$new_tag = Git::bump($latest_tag, $bump);
pr_debug('Tag bumped from ' . $latest_tag . ' to ' . $new_tag);
$repo_git->tag($new_tag, $last_log);
// Add the change log entry for this merge
$changelog = $repo_git->getChangeLog();
file_put_contents($repo_target . '/changelog.json', json_encode($changelog, JSON_PRETTY_PRINT));
$repo_git->add('changelog.json');
$repo_git->commit('Updated changelog to ' . $new_tag);
// Push the Repo
$repo_git->pushWithTags();

# Checkout/clone ntdr-pas
$ntdr_full_name = _NTDRPAS_REPO;
$ntdr_target = _AUTOTAG_ROOT . '/' . $ntdr_full_name;
$ntdr_url = sprintf('git@%s:%s', _GIT_HUB, $ntdr_full_name);

$ntdr_git = new Git($ntdr_url, $ntdr_target);
$ntdr_git->createPullClone();

# For each Brand
foreach ($brands as $brand) {
  $_brand = trim($brand);
  # Update the make file
  $makefile = $ntdr_target . '/files/' . $_brand . '.make';
  $brand_file = new Makefile($makefile);
  $brand_file->replace(basename($repo_full_name), $new_tag);
  $brand_file->save();
  pr_debug($makefile . ' has been updated');
  $ntdr_git->commit('Update make file for ' . $_brand . ', Tagged ' . basename($repo_full_name) . ' to ' . $new_tag);
  $ntdr_git->push();
  pr_debug('Pushed to ' . $_brand);
}

