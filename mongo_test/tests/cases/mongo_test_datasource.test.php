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


}
