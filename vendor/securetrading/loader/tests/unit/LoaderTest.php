<?php

namespace Securetrading\Loader\Tests\Unit;

use \Securetrading\Loader\Loader as Loader;

class LoaderTest extends \Securetrading\Unittest\UnittestAbstract {
  public function setUp() : void {
    $this->_expectedRootPath = realpath(__DIR__ . '/../../../') . '/';
    $this->assertNotEquals(false, $this->_expectedRootPath);
  }

  /**
   *
   */
  public function testGetRootPath() {
    $this->assertEquals($this->_expectedRootPath, Loader::getRootPath());
  }

  /**
   *
   */
  public function testGetVarPath() {
    $this->assertEquals($this->_expectedRootPath . 'var/', Loader::getVarPath());
  }

  /**
   *
   */
  public function testGetLogPath() {
    $this->assertEquals($this->_expectedRootPath . 'var/log/', Loader::getLogPath());
  }

  /**
   *
   */
  public function testGetLogArchivePath() {
    $this->assertEquals($this->_expectedRootPath . 'var/log/archive/', Loader::getLogArchivePath());
  }
}