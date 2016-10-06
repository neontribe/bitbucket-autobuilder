<?php

spl_autoload_register(
  function ($class) {
  // Project specific namespace
  $prefix = 'uk\\co\\neontabs\\bbucket\\';

  // Base directory
  $base_dir = __DIR__ . '/includes/';

  // Does the class use this namespace?
  $len = strlen($prefix);

  // @codeCoverageIgnoreStart
  if (strncmp($prefix, $class, $len) !== 0) {
    // no
    return;
  }
  // @codeCoverageIgnoreEnd
  // Replace namespace prefix with the base directory, replace namepace
  // separators with directory separators in teh relative class name,
  // append with .class.php
  $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

  if (file_exists($file)) {
    include $file;
  }
}
);
