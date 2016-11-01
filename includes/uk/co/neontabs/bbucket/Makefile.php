<?php

namespace uk\co\neontabs\bbucket;

class Makefile {

  protected $makefile;
  protected $lines;

  public function __construct($makefile) {
    $this->makefile = $makefile;
    $this->lines = file($makefile);
    if (!count($this->lines)) {
      throw new \RuntimeException('Makefile empty: ' . $makefile);
    }
  }

  function getMakefile() {
    return $this->makefile;
  }

  function getLines() {
    return $this->lines;
  }

  public function replace($needle, $new_version, $tag = TRUE) {
    $_needle = sprintf("projects[%s][download][tag]", $needle);
    $type = $tag ? '[tag]' : '[branch]';
    foreach ($this->lines as $line_number => $line) {
      if (strpos($line, $_needle) === 0) {
        if (!$tag) {
          $_needle = str_replace('[tag]', '[branch]', $_needle);
        }
        $this->lines[$line_number] = $_needle . ' = ' . $new_version . "\n";
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

  public function save($file = FALSE) {
    if ($file) {
      $_file = $file;
    }
    else {
      $_file = $this->makefile;
    }

    $fh = fopen($_file, 'w');
    fwrite($fh, $this->dump(TRUE));
    fclose($fh);
  }

}
