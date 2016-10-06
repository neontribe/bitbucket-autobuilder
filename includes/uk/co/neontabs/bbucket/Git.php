<?php

namespace uk\co\neontabs\bbucket;

/**
 * Description of Git
 *
 * @author Toby Batch <tobias@neontribe.co.uk>
 */
class Git {

  protected $url;
  protected $target;

  public function __construct($url, $target) {
    $this->url = $url;
    $this->target = $target;
  }

  /**
   * Create or Pull fresh master changes.
   *
   * @param type $target
   * @return type
   */
  public function createPullClone() {
    $git_folder = $this->target . '/' . '.git';

    $parent_dir = dirname($this->target);
    if (!file_exists($parent_dir)) {
      mkdir($parent_dir, 0755, TRUE);
    }

    if (!file_exists($this->target)) {
      // clone new version
      $cmd_clone = "git clone $this->url $this->target";
      echo $cmd_clone . "\n";
      exec($cmd_clone);
    }
    elseif (file_exists($this->target) && file_exists($git_folder)) {
      $cmd_fetch = "git -C $this->target fetch";
      exec($cmd_fetch);
      $cmd_pull = "git -C $this->target pull origin master";
      exec($cmd_pull);
    }
    else {
      $msg = sprintf('Unable to clone %s to %s.', $this->url, $this->target);
      throw new \RuntimeException($msg);
    }
  }

  public function lastLog($count = 1) {
    $cmd_last_log = sprintf(
      "git -C %s log --name-status -n %d", $this->target, $count
    );
    $last_log = array();
    exec($cmd_last_log, $last_log);

    return $last_log;
  }

}
