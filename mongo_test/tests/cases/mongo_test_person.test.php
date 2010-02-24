<?php

App::import('Model', 'MongoTest.MongoTestPerson');

class MongoTestPersonCase extends CakeTestCase
{
  var $Person;
  var $prevDebug;
  var $debugParam = 1;

  function startCase() {
    $this->prevDebug = Configure::read('debug');
    Configure::write('debug', $this->debugParam);
  }

  function endCase() {
    Configure::write('debug', $this->prevDebug);
  }

  function startTest() {
    $this->Person = ClassRegistry::init('MongoTestPerson');

    $this->Person->id = null;
    $db =& ConnectionManager::getDataSource($this->Person->useDbConfig);
    $db->delete($this->Person, array());
    $db->setDebugMode(Configure::read('debug'));
  }

  function testInit() {
    $this->assertIsA($this->Person, 'MongoTestPerson');
    $this->assertTrue($this->Person->Behaviors->enabled('MongoDocument'));
  }

  /**
   * @return last insert id
   */
  function _insert($data) {
    $this->Person->create();
    $this->assertTrue($this->Person->save($data));
    return $this->Person->getLastInsertID();
  }

  function assertStringId($id) {
    $this->assertTrue(is_string($id) && strlen($id) > 0);
  }

  function testCreate() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $this->assertStringId($id1);

    $id2 = $this->_insert($p2 = array('lastname' => 'John',
				      'firstname' => 'Smith',
				      'hobby' => array('baseball', 'walking')));
    $this->assertStringId($id2);
    $this->assertNotEqual($id1, $id2);
  }

  function testFindFirstBy_Id() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 99));
    // _id only
    $read1 = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $this->assertTrue($read1 && is_array($read1[$this->Person->alias]));

    $data = $read1[$this->Person->alias];
    $readId = $data['_id'];
    unset($data['_id']);
    $this->assertStringId($readId);
    $this->assertEqual($readId, $id1);
    $this->assertEqual($p1, $data);

    // with prefix
    $read2 = $this->Person->find('first', array('conditions' => 
						array($this->Person->alias.'._id' => $id1)));
    $this->assertEqual($read1, $read2);
  }

  function testDelete() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $this->assertStringId($id1);

    $this->assertTrue($this->Person->delete($id1));
    $ret = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $this->assertFalse($ret);
  }

  function _testDeleteAll($cascade) {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $id2 = $this->_insert($p2 = array('name' => 'Paul Smith',
				      'age' => 25));
    $id3 = $this->_insert($p3 = array('name' => 'Paul Smith',
				      'age' => 99));
    $this->assertStringId($id1);
    $this->assertStringId($id2);
    $this->assertStringId($id3);

    $this->assertTrue($this->Person->deleteAll(array('age' => 25),
					       $cascade));
    $this->assertFalse($this->Person->find('first', array('conditions' => array('_id' => $id1))));
    $this->assertFalse($this->Person->find('first', array('conditions' => array('_id' => $id2))));

    $alive = $this->Person->find('first', array('conditions' => array('_id' => $id3)));
    $this->assertEqual($alive[$this->Person->alias]['_id'], $id3);
  }

  function testDeleteAllWithCascade() {
    $this->_testDeleteAll(true);
  }

  function testDeleteAllWithoutCascade() {
    $this->_testDeleteAll(false);
  }

  function testUpdateBySaveWithParamId() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $this->assertStringId($id1);

    $newAge = 99;
    $this->Person->create();
    $this->assertTrue($this->Person->save(array('_id' => $id1,
						'age' => $newAge)));
    
    $read = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $this->assertEqual($read[$this->Person->alias]['_id'],  $id1);
    $this->assertEqual($read[$this->Person->alias]['name'], $p1['name']);
    $this->assertEqual($read[$this->Person->alias]['age'],  $newAge);
  }

  function testUpdateBySaveWithModelId() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $this->assertStringId($id1);

    $newAge = 99;
    $this->Person->create();
    $this->Person->id = $id1;
    $this->assertTrue($this->Person->save(array('age' => $newAge)));
    
    $read = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $this->assertEqual($read[$this->Person->alias]['_id'],  $id1);
    $this->assertEqual($read[$this->Person->alias]['name'], $p1['name']);
    $this->assertEqual($read[$this->Person->alias]['age'],  $newAge);
  }

  function testUpdateAll() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $id2 = $this->_insert($p2 = array('name' => 'Paul Smith',
				      'age' => 25));
    $id3 = $this->_insert($p3 = array('name' => 'Paul Smith',
				      'age' => 99));
    $this->assertStringId($id1);
    $this->assertStringId($id2);
    $this->assertStringId($id3);

    $newName = 'young person';
    $this->assertTrue($this->Person->updateAll(array('name' => $newName),
					       array('age' => 25)));

    $read1 = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $read2 = $this->Person->find('first', array('conditions' => array('_id' => $id2)));
    $read3 = $this->Person->find('first', array('conditions' => array('_id' => $id3)));

    foreach(range(1,3) as $i) {
      $this->assertEqual(${"read$i"}[$this->Person->alias]['_id'], ${"id$i"});
      $this->assertEqual(${"read$i"}[$this->Person->alias]['age'], ${"p$i"}['age']);
    }

    $this->assertEqual($read1[$this->Person->alias]['name'], $newName);
    $this->assertEqual($read2[$this->Person->alias]['name'], $newName);
    $this->assertEqual($read3[$this->Person->alias]['name'], $p3['name']);
  }

  /**
   * boolean true and any empty values mean 'all'
   */
  function testUpdateAllNoConditions() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $id2 = $this->_insert($p2 = array('name' => 'Paul Smith',
				      'age' => 25));
    $id3 = $this->_insert($p3 = array('name' => 'Paul Smith',
				      'age' => 99));
    $this->assertStringId($id1);
    $this->assertStringId($id2);
    $this->assertStringId($id3);

    $cond = array('empty_string' => "",
		  'boolean_true' => true,
		  'empty_array' => array());

    foreach($cond as $newName => $conditions) {
      $this->assertTrue($this->Person->updateAll(array('name' => $newName),
						 $conditions));

      $read1 = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
      $read2 = $this->Person->find('first', array('conditions' => array('_id' => $id2)));
      $read3 = $this->Person->find('first', array('conditions' => array('_id' => $id3)));

      foreach(range(1,3) as $i) {
	$this->assertEqual(${"read$i"}[$this->Person->alias]['_id'], ${"id$i"});
	$this->assertEqual(${"read$i"}[$this->Person->alias]['age'], ${"p$i"}['age']);
	$this->assertEqual(${"read$i"}[$this->Person->alias]['name'], $newName);
      }
    }
  }

  /**
   * boolean false means "Don't update any records".
   */
  function testUpdateAllFalseConditions() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $id2 = $this->_insert($p2 = array('name' => 'Paul Smith',
				      'age' => 25));
    $id3 = $this->_insert($p3 = array('name' => 'Paul Smith',
				      'age' => 99));
    $this->assertStringId($id1);
    $this->assertStringId($id2);
    $this->assertStringId($id3);

    $newName = 'never_used';
    $conditions = false;

    $this->assertTrue($this->Person->updateAll(array('name' => $newName),
					       $conditions));
    $read1 = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $read2 = $this->Person->find('first', array('conditions' => array('_id' => $id2)));
    $read3 = $this->Person->find('first', array('conditions' => array('_id' => $id3)));

    foreach(range(1,3) as $i) {
      $this->assertEqual(${"read$i"}[$this->Person->alias]['_id'], ${"id$i"});
      $this->assertEqual(${"read$i"}[$this->Person->alias]['age'], ${"p$i"}['age']);
      $this->assertEqual(${"read$i"}[$this->Person->alias]['name'], ${"p$i"}['name']);
    }
  }

  function testUpdateAllAddingNewFields() {
    $id1 = $this->_insert($p1 = array('name' => 'John Smith',
				      'age' => 25));
    $id2 = $this->_insert($p2 = array('name' => 'Paul Smith',
				      'age' => 25));
    $id3 = $this->_insert($p3 = array('name' => 'Paul Smith',
				      'age' => 99));
    $this->assertStringId($id1);
    $this->assertStringId($id2);
    $this->assertStringId($id3);

    $newFields = array('address' => 'kyoto');
    $this->assertTrue($this->Person->updateAll($newFields,
					       array('age' => 25)));

    $read1 = $this->Person->find('first', array('conditions' => array('_id' => $id1)));
    $read2 = $this->Person->find('first', array('conditions' => array('_id' => $id2)));
    $read3 = $this->Person->find('first', array('conditions' => array('_id' => $id3)));

    foreach(range(1,3) as $i) {
      $this->assertEqual(${"read$i"}[$this->Person->alias]['_id'], ${"id$i"});
      $this->assertEqual(${"read$i"}[$this->Person->alias]['age'], ${"p$i"}['age']);
      $this->assertEqual(${"read$i"}[$this->Person->alias]['name'], ${"p$i"}['name']);
    }

    $this->assertEqual($read1[$this->Person->alias]['address'], $newFields['address']);
    $this->assertEqual($read2[$this->Person->alias]['address'], $newFields['address']);
    $this->assertTrue(!array_key_exists('address', $read3[$this->Person->alias]));
  }

  function testFindAll() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    // all
    $ret = $this->Person->find('all');
    $this->assertEqual(count($ret), 20);
    foreach($ret as $_r) {
      $r = $_r[$this->Person->alias];
      $this->assertTrue(isset($r['_id']) && isset($r['name']) && isset($r['num']));
      $num = $r['num'];
      $this->assertEqual($r['name'], "name-{$num}");
    }

    // greater than 10
    $ret = $this->Person->find('all', array('conditions' => array('num' => array('$gt' => 10))));
    $this->assertEqual(count($ret), 10);
    foreach($ret as $_r) {
      $r = $_r[$this->Person->alias];
      $this->assertTrue(isset($r['_id']) && isset($r['name']) && isset($r['num']));
      $num = $r['num'];
      $this->assertTrue($num > 10);
      $this->assertEqual($r['name'], "name-{$num}");
    }

    // no match
    $ret = $this->Person->find('all', array('conditions' => false));
    $this->assertTrue(is_array($ret));
    $this->assertEqual(count($ret), 0);
  }

  function testFindAllFields() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    // name and num; _id is always returned
    $ret = $this->Person->find('all', array('fields' => array('name', 'num')));
    $this->assertEqual(count($ret), 20);
    foreach($ret as $_r) {
      $r = $_r[$this->Person->alias];
      $this->assertTrue(isset($r['_id']) && isset($r['name']) && isset($r['num']));
      $num = $r['num'];
      $this->assertEqual($r['name'], "name-{$num}");
    }

    // num only; _id is always returned
    $ret = $this->Person->find('all', array('fields' => 'num',
					    'conditions' => array('num' => array('$gt' => 10))));
    $this->assertEqual(count($ret), 10);
    foreach($ret as $_r) {
      $r = $_r[$this->Person->alias];
      $this->assertTrue(isset($r['_id']) && !isset($r['name']) && isset($r['num']));
      $this->assertTrue($r['num'] > 10);
    }
  }

  function testFindAllOrderAndLimit() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $ret = $this->Person->find('all', array('order' => array('num' => -1),
					    'limit' => 2));

    $this->assertEqual(count($ret), 2);
    $this->assertEqual($ret[0][$this->Person->alias]['num'], 20);
    $this->assertEqual($ret[1][$this->Person->alias]['num'], 19);
  }

  function testFindAllLimitAndOffset() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $ret = $this->Person->find('all', array('order' => array('num' => 1),
					    'limit' => 3,
					    'offset' => 10));

    $this->assertEqual(count($ret), 3);
    $this->assertEqual($ret[0][$this->Person->alias]['num'], 11);
    $this->assertEqual($ret[1][$this->Person->alias]['num'], 12);
    $this->assertEqual($ret[2][$this->Person->alias]['num'], 13);
  }

  function testFindAllLimitAndPage() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $ret = $this->Person->find('all', array('order' => array('num' => 1),
					    'limit' => 3,
					    'page' => 4));

    $this->assertEqual(count($ret), 3);
    $this->assertEqual($ret[0][$this->Person->alias]['num'], 10);
    $this->assertEqual($ret[1][$this->Person->alias]['num'], 11);
    $this->assertEqual($ret[2][$this->Person->alias]['num'], 12);
  }

  function testFindAllManyParameters() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $ret = $this->Person->find('all', array('conditions' => array('num' => array('$lt' => 15,
										 '$gt' => 5)),
					    'order' => array('num' => 1),
					    'limit' => 100,
					    'offset' => 5));

    $this->assertEqual(count($ret), 4);
    $this->assertEqual($ret[0][$this->Person->alias]['num'], 11);
    $this->assertEqual($ret[1][$this->Person->alias]['num'], 12);
    $this->assertEqual($ret[2][$this->Person->alias]['num'], 13);
    $this->assertEqual($ret[3][$this->Person->alias]['num'], 14);
  }

  function testFindCount() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $this->assertEqual($this->Person->find('count'), 20);
    $this->assertEqual($this->Person->find('count', array('conditions' => array('num' => array('$gt' => 5)))), 15);
    $this->assertEqual($this->Person->find('count', array('conditions' => array('num' => array('$gt' => 20)))), 0);


    // limit and offset/page will be ignored because of pecl mongo's limitation.
    $this->assertEqual($this->Person->find('count', array('limit' => 100)), 20);
    $this->assertEqual($this->Person->find('count', array('limit' => 10)), 20);
    $this->assertEqual($this->Person->find('count', array('offset' => 15, 'limit' => 100)), 20);
  }

  function testQuery() {
    foreach(range(1,20) as $num) {
      $this->_insert(array('name' => "name-{$num}",
			   'num' => $num));
    }

    $deleted = $this->Person->deleteIndexes();
    $this->assertTrue(is_array($deleted) && $deleted['ok'] == 1.0);

    $prevIndex = $this->Person->getIndexInfo();
    $prev = count($prevIndex);
    $this->assertTrue(is_array($prevIndex));

    $this->assertTrue($this->Person->ensureIndex(array('num' => -1)));

    $afterIndex = $this->Person->getIndexInfo();
    $after = count($afterIndex);
    $this->assertTrue(is_array($afterIndex));
    $this->assertEqual($after, $prev + 1);
  }

  function testQueryUndefinedMethod() {
    $this->assertNull($this->Person->{'  @@undefinedMethod@@  '}());
  }

}


class MongoTestPersonWithDebugCase extends MongoTestPersonCase
{
  var $debugParam = 2;

  function testQueryUndefinedMethod() {
    $method = '  @@undefinedMethod@@  ';
    try {
      $this->Person->{$method}();
    }
    catch(Exception $e) {
      $err = $e->getMessage();
      $this->assertPattern('/undefined method/', $err);
      $this->assertPattern("/{$method}/", $err);
      return;
    }
    $this->fail("No exception was thrown.");
  }

}

