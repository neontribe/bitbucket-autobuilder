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
$fname = "pr-merge-$date.log";
debug_out(basename(__FILE__) . ': Writting data to ' . $fname);
$fh=fopen($fname, 'w');
fwrite($fh, print_r($payload, TRUE));
fclose($fh);

