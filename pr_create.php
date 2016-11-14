<?php

use uk\co\neontabs\bbucket\Payload;

include 'autoload.php';
require_once __DIR__ . '/config.php';
$url = 'https://jenkins.neontribe.org/job/_build_pr/build';

function pr_debug($message) {
    echo $message;
    syslog(LOG_INFO, trim($message));
}

$UID = time(TRUE);

$date = date('Y-m-d.H:i:s');
$payload = file_get_contents('php://input');
$fname = __DIR__ . "/logs/pr-create-$date.log";
$fh=fopen($fname, 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);
// $payload = file_get_contents('pr-create.log');

$data = json_decode($payload, TRUE);
$pull_request = $data['pullrequest'];
$description = $pull_request['description'];

if (strpos($description, 'BUILD:') !== FALSE) {
  $brands = array();

  // Get Brands specified.
  $lines = explode("\n", $description);
  foreach ($lines as $line) {
    if (strpos($line, 'BUILD:') === 0) {
      $_line = substr($line, strpos($line, ':') + 1);
      pr_debug(sprintf("_Line: %s\n", $_line));
      $brands = explode(',', $_line);
    }
  }

  foreach ($brands as $index => $brand) {
    $brands[$index] = trim($brand);
  }

  pr_debug(sprintf("Build directive found for %s\n", json_encode($brands)));

  // Get the branch name
  $branch_name = $pull_request['source']['branch']['name'];

  // Get the ticket number, I could use a regex but that's not as easy as it seems.
  $elements = explode('-', $branch_name);
  if (count($elements) < 3) {
    pr_debug(sprintf("No valid ticket number fount in %s\n", $branch_name));
    exit();
  }

  // Shift the first two instances off the bottom of the array.
  $_brandcode = array_shift($elements);
  $ticket = $_brandcode . '-' . array_shift($elements);

  foreach ($brands as $brand) {
    // Now we have a brandcode, and a ticket number, ($brandcode, $ticket).
    // So we now call the jenkins with those parameters.
    $data = array(
      'json' => '{"parameter": [{"name":"brandcode", "value":"' . $brand . '"}, {"name":"ticket", "value":"' . $ticket . '"}]}'
    );
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, "jenkins:" . _JENKINS_PASS);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    pr_debug(sprintf("Build %s with %s\n", $url, json_encode($data)));
    $output = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    pr_debug(sprintf("\n\n%s\n\n", $output));
  }
  pr_debug("All done\n");
}
else {
    pr_debug(sprintf("Build directive not found\n"));
}
