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
  protected $pretend;

  public function __construct($url, $target, $pretend = FALSE) {
    $this->url = $url;
    $this->target = $target;
    $this->pretend = $pretend;
  }

  public function getUrl() {
    return $this->url;
  }

  public function getTarget() {
    return $this->target;
  }

  public static function cloneFromGithub($full_name) {
    $full_name = _NTDRPAS_REPO;
    $target = _AUTOTAG_ROOT . '/' . $full_name;
    $url = sprintf('git@%s:%s', _GIT_HUB, $full_name);
    $git = new Git($url, $target);
    $git->createPullClone();

    return $git;
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

  public function branches() {
    $cmd = sprintf(
      "git -C %s branch -a", $this->target
    );
    $output = array();
    exec($cmd, $output);

    return $output;
  }

  public function cleanLastLog($lastlog) {
    $header = array();
    $lines = array();
    foreach ($lastlog as $line) {
      if (strpos($line, 'commit') === 0 || strpos($line, 'BUMP') !== FALSE || strpos($line, 'BRAND') !== FALSE || strlen(trim($line)) == 0) {
        // Discard.
      }
      elseif (strpos($line, 'Merge') === 0) {
        $header['merge'] = trim(substr($line, strpos($line, ':') + 1));
      }
      elseif (strpos($line, 'Author') === 0) {
        $header['author'] = trim(substr($line, strpos($line, ':') + 1));
      }
      elseif (strpos($line, 'Date') === 0) {
          $_date = strtotime(trim(substr($line, strpos($line, ':') + 1)));
        $header['date'] = date('Y/m/d H:s:i', $_date);
      }
      else {
        $lines[] = trim($line);
      }
    }
    asort($header);
    $message = array('message' => $lines);
    $_lastlog = array_merge($header, $message);

    return $_lastlog;
  }

  public function lastLog() {
    $cmd = sprintf("git -C %s show", $this->target);
    $output = array();
    exec($cmd, $output);

    return $output;
  }

  public function getLatestTag() {
    $cmd = sprintf("'git -C %s describe --abbrev=0 --tags'", $this->target);
    $output = array();
    $retval = 0;
    exec($cmd, $output, $retval);

    if ($retval != 0) {
      // There's propably no tags yet.
      if (strpos(implode($output), 'not found') !== FALSE) {
        // Not sure what is wrong....
        $msg = sprintf("Can't determine current tag? [%s]", implode($output));
        throw new \RuntimeException($msg);
      }
      $output = 'v0_0_0';
    }

    return $output;
  }

  /**
   *
   * @param string|array $tag
   * @param string $bump
   * @param $string $sep
   * @param $string $prefix
   */
  public static function bump($tag = '0', $bump = 'patch', $sep = '_', $prefix = 'v') {
    if (!is_array($tag)) {
      $_tag = self::explode($tag, $sep);
    }
    else {
      $_tag = $tag;
    }

    switch ($bump) {
      case 'major':
        $_tag[0] = $tag[0] + 1;
        $_tag[1] = 0;
        $_tag[2] = 0;
        break;
      case 'minor':
        $_tag[0] = $tag[0];
        $_tag[1] = $tag[1] + 1;
        $_tag[2] = 0;
        break;
      case 'patch':
      case 'default':
        $_tag[2] = $tag[0] + 1;
        break;
    }

    return $prefix . $sep . $_tag[0] . $sep . $_tag[1] . $sep . $_tag[2];
  }

  public static function explode($tag, $sep) {
    $tags = explode($sep, $tag);
    if (count($tags) > 3) {
      throw new \RuntimeException('Too many tag elements');
    }
    elseif (count($tags == 3)) {
      // preg_replace("/\D+/", "", $input_lines);
        $_tags[0] = self::clearNonNumeric($tags[0]);
      $_tags[1] = self::clearNonNumeric($tags[1]);
      $_tags[2] = self::clearNonNumeric($tags[2]);
    }
    elseif (count($tags == 2)) {
      $_tags[0] = 0;
      $_tags[1] = self::clearNonNumeric($tags[0]);
      $_tags[2] = self::clearNonNumeric($tags[1]);
    }
    elseif (count($tags == 1)) {
      $tags[0] = 0;
      $tags[1] = 0;
      $tags[2] = self::clearNonNumeric($tags[0]);
    }
    elseif (count($tags == 0)) {
      $tags[0] = 0;
      $tags[1] = 0;
      $tags[2] = 0;
    }

    return $_tags;
  }

  private static function clearNonNumeric($input) {
    return preg_replace("/\D+/", "", $input);
  }

  protected function exec($cmd, &$output) {
    if ($this->pretend) {
      echo $cmd . "\n";
    }
    else {
      exec($cmd, $output);
    }
  }

  public function commit($msg) {
    $cmd = sprintf(
      "git -C %s commit -a --allow-empty -m '%s'", $this->target, $msg
    );
    syslog(LOG_INFO, basename(__FILE__) . ': ' . __METHOD__ . ': ' . $cmd);
    $output = array();
    $this->exec($cmd, $output);

    return $output;
  }

  public function pushWithTags() {
    $cmd = sprintf(
      "git -C %s push origin master --tags", $this->target
    );
    syslog(LOG_INFO, basename(__FILE__) . ': ' . __METHOD__ . ': ' . $cmd);
    $output = array();
    $this->exec($cmd, $output);

    return $output;
  }

  public function add($file) {
    $cmd = sprintf(
      "git -C %s add '%s'", $this->target, $file
    );
    syslog(LOG_INFO, basename(__FILE__) . ': ' . __METHOD__ . ': ' . $cmd);
    $output = array();
    $this->exec($cmd, $output);

    return $output;
  }

  public function tag($new_tag, $last_log) {
    $_last_log = $this->cleanLastLog($last_log);
    $cmd = sprintf(
      "git -C %s tag %s -m '%s'", $this->target, $new_tag, json_encode($_last_log)
    );
    syslog(LOG_INFO, basename(__FILE__) . ': ' . __METHOD__ . ': ' . $cmd);
    $output = array();
    $this->exec($cmd, $output);

    return $output;
  }

  public function push($branch = 'master') {
    $cmd = sprintf(
      "git -C %s push origin %s", $this->target, $branch
    );
    syslog(LOG_INFO, basename(__FILE__) . ': ' . __METHOD__ . ':  ' . $cmd);
    $output = array();
    $this->exec($cmd, $output);

    return $output;
  }

  public function getChangeLog() {
    $cmd = sprintf("git -C %s tag -n9", $this->target);
    $output = array();
    exec($cmd, $output);

    $changelog = array();
    foreach ($output as $tag) {
      list($version, $message) = explode(' ', $tag);
      $commit_hash_cmd = shell_exec(sprintf('git %s rev-list -n 1 %s', $this->target, $tag));
      $changelog[$commit_hash] = $tag;
    }

    return $output;
  }

}
