<?php

use uk\co\neontabs\bbucket\Git;
use uk\co\neontabs\bbucket\Payload;
use uk\co\neontabs\bbucket\Makefile;

include 'autoload.php';
require_once __DIR__ . '/neontabs/nt_util/nt_util.opts.inc';

function pr_debug($message) {
  echo $message;
  syslog(LOG_INFO, trim($message));
}

$UID = time(TRUE);

$date = date('Y-m-d.H:i:s');
$payload = file_get_contents('logs/pr-merge-2016-11-07.14:49:46.log');
/*
$payload = file_get_contents('php://input');
$fname = "logs/pr-merge-$date.log";
pr_debug(basename(__FILE__) . ': Writting data to ' . $fname);
$fh=fopen($fname, 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);
*/

$data = json_decode($payload, TRUE);
$pull_request = $data['pullrequest'];
$description = $pull_request['description'];

if (strpos($description, 'BUILD:') !== FALSE) {
}
