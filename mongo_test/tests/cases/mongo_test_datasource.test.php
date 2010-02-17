<?php

App::import('Model', 'MongoTest.MongoTestPerson');
Mock::generate('MongoDB');
Mock::generate('MongoCollection');
Mock::generate('MongoCursor');
Mock::generate('Model');
Mock::generate('BehaviorCollection');

class MyMockMongoCollection extends MockMongoCollection
{
  private $__nextId;

  function setNextId($id) {
    $this->__nextId = $id;
  }
  function insert(&$data, $param) {
    $ret = parent::insert($data, $param);
    $data['_id'] = new MongoId($this->__nextId);
    return $ret;
  }
}

class MongoTestDatasourceCase extends CakeTestCase
{
  var $Person;

  var $db;
  var $source;
  var $realSource;

  function startCase() {
    // loads MongodbSource through loading Model.
    ClassRegistry::init('MongoTestPerson');

    eval('
class MongoTestDatasource extends MongodbSource {
  function __construct($mockdb) {
    $this->_db = $mockdb;
  }
}
');
  }

  function _createModel($props=array()) {
    $model = new MockModel();
    foreach($props as $k => $v) {
      $model->{$k} = $v;
    }
    $model->Behaviors = new MockBehaviorCollection();
    return $model;
  }

  function _id($id) {
    return is_array($id) ? array_map(array($this, '_id'), $id) : new MongoId($id);
  }

  function startTest() {
    $this->Person = ClassRegistry::init('MongoTestPerson');
    $this->realSource =& ConnectionManager::getDataSource($this->Person->useDbConfig);
    $this->db = new MockMongoDB();
    $this->source = new MongoTestDatasource($this->db);
  }

  function testInit() {
    $this->assertIsA($this->realSource, 'MongodbSource');
  }

  function testDescribe() {
    // has no schema.
    $model = $this->_createModel(array('primaryKey' => 'id'));
    $schema = $this->source->describe($model);
    $this->assertEqual($model->primaryKey, '_id');
    $this->assertTrue(is_array($model->actsAs) && count($model->actsAs) > 0);
    $this->assertTrue(is_array($schema) && count($schema) > 0);

    // has partial (loose) schema in $mongoSchema.
    $partialSchema = array('created' => array('type' => 'datetime'),
			   'modified' => array('type' => 'datetime'));
    $model2 = $this->_createModel(array('mongoSchema' => $partialSchema));
    $schema2 = $this->source->describe($model2);
    $this->assertTrue(is_array($schema2) && count($schema2) > count($partialSchema));
    foreach($partialSchema as $col => $info) {
      $this->assertEqual($schema2[$col], $partialSchema[$col]);
    }
  }

  function testCreate() {
    $id = '123456789012345678901234';
    $fields = array('firstname', 'lastname');
    $values = array('John', 'Smith');

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MyMockMongoCollection();
    $col->setReturnValue('insert', array('ok' => 1.0));
    $col->setNextId($id);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $model->expectOnce('setInsertID', array($id));
    $col->expectOnce('insert', array(array_combine($fields, $values), true));

    // execute
    $ret = $this->source->create($model, $fields, $values);
    $this->assertTrue($ret);
    $this->assertEqual($model->id, $id);
  }

  function testUpdateThroughSave() {
    $id = '123456789012345678901234';
    $fields = array('firstname', 'lastname');
    $values = array('John', 'Smith');

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array(array('_id' => new MongoId($id)),
				     array('$set' => array_combine($fields, $values)),
				     array('multiple' => false)));

    // execute
    $ret = $this->source->update($model,
				 am(array('_id'), $fields),
				 am(array($id), $values));
    $this->assertTrue($ret);
  }

  function testUpdateThroughSaveByModelId() {
    $id = '123456789012345678901234';
    $fields = array('firstname', 'lastname');
    $values = array('John', 'Smith');

    // setup actors
    $model = $this->_createModel(array('table' => 'test', 'id' => $id));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array(array('_id' => new MongoId($id)),
				     array('$set' => array_combine($fields, $values)),
				     array('multiple' => false)));

    // execute
    $ret = $this->source->update($model, $fields, $values);
    $this->assertTrue($ret);
  }

  function testUpdateThroughUpdateAll() {
    $fields = array('firstname' => 'John', 'lastname' => 'Smith');
    $conditions = array('age' => 24);

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array($conditions,
				     array('$set' => $fields),
				     array('multiple' => true)));

    // execute
    $ret = $this->source->update($model,
				 $fields,
				 null,
				 $conditions);
    $this->assertTrue($ret);
  }

  function testUpdateThroughUpdateAllPassingTrue() {
    $fields = array('firstname' => 'John', 'lastname' => 'Smith');
    $conditions = array();

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array($conditions,
				     array('$set' => $fields),
				     array('multiple' => true)));

    // execute
    $ret = $this->source->update($model,
				 $fields,
				 null,
				 true);
    $this->assertTrue($ret);
  }

  function testUpdateThroughUpdateAllPassingFalse() {
    $fields = array('firstname' => 'John', 'lastname' => 'Smith');
    $conditions = array('$where' => 'false');

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array($conditions,
				     array('$set' => $fields),
				     array('multiple' => true)));

    // execute
    $ret = $this->source->update($model,
				 $fields,
				 null,
				 false);
    $this->assertTrue($ret);
  }

  function testUpdateThroughUpdateAllPassingEmpty() {
    $fields = array('firstname' => 'John', 'lastname' => 'Smith');
    $conditions = array();

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array($conditions,
				     array('$set' => $fields),
				     array('multiple' => true)));

    // execute
    $ret = $this->source->update($model,
				 $fields,
				 null,
				 "");
    $this->assertTrue($ret);
  }

  function testDeleteThroughRemove() {
    $id = '123456789012345678901234';

    // setup actors
    $model = $this->_createModel(array('table' => 'test', 'id' => $id));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('remove', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('remove', array(array('_id' => new MongoId($id)),
				     true));

    // execute
    $ret = $this->source->delete($model);
    $this->assertTrue($ret);
  }


  function testDeleteThroughDeleteAllNoCascade() {
    $conditions = array('active' => 0);

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('remove', true);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('remove', array($conditions, false));

    // execute
    $ret = $this->source->delete($model, $conditions);
    $this->assertTrue($ret);
  }

  function testDeleteThroughDeleteAllWithCascade() {
    $ids = $this->_id(array('123456789012345678901234',
			    '123456789012345678901235',
			    '123456789012345678901236'));

    // setup actors
    $model = $this->_createModel(array('table' => 'test',
				       'alias' => 'Test',
				       'primaryKey' => '_id'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('remove', true);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('remove', array(array('_id' => array('$in' => $ids)),
				     false));

    // execute
    $ret = $this->source->delete($model,
				 array($model->alias . '.' . $model->primaryKey => $ids));
    $this->assertTrue($ret);
  }

  /**
   * Deletes all documents in the collection.
   * This situation will not occur as long as you use Model::*,
   * but is useful in unit tests.
   */
  function testDeleteThroughDeleteAllNoConditions() {
    $conditions = null;

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('remove', true);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('remove', array(array(), false));

    // execute
    $ret = $this->source->delete($model, $conditions);
    $this->assertTrue($ret);
  }


  /**
   * test that booleans and null make logical condition strings.
   *
   * @return void
   */
  function testBooleanNullConditionsParsing() {
    $result = $this->source->conditions(true);
    $this->assertEqual($result, array());

    $result = $this->source->conditions(false);
    $this->assertEqual($result, array('$where' => 'false'));

    $result = $this->source->conditions(null);
    $this->assertEqual($result, array());
    
    $result = $this->source->conditions(array());
    $this->assertEqual($result, array());
    
    $result = $this->source->conditions('');
    $this->assertEqual($result, array());
    
    $result = $this->source->conditions(' ');
    $this->assertEqual($result, array());
  }

  function testAnyValues() {
    $result = $this->source->conditions(100);
    $this->assertEqual($result, 100);

    $result = $this->source->conditions(array('a' => 1));
    $this->assertEqual($result, array('a' => 1));

    $result = $this->source->conditions("string value");
    $this->assertEqual($result, "string value");
  }

  function testConditionsObjectizingId() {
    $model = $this->_createModel(array('alias' => 'Test'));
    $id = str_repeat('a', 24);
    $id2 = str_repeat('b', 24);

    $result = $this->source->conditions(array('a' => 100, '_id' => $id));
    $this->assertEqual($result, array('a' => 100, '_id' => new MongoId($id)));

    $result = $this->source->conditions(array('a' => 100, 'Test._id' => $id),
					$model);
    $this->assertEqual($result, array('a' => 100, '_id' => new MongoId($id)));

    // Alias._id overrides _id
    $result = $this->source->conditions(array('a' => 100, '_id' => $id, 'Test._id' => $id2),
					$model);
    $this->assertEqual($result, array('a' => 100, '_id' => new MongoId($id2)));
  }

  function testConditionsArrayId() {
    $id = str_repeat('a', 24);
    $id2 = str_repeat('b', 24);
    $id3 = str_repeat('c', 24);

    $model = $this->_createModel(array('alias' => 'Test'));

    $result = $this->source->conditions(array('a' => 100, '_id' => array($id2, $id3)),
					$model);
    $this->assertEqual($result, array('a' => 100,
				      '_id' => array('$in' => array(new MongoId($id2),
								    new MongoId($id3)))));

    $result = $this->source->conditions(array('a' => 100, 'Test._id' => array($id3, $id)),
					$model);
    $this->assertEqual($result, array('a' => 100,
				      '_id' => array('$in' => array(new MongoId($id3),
								    new MongoId($id)))));

  }


}
