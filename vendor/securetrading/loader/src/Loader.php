<?php

namespace Securetrading\Loader;

class Loader {
  public static function getRootPath() {
    return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . '/';
  }

  public static function getVarPath() {
    return static::getRootPath() . 'var' . DIRECTORY_SEPARATOR;
  }
  
  public static function getLogPath() {
    return static::getVarPath() . 'log' . DIRECTORY_SEPARATOR;
  }
  
  public static function getLogArchivePath() {
    return static::getLogPath() . 'archive' . DIRECTORY_SEPARATOR;
  }
}