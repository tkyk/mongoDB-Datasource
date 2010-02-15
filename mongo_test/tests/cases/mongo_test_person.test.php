<?php

App::import('Model', 'MongoTest.MongoTestPerson');

class MongoTestPersonCase extends CakeTestCase
{
  var $Person;

  function startTest() {
    $this->Person = ClassRegistry::init('MongoTestPerson');

    $this->Person->id = null;
    $db =& ConnectionManager::getDataSource($this->Person->useDbConfig);
    $db->delete($this->Person, array());
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


}
