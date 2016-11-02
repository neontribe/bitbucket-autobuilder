<?php

use uk\co\neontabs\bbucket\Git;
use uk\co\neontabs\bbucket\Payload;
use uk\co\neontabs\bbucket\Makefile;

include 'autoload.php';
require_once __DIR__ . '/config.php';

function cloneFromGithub($full_name) {
  $full_name = _NTDRPAS_REPO;
  $target = _AUTOTAG_ROOT . '/' . $full_name;
  $url = sprintf('git@%s:%s', _GIT_HUB, $full_name);
  $git = new Git($url, $target);
  $git->createPullClone();

  return $git;
}

$UID = time(TRUE);

$date = date('Y-m-d.H:i:s');
$payload = file_get_contents('php://input');
// $payload = file_get_contents('pr-create.log');
$fname = __DIR__ . "/logs/pr-create-$date.log";
$fh=fopen($fname, 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);

$data = json_decode($payload, TRUE);
$pull_request = $data['pullrequest'];
$description = $pull_request['description'];

if (strpos($description, 'BUILD') !== FALSE) {
  printf("Build directive found\n");

  # Checkout/clone ntdr-pas
  $ntdr_git = cloneFromGithub(_NTDRPAS_REPO);

  // Get the branch name
  $branch_name = $pull_request['source']['branch']['name'];
  // Get the ticket number, I could use a regex but that's not as easy as it seems.
  $elements = explode('-', $branch_name);
  if (count($elements) < 3) {
    echo "No valid ticket number fount in " . $branch_name . "\n";
    exit();
  }

  // Shift the first two instances off the bottom of the array.
  $brandcode = array_shift($elements);
  $ticket = $brandcode . '-' . array_shift($elements);

  // This next block check if it's a NEON ticket, we need to translate this to zz in make file cases.
  if ($brandcode == 'NEON') {
    $_brandcode = 'zz';
  }
  else {
    $_brandcode = strtolower($brandcode);
  }

  $makefile = new Makefile($ntdr_git->getTarget() . '/files/' . $_brandcode . '.make');
  printf("Sourcing make file from %s\n", $makefile->getMakefile());

  printf("Processing ticket id %s\n", $ticket);
  // Scan bit buckets for other tickets that match this issue.
  foreach ($repos as $url) {
    printf("\tChecking repo %s\n", $url);
    $repo_full_name = substr($url, strpos($url, ':') + 1);
    $target = _AUTOTAG_ROOT . $repo_full_name;
    printf("\tFetching/cloning repo into %s\n", $target);
    $git = new Git($url, $target);
    $git->createPullClone();
    $branches = $git->branches();
    foreach ($branches as $branch) {
      printf("\t\tChecking branch %s\n", $branch);
      $tid_start_index = strpos($branch, $ticket);
      if ($tid_start_index !== FALSE) {
        // This repo needs a build
        printf("\t\t\tMatch found\n");
        $branch_name = substr($branch, $tid_start_index);
        $makefile->replace(basename($repo_full_name), $branch_name, FALSE);
      }
    }
  }

  // Build out this Ticket
  $no_core = '';
  $build_taget = sprintf('%s/%s/%s', _WEB_ROOT, $_brandcode, $ticket);
  if (file_exists($build_taget)) {
    $no_core = '--no-core ';
  }
  $_makefile = sprintf('%s/%s/%s.make', _MAKEFILE_ROOT, $_brandcode, $ticket);
  if (!is_dir(dirname($_makefile))) {
      mkdir(dirname($_makefile), 0775, TRUE);
  }
  file_put_contents($_makefile, $makefile->dump(TRUE));

  // Now we have a brandcode, a make file and a ticket number, ($brandcode, $_makefile, $ticket).
  // So we now call the jenkins with those parameters.
  $data = array(
      'json' => '{"parameter": [{"name":"brandcode", "value":"' . $_brandcode . '"}, {"name":"ticket", "value":"' . $ticket . '"}, {"name":"makefile", "value":"' . $_makefile . '"}]}'
  );
  $curl = curl_init('https://jenkins.neontribe.org/job/_build_pr/build');
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERPWD, "jenkins:" . _JENKINS_PASS);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  $output = curl_exec($curl);
  $info = curl_getinfo($curl);
  curl_close($curl);
  echo "\n\n" . $output . "\n\n";
}
else {
    printf("Build directive not found\n");
}
