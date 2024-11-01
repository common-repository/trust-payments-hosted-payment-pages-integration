<?php

namespace Securetrading\Data\Tests\Unit;

class ExtendedDataWithSetter extends \Securetrading\Data\Data {
  protected function _setSomething($value) {
    $this->_set('something', strtoupper($value));
  }
}

class ExtendedDataWithGetter extends \Securetrading\Data\Data {
  protected function _getSomething($retrievedValue, $default) {
    if ($retrievedValue === 'returndefault') {
      $return = $default;
    }
    else {
      $return = 'RETRIEVED_' . $retrievedValue;
    }
    return $return;
  }
}

class ExtendedDataWithHasFunction extends \Securetrading\Data\Data {
  protected function _hasSomething($key) {
    return 'returned_from_has_function';
  }
}

class ExtendedDataWithUnsetFunction extends \Securetrading\Data\Data {
  protected function _unsSomething() {
    $this->_unset('something');
    $this->_unset('something_else');
  }
}

class DataTest extends \Securetrading\Unittest\UnittestAbstract {
  /**
   *
   */
  public function testSetSingle() {
    $data = new ExtendedDataWithSetter();
    $data->setSingle('key', 'value1');
    $data->setSingle('something', 'value2');
    $internalDataArray = $this->_getPrivateProperty($data, '_data');
    $this->assertEquals('value1', $internalDataArray['key']);
    $this->assertEquals('VALUE2', $internalDataArray['something']);
  }

  /**
   *
   */
  public function testSetSingle_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $this->assertSame($data, $data->setSingle('key1', 'value1'));
  }

  /**
   *
   */
  public function testSetMultiple() {
    $data = new ExtendedDataWithSetter();
    $data->setMultiple(array(
      'key1' => 'value1', 
      'something' => 'value2',
    ));
    $internalDataArray = $this->_getPrivateProperty($data, '_data');
    $this->assertEquals($internalDataArray, array(
      'key1' => 'value1',
      'something' => 'VALUE2',
    ));
  }

  /**
   *
   */
  public function testSetMultiple_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $this->assertSame($data, $data->setMultiple(array('key1', 'value1')));
  }

  /**
   *
   */
  public function testSet_WithOneParam() {
    $data = new ExtendedDataWithSetter();
    $data->set(array(
      'key1' => 'value1',
      'something' => 'value2',
    ));
    $internalDataArray = $this->_getPrivateProperty($data, '_data');
    $this->assertEquals($internalDataArray, array(
      'key1' => 'value1',
      'something' => 'VALUE2',
    ));
  }

  /**
   *
   */
  public function testSet_WithTwoParams() {
    $data = new ExtendedDataWithSetter();
    $data->set('key1', 'value1');
    $data->set('something', 'value2');
    $internalDataArray = $this->_getPrivateProperty($data, '_data');
    $this->assertEquals($internalDataArray, array(
      'key1' => 'value1',
      'something' => 'VALUE2',
    ));
  }

  /**
   *
   */
  public function testSet_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $this->assertSame($data, $data->set('key1', 'value1'));
  }

  /**
   * 
   */
  public function testGetSingle() {
    $data = new ExtendedDataWithGetter();
    $data->set(array(
      'key1' => 'value1',
      'key2' => 'value2',
      'something' => 'myval',
    ));

    $this->assertEquals('value1', $data->getSingle('key1', 'defaultvalue'));
    $this->assertEquals('defaultvalue', $data->getSingle('key9', 'defaultvalue'));
    $this->assertEquals('RETRIEVED_myval', $data->getSingle('something', 'defaultvalue'));

    $data->set('something', 'returndefault');
    $this->assertEquals('defaultvalue', $data->getSingle('something', 'defaultvalue'));
  }

  /**
   *
   */
  public function testGetMultiple() {
    $data = new ExtendedDataWithGetter();
    $data->set(array(
      'key1' => 'value1',
      'key2' => 'value2',
      'something' => 'value3',
    ));
    $this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $data->getMultiple(array('key1', 'key2')));
    $this->assertEquals(array('something' => 'RETRIEVED_value3'), $data->getMultiple(array('something')));
    $this->assertEquals(array('unset_key' => 'DEFAULT'), $data->getMultiple(array('unset_key'), 'DEFAULT'));
  }

  /**
   *
   */
  public function testGetAll() {
    $data = new ExtendedDataWithGetter();
    $data->set(array(
      'key1' => 'value1',
      'something' => 'value2',
    ));

    $expectedReturnValue = array(
      'key1' => 'value1',
      'something' => 'RETRIEVED_value2',
    );

    $this->assertEquals($expectedReturnValue, $data->getAll());
  }

  /**
   *
   */
  public function testGet_WhenCallsGetAll() {
    $data = new ExtendedDataWithGetter();
    $data->setSingle('key1', 'value1');
    $data->setSingle('key2', 'value2');
    $data->setSingle('something', 'value3');

    $expectedReturnValue = array(
      'key1' => 'value1',
      'key2' => 'value2',
      'something' => 'RETRIEVED_value3',
    );

    $actualReturnValue = $data->get();
    $this->assertEquals($expectedReturnValue, $actualReturnValue);
  }

  /**
   *
   */
  public function testGet_WhenCallsGetMultiple() {
    $data = new ExtendedDataWithGetter();
    $data->setSingle('key1', 'value1');
    $data->setSingle('key2', 'value2');
    $data->setSingle('something', 'value3');
    
    $expectedReturnValue = array(
      'key1' => 'value1',
      'something' => 'RETRIEVED_value3',
      'something_else' => 'default_value',
    );

    $actualReturnValue = $data->get(array('key1', 'something', 'something_else'), 'default_value');
    $this->assertEquals($expectedReturnValue, $actualReturnValue);    
  }

  /**
   *
   */
  public function testGet_WhenCallsGetSingle() {
    $data = new ExtendedDataWithGetter();
    $data->setSingle('key1', 'value1');
    $data->setSingle('something', 'value2');
    
    $this->assertEquals('value1', $data->get('key1', 'default_value'));
    $this->assertEquals('RETRIEVED_value2', $data->get('something', 'default_value'));
    $this->assertEquals('default_value', $data->get('unknown_key', 'default_value'));

    $data->set('something', 'returndefault');
    $this->assertEquals('defaultvalue', $data->getSingle('something', 'defaultvalue'));
  }

  /**
   * 
   */
  public function testHasSingle() {
    $data = new ExtendedDataWithHasFunction();
    $this->assertEquals(false, $data->hasSingle('key1'));
    $data->setSingle('key1', 'value1');
    $this->assertEquals(true, $data->hasSingle('key1'));

    $this->assertEquals(true, $data->hasSingle('something'));
  }

  /**
   *
   */
  public function testHasMultiple() {
    $data = new ExtendedDataWithHasFunction();
    $data->setSingle('key1', 'value1');
    $data->setSingle('key2', 'value2');
    
    $this->assertEquals(true, $data->hasMultiple(array('key1', 'key2')));
    $this->assertEquals(false, $data->hasMultiple(array('key1', 'key2', 'key3')));
    $this->assertEquals(true, $data->hasMultiple(array('key1', 'key2', 'something')));
  }

  /**
   *
   */
  public function testHas_WhenCallingHasMultiple() {
    $data = new ExtendedDataWithHasFunction();
    $data->setSingle('key1', 'value1');
    $data->setSingle('key2', 'value2');
    
    $this->assertEquals(true, $data->has(array('key1', 'key2')));
    $this->assertEquals(false, $data->has(array('key1', 'key2', 'key3')));
    $this->assertEquals(true, $data->has(array('key1', 'key2', 'something')));
  }

  /**
   *
   */
  public function testHas_WhenCallingHasSingle() {
    $data = new ExtendedDataWithHasFunction();
    $this->assertEquals(false, $data->has('key1'));
    $data->setSingle('key1', 'value1');
    $this->assertEquals(true, $data->has('key1'));

    $this->assertEquals(true, $data->has('something'));
  }

  /**
   * 
   */
  public function testUnsSingle() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    $data->unsSingle('something');
    $this->assertEquals(array('a' => 'b', 'c' => 'd'), $data->get());
    $data->unsSingle('c');
    $this->assertEquals(array('a' => 'b'), $data->get());
  }

  /**
   *
   */
  public function testUnsSingle_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $returnValue = $data->unsSingle('a_key');
    $this->assertSame($data, $returnValue);
  }

  /**
   *
   */
  public function testUnsMultiple() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    $data->unsMultiple(array('something', 'a'));
    $this->assertEquals(array('c' => 'd'), $data->get());
  }

  /**
   *
   */
  public function testUnsMultiple_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $returnValue = $data->unsMultiple(array('a_key'));
    $this->assertSame($data, $returnValue);
  }

  /**
   *
   */
  public function testUnsAll() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    $data->unsAll();
    $this->assertEquals(array(), $data->get());
  }

  /**
   *
   */
  public function testUnsAll_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $returnValue = $data->unsAll();
    $this->assertSame($data, $returnValue);
  }

  /**
   *
   */
  public function testUns_WhenCallingUnsSingle() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    $data->uns('something');
    $this->assertEquals(array('a' => 'b', 'c' => 'd'), $data->get());
    $data->uns('c');
    $this->assertEquals(array('a' => 'b'), $data->get());
  }

  /**
   *
   */
  public function testUns_WhenCallingUnsMultiple() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    $data->uns(array('something', 'a'));
    $this->assertEquals(array('c' => 'd'), $data->get());
  }

  /**
   *
   */
  public function testUns_ReturnValue() {
    $data = new \Securetrading\Data\Data();
    $returnValue = $data->uns('key');
    $this->assertSame($data, $returnValue);
  }

  /**
   * 
   */
  public function testClear() {
    $data = new \Securetrading\Data\Data();
    $data->set('a', 'b');
    $data->set('c', 'd');
    $this->assertEquals(array('a' => 'b', 'c' => 'd'), $data->get());
    $data->clear();
    $this->assertEquals(array(), $data->get());
  }

  /**
   * 
   */
  public function testReplace() {
    $data = new ExtendedDataWithSetter();
    $data->set('key1', 'value1');    
    $this->assertEquals(array('key1' => 'value1'), $data->get());
    $data->replace(array('key2' => 'value2', 'something' => 'value3'));
    $this->assertEquals(array('key2' => 'value2', 'something' => 'VALUE3'), $data->get());
  }

  /**
   *
   */
  public function testToArray() {
    $dataOne = new \Securetrading\Data\Data();
    $dataTwo = new \Securetrading\Data\Data();
    $dataThree = new \Securetrading\Data\Data();
    $dataFour = new \Securetrading\Data\Data();

    $dataOne->setMultiple(array(
      'key1' => 'value1',
      'key2' => $dataTwo
    ));

    $dataTwo->setMultiple(array(
      'key1' => 'value1',
      'key2' => $dataThree
    ));
    
    $dataThree->setMultiple(array(
      'key1' => $dataFour,
      'key2' => 'value1'
    ));

    $dataFour->setMultiple(array(
      'key1' => 'value1',
      'key2' => 'value2',
    ));

    $expectedReturnValue = array(
      'key1' => 'value1',
      'key2' => array(
	'key1' => 'value1',
	'key2' => array(
	  'key1' => array(
	    'key1' => 'value1',
	    'key2' => 'value2',
	  ),
	  'key2' => 'value1',
	),
      ),
    );

    $actualReturnValue = $dataOne->toArray();
    $this->assertEquals($expectedReturnValue, $actualReturnValue);
  }

  /**
   *
   */
  public function testFromArray() {
    $data = new \Securetrading\Data\Data();
    $returnValue = $data->fromArray(array(
      'key1' => 'value1',
      'key2' => array(
        'key3' => 'value3',
	'key4' => array(
          'key5' => 'value5',
	),
      ),
    ));
    
    $this->assertSame($data, $returnValue);
    $this->assertInstanceOf('\Securetrading\Data\Data', $data->get('key2'));
    $this->assertInstanceOf('\Securetrading\Data\Data', $data->get('key2')->get('key4'));

    $this->assertEquals('value1', $data->get('key1'));
    $this->assertEquals('value3', $data->get('key2')->get('key3'));
    $this->assertEquals('value5', $data->get('key2')->get('key4')->get('key5'));

    $data = new \Securetrading\Data\Data();
    $returnValue = $data->fromArray(array(
      'key1' => 'value1',
      'key2' => array(
        'key3' => 'value3',
	'key4' => array(
          'key5' => 'value5',
	),
      ),
    ), '\Securetrading\Data\Tests\Unit\ExtendedDataWithSetter');

    $this->assertSame($data, $returnValue);
    $this->assertInstanceOf('\Securetrading\Data\Tests\Unit\ExtendedDataWithSetter', $data->get('key2'));
    $this->assertInstanceOf('\Securetrading\Data\Tests\Unit\ExtendedDataWithSetter', $data->get('key2')->get('key4'));

    $this->assertEquals('value1', $data->get('key1'));
    $this->assertEquals('value3', $data->get('key2')->get('key3'));
    $this->assertEquals('value5', $data->get('key2')->get('key4')->get('key5'));
  }

  /**
   * 
   */
  public function testCount() {
    $data = new \Securetrading\Data\Data();
    $this->assertEquals(0, count($data));
    $data->setMultiple(array('value1', 'value2'));
    $this->assertEquals(2, count($data));
  }

  /**
   *
   */
  public function testArrayAccess_OffsetExists() {
    $data = new ExtendedDataWithHasFunction();
    $this->assertEquals(true, isset($data['something']));
    $this->assertEquals(false, isset($data['something_else']));
  }

  /**
   *
   */
  public function testArrayAccess_OffsetGet() {
    $data = new ExtendedDataWithGetter();
    $data->set('something', 'value1');
    $data->set('something_else', 'value2');
    $this->assertEquals('RETRIEVED_value1', $data['something']);
    $this->assertEquals('value2', $data['something_else']);
  }

  /**
   *
   */
  public function testArrayAccess_OffsetSet() {
    $data = new ExtendedDataWithSetter();
    $data['something'] = 'value1';
    $data['something_else'] = 'value2';
    $this->assertEquals('VALUE1', $data['something']);
    $this->assertEquals('value2', $data['something_else']);
  }
 
  /**
   *
   */
  public function testArrayAccess_OffsetUnset() {
    $data = new ExtendedDataWithUnsetFunction();
    $data->set(array('something' => 'something_value', 'something_else' => 'something_else_value', 'a' => 'b', 'c' => 'd'));
    unset($data['something']);
    $this->assertEquals(array('a' => 'b', 'c' => 'd'), $data->get());
    unset($data['c']);
    $this->assertEquals(array('a' => 'b'), $data->get()); 
  }

  /**
   * 
   */
  public function testIterable() {
    $dataToSet = array(
      'k1' => 'v1',
      'k2' => 'v2',
      'k3' => 'v3',
    );

    $data = new \Securetrading\Data\Data();
    $data->set($dataToSet);

    $seen = array();
    foreach($data as $k => $v) {
      $seen[$k] = $v;
    }

    $this->assertEquals($dataToSet, $seen);
  }

  /**
   * @dataProvider providerIterable_WhenUnsettingDataInLoop
   */
  public function testIterable_WhenUnsettingDataInLoop($keysToUnset, $expectedFinalData) {
    $expectedSeen = array(
      'k1' => 'v1',
      'k2' => 'v2',
      'k3' => 'v3',
      'k4' => 'k4',
      'k5' => 'v5',
      'k6' => 'v6',
    );

    $data = new \Securetrading\Data\Data();
    $data->set($expectedSeen);

    $actualSeen = array();

    foreach($data as $k => $v) {
      if ($k === 'k3') {
	$data->unsMultiple($keysToUnset);
      }
      $actualSeen[$k] = $v;
    }
    
    $this->assertEquals($expectedSeen, $actualSeen);
    $this->assertEquals($expectedFinalData, $data->get());
  }

  public function providerIterable_WhenUnsettingDataInLoop() {
    $this->_addDataSet(
      array('k1', 'k2'),
      array(
        'k3' => 'v3',
        'k4' => 'k4',
        'k5' => 'v5',
        'k6' => 'v6',
      )
    );

    $this->_addDataSet(
      array('k3'),
      array(
        'k1' => 'v1',
        'k2' => 'v2',
        'k4' => 'k4',
        'k5' => 'v5',
        'k6' => 'v6',
      )
    );

    $this->_addDataSet(
      array('k5'),
      array(
        'k1' => 'v1',
        'k2' => 'v2',
        'k3' => 'v3',
        'k4' => 'k4',
        'k6' => 'v6',
      )
    );

    return $this->_getDataSets();
  }
}