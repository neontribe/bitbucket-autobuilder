<?php

namespace uk\co\neontabs\bbucket;

class Makefile {

  protected $lines;

  public function __construct($makefile) {
    $this->lines = file($makefile);
    if (!count($this->lines)) {
      throw new \RuntimeException('Makefile empty: ' . $makefile);
    }
  }

  public function replace($needle, $new_version) {
    $_needle = sprintf("projects[%s][download][tag]\n", $needle);
    foreach ($this->lines as $line_number => $line) {
      if (strpos($line, $_needle) === 0) {
        $this->lines[$line_number] = $_needle . ' = ' . $new_version;
      }
    }
  }

  public function dump($as_string = false) {
    $data = implode("", $this->lines);
    if ($as_string) {
      return $data;
    }
    else {
      echo $data . "\n";
    }
  }

}
