<?php

namespace uk\co\neontabs\bbucket;

class Payload {
  protected $payload;

  public function __construct($payload) {
    $this->payload = json_decode($payload, TRUE);
  }

}
