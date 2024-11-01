<?php

namespace Securetrading\Data;

class Data implements DataInterface {
  protected $_data = array();
    
  protected $_copy = array();
  
  protected $_position = 0;
  
  protected function _get($key, $default = null) {
    return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
  }

  protected function _set($key, $value) {
    $this->_data[$key] = $value;
  }
  
  protected function _has($key) {
    return array_key_exists($key, $this->_data);
  }
  
  protected function _unset($key) {
    unset($this->_data[$key]);
  }
  
  public function setSingle($key, $value) {
    $setterMethod = '_set' . $key;
    if (method_exists($this, $setterMethod)) {
      $this->$setterMethod($value);
    }
    else {
      $this->_set($key, $value);
    }
    return $this;
  }
  
  public function setMultiple(array $values) {
    foreach($values as $k => $v) {
      $this->setSingle($k, $v);
    }
    return $this;
  }
  
  public function set($key, $value = null) {
    if (is_array($key)) {
      $this->setMultiple($key);
    }
    else {
      $this->setSingle($key, $value);
    }
    return $this;
  }
  
  public function getSingle($key, $default = null) {
    $value = $this->_get($key, $default);
    $getterMethod = '_get' . $key;        
    if (method_exists($this, $getterMethod)) {
      $value = $this->$getterMethod($value, $default);
    }
    return $value;
  }
  
  public function getMultiple(array $keys, $default = null) {
    $array = array();
    foreach($keys as $key) {
      $array[$key] = $this->getSingle($key, $default);
    }
    return $array;
  }
  
  public function getAll() {
    $array = array();
    foreach(array_keys($this->_data) as $key) {
      $array[$key] = $this->getSingle($key);
    }
    return $array;
  }
  
  public function get($key = null, $default = null) {
    if ($key === null) {
      $return = $this->getAll();
    }
    else if (is_array($key)) {
      $return = $this->getMultiple($key, $default);
    }
    else {
      $return = $this->getSingle($key, $default);
    }
    return $return;
  }
  
  public function hasSingle($key) {
    $hasMethod = '_has' . $key;
    if (method_exists($this, $hasMethod)) {
      return (bool) $this->$hasMethod($key);
    }
    else {
      return $this->_has($key);
    }      
  }
  
  public function hasMultiple(array $keys) {
    $return = true;
    foreach($keys as $key) {
      if (!$this->hasSingle($key)) {
	$return = false;
	break;
      }
    }
    return $return;
  }
  
  public function has($key) {
    if (is_array($key)) {
      $return = $this->hasMultiple($key);
    }
    else {
      $return = $this->hasSingle($key);
    }
    return $return;
  }
  
  public function unsSingle($key) {
    $unsMethod = '_uns' . $key;
    if (method_exists($this, $unsMethod)) {
      $this->$unsMethod();
    }
    else {
      $this->_unset($key);
    }
    return $this;
  }
  
  public function unsMultiple(array $keys) {
    foreach($keys as $key) {
      $this->unsSingle($key);
    }
    return $this;
  }
  
  public function unsAll() {
    foreach(array_keys($this->_data) as $key) {
      $this->unsSingle($key);
    }
    return $this;
  }
  
  public function uns($key) {
    if (is_array($key)) {
      $this->unsMultiple($key);
    }
    else {
      $this->unsSingle($key);
    }
    return $this;
  }

  public function clear() {
    $this->_data = array();
    return $this;
  }
  
  public function replace(array $array) {
    $this->clear();
    $this->setMultiple($array);
  }
  
  public function toArray() {
    $array = array();
    foreach($this->_data as $k => $v) {
      if ($v instanceof DataInterface) {
	$array[$k] = $this->_data[$k]->toArray();
      }
      else {
	$array[$k] = $v;
      }
    }
    return $array;
  }

  public function fromArray(array $data, $type = '\Securetrading\Data\Data') {
    $recursiveFunction = function($data, $object) use (&$recursiveFunction, $type) {
      foreach($data as $key => $value) {
        $value = is_array($value) ? call_user_func($recursiveFunction, $value, new $type) : $value;
        $object->setSingle($key, $value);
      }
      return $object;
    };

    $recursiveFunction($data, $this);
    return $this;
  }
  
  // ArrayAccess
  
  public function offsetExists($offset) : bool {
    return $this->hasSingle($offset);
  }
  
  public function offsetGet($offset) : mixed {
    return $this->getSingle($offset);
  }
  
  public function offsetSet($offset, $value) : void {
    $this->setSingle($offset, $value);
  }
  
  public function offsetUnset($offset) : void {
    $this->unsSingle($offset);
  }
  
  // Countable:
  
  public function count() : int {
    return count($this->_data);
  }
  
  // Iterable:
  
  public function current() : mixed {
    return current($this->_copy);
  }
  
  public function key() : mixed {
    return key($this->_copy);
  }
  
  public function next() : void {
    $this->_position++;
    next($this->_copy);
  }
    
  public function rewind() : void {
    $this->_copy = $this->_data;
    $this->_position = 0;
    reset($this->_copy);
  }
  
  public function valid() : bool {
    return $this->_position < count($this->_copy);
  }
}
