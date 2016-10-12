<?php

namespace uk\co\neontabs\bbucket;

class Payload {
  protected $payload;

  public function __construct($payload) {
    $this->payload = json_decode($payload, TRUE);
  }

  public function getFullName() {
    return $this->payload['repository']['full_name'];
  }

  public function getPR() {
    return $this->payload['pullrequest']['links']['html']['href'];
  }

  public function getState() {
    if (isset($this->payload['pullrequest']['state'])) {
        return $this->payload['pullrequest']['state'];
    }

    return FALSE;
  }
}
