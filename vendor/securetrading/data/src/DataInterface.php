<?php

namespace Securetrading\Data;

interface DataInterface extends \ArrayAccess, \Countable, \Iterator {
  function setSingle($key, $value);
  function setMultiple(array $values);
  function set($key, $value = null);
  function getSingle($key, $default = null);
  function getMultiple(array $keys, $default = null);
  function getAll();
  function get($key = null, $default = null);
  function hasSingle($key);
  function hasMultiple(array $keys);
  function has($key);
  function unsSingle($key);
  function unsMultiple(array $keys);
  function unsAll();
  function uns($key);
  function clear();
  function replace(array $array);
  function toArray();
  function fromArray(array $data, $type = '\Securetrading\Data\Data');
}