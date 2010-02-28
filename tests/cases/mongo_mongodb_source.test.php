<?php

require_once dirname(__FILE__) . DS . '..' . DS . 'lib' . DS . 'mongo_test_case.php';

App::import('Model', 'Mongo.MongoTestPerson');
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

class MongoMongodbSourceCase extends MongoTestCase
{
  var $Person;

  var $db;
  var $source;
  var $realSource;

  function startCase() {
    $this->_buildModelPath();
    $this->_setDebug(1);

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

  function endCase() {
    $this->_restoreDebug();
    $this->_restoreModelPath();
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

    // already object
    $objId = new MongoId($id);
    $result = $this->source->conditions(array('a' => 100, '_id' => $id),
					$model);
    $this->assertEqual($result, array('a' => 100, '_id' => $objId));
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

    // already objects
    $objId3 = new MongoId($id3);
    $result = $this->source->conditions(array('a' => 100, 'Test._id' => array($objId3, $id)),
					$model);
    $this->assertEqual($result, array('a' => 100,
				      '_id' => array('$in' => array($objId3,
								    new MongoId($id)))));
  }

  function _createCursor($rows=array()) {
    $cur = new MockMongoCursor();

    //setup as an actor
    $cur->setReturnValue('hasNext', false);

    foreach($rows as $i => $row) {
      $cur->setReturnValueAt($i, 'hasNext', true);
      $cur->setReturnValueAt($i, 'getNext', $row);
    }

    //setup as a ciritic
    $cur->expectCallCount('hasNext', count($rows) + 1);
    $cur->expectCallCount('getNext', count($rows));

    return $cur;
  }

  /**
   * This is from the Model::find()
   */
  function _makeQuery($query=array()) {
    return array_merge(
		       array(
			     'conditions' => null, 'fields' => null, 'joins' => array(), 'limit' => null,
			     'offset' => null, 'order' => null, 'page' => null, 'group' => null, 'callbacks' => true
			     ),
		       (array)$query
		       );
  }

  function testReadNoParameters() {
    $id = str_repeat('a', 24);

    $singleRow = array('_id' => new MongoId($id),
		       'age' => 20,
		       'name' => 'John Smith');


    $expectedRow = array('_id' => $id) + $singleRow;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($singleRow));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur, array(array(), array()));
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array(array(), array()));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery());

    $this->assertEqual($ret, array(array($model->alias => $expectedRow)));
  }

  function testReadSimpleConditions() {
    $id1 = str_repeat('a', 24);
    $id2 = str_repeat('b', 24);

    $conditions = array('age' => 20);

    $row1 = array('_id' => new MongoId($id1),
		  'age' => 20,
		  'name' => 'John Smith');
    $row2 = array('_id' => new MongoId($id2),
		  'age' => 20,
		  'name' => 'Paul Smith');

    $expectedRow1 = array('_id' => $id1) + $row1;
    $expectedRow2 = array('_id' => $id2) + $row2;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($row1, $row2));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array($this->source->conditions($conditions),
				   array()));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow1),
				   array($model->alias => $expectedRow2)));
  }

  function testReadWithArrayFields() {
    $id = str_repeat('a', 24);
    $conditions = array('_id' => $id,
			'age' => 20);
    $fields = array('age', 'name');

    $singleRow = array('age' => 20,
		       'name' => 'John Smith');

    $expectedRow = $singleRow;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($singleRow));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array($this->source->conditions($conditions),
				   $fields));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions', 'fields')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow)));
  }

  function testReadWithStringFields() {
    $id = str_repeat('a', 24);
    $conditions = array('_id' => $id,
			'age' => 20);
    $fields = 'age';

    $singleRow = array('age' => 20);
    $expectedRow = $singleRow;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($singleRow));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array($this->source->conditions($conditions),
				   array($fields)));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions', 'fields')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow)));
  }

  function testReadInOrder() {
    $id1 = str_repeat('a', 24);
    $id2 = str_repeat('b', 24);

    $conditions = array('age' => 20);
    $actualOrder = array('age' => 1);
    // Cake's Model::find will wrap $order with an array
    $order = array($actualOrder);

    $row1 = array('_id' => new MongoId($id1),
		  'age' => 20,
		  'name' => 'John Smith');
    $row2 = array('_id' => new MongoId($id2),
		  'age' => 20,
		  'name' => 'Paul Smith');

    $expectedRow1 = array('_id' => $id1) + $row1;
    $expectedRow2 = array('_id' => $id2) + $row2;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($row1, $row2));
    $cur->setReturnValue('sort', $cur, array($actualOrder));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $cur->expectOnce('sort', array($actualOrder));
    $col->expectOnce('find', array($this->source->conditions($conditions),
				   array()));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions', 'order')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow1),
				   array($model->alias => $expectedRow2)));
  }

  function testLimitAndOffset() {
    $query = array('limit' => 10,
		   'offset' => 100,
		   'page' => 999);

    $cur = new MockMongoCursor();
    $cur->expectOnce('limit', array($query['limit']));
    $cur->expectOnce('skip', array($query['offset']));
    $this->source->limit($cur, $query);
  }

  function testLimitAndPage() {
    $query = array('limit' => 10,
		   'offset' => null,
		   'page' => 5);

    $cur = new MockMongoCursor();
    $cur->expectOnce('limit', array($query['limit']));
    $cur->expectOnce('skip',  array(40));
    $this->source->limit($cur, $query);
  }

  function testOnlyLimit() {
    $query = array('limit' => 100,
		   'offset' => null,
		   'page' => null);

    $cur = new MockMongoCursor();
    $cur->expectOnce('limit', array($query['limit']));
    $cur->expectNever('skip');
    $this->source->limit($cur, $query);
  }

  function testOnlyOffset() {
    $query = array('limit' => null,
		   'offset' => 5000,
		   'page' => null);

    $cur = new MockMongoCursor();
    $cur->expectNever('limit');
    $cur->expectOnce('skip',  array($query['offset']));
    $this->source->limit($cur, $query);
  }

  function testOnlyPage() {
    $query = array('limit' => null,
		   'offset' => null,
		   'page' => 10);

    $cur = new MockMongoCursor();
    $cur->expectNever('limit');
    $cur->expectNever('skip');
    $this->source->limit($cur, $query);
  }

  function testReadWithLimit() {
    $id1 = str_repeat('a', 24);
    $id2 = str_repeat('b', 24);

    $conditions = array('age' => 20);
    $limit = 50;
    $offset = 100;

    $row1 = array('_id' => new MongoId($id1),
		  'age' => 20,
		  'name' => 'John Smith');
    $row2 = array('_id' => new MongoId($id2),
		  'age' => 20,
		  'name' => 'Paul Smith');

    $expectedRow1 = array('_id' => $id1) + $row1;
    $expectedRow2 = array('_id' => $id2) + $row2;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($row1, $row2));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array($this->source->conditions($conditions),
				   array()));
    $cur->expectOnce('limit', array($limit));
    $cur->expectOnce('skip', array($offset));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions',
								 'limit',
								 'offset')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow1),
				   array($model->alias => $expectedRow2)));
  }

  function testReadCount() {
    $count = 100;
    $conditions = array('age' => 20);

    //setup actors
    $cur = new MockMongoCursor();
    $cur->setReturnValue('count', $count, array());

    $model = $this->_createModel(array('table' => 'tests',
				       'alias' => 'Test',
				       'findQueryType' => 'count'));
    $model->Behaviors->setReturnValue('enabled', false);
    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur);
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //set critics
    $cur->expectOnce('count', array());
    $cur->expectNever('hasNext');
    $cur->expectNever('getNext');

    // execute
    $ret = $this->source->read($model, $this->_makeQuery(compact('conditions')));
    $expected = array(array($model->alias => array('count' => $count)));
    $this->assertEqual($ret, $expected);
  }

  function testReadObjectId() {
    $id = str_repeat('a', 24);
    $object_id = true;

    $singleRow = array('_id' => new MongoId($id),
		       'age' => 20,
		       'name' => 'John Smith');

    $expectedRow = $singleRow;

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $cur = $this->_createCursor(array($singleRow));

    $col = new MockMongoCollection();
    $col->setReturnValue('find', $cur, array(array(), array()));
    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('find', array(array(), array()));

    //execute tests
    $ret = $this->source->read($model, $this->_makeQuery(compact('object_id')));

    $this->assertEqual($ret, array(array($model->alias => $expectedRow)));
    $this->assertIsA($ret[0][$model->alias]['_id'], 'MongoId');
  }

  function testQuery()
  {
    $indexParams = array(array('x' => 1), array('unique' => true));
    $countParams = array(array('x' => array('$gt' => 3)));

    //setup actors
    $model = $this->_createModel(array('table' => 'tests', 'alias' => 'Test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('ensureIndex', true, $indexParams);
    $col->setReturnValue('count', 5, $countParams);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    //setup critics
    $col->expectOnce('ensureIndex', $indexParams);
    $col->expectOnce('count', $countParams);

    // execute tests
    $this->assertTrue($this->source->query('ensureIndex', $indexParams, $model));
    $this->assertEqual($this->source->query('count', $countParams, $model), 5);

    $this->assertNull($this->source->query('NoSuchMethod', array(), $model));
  }

  function testRawUpdate()
  {
    $strId = str_repeat('b', 24);
    $objId = new MongoId($strId);

    $fields = array('$inc' => array('age' => 1));
    $conditions = array('_id' => $strId, 'age' => 24);
    $objConditions = am($conditions, array('_id' => $objId));
    $options = array('multiple' => false,
		     'upsert' => false);

    // setup actors
    $model = $this->_createModel(array('table' => 'test'));
    $model->Behaviors->setReturnValue('enabled', false);

    $col = new MockMongoCollection();
    $col->setReturnValue('update', true);

    $this->db->setReturnValue('selectCollection', $col, array($model->table));

    // setup critics
    $col->expectOnce('update', array($objConditions,
				     $fields,
				     $options));

    // execute
    $ret = $this->source->rawUpdate($model,
				    $fields,
				    $conditions,
				    $options);
    $this->assertTrue($ret);
  }
}


class MongoTestMongodbSource_LoggerCase extends MongoTestCase
{
  function _enc()
  {
    $args = func_get_args();
    return call_user_func_array(array('MongodbSource_Logger', 'encodeArg'), $args);
  }

  function testEncodeArg()
  {
    $this->assertIdentical("1", $this->_enc(1));
    $this->assertIdentical("0", $this->_enc(0));
    $this->assertIdentical("-1", $this->_enc(-1));
    $this->assertIdentical("1.234", $this->_enc(1.234));
    $this->assertIdentical("true", $this->_enc(true));
    $this->assertIdentical("false", $this->_enc(false));
    $this->assertIdentical("NULL", $this->_enc(null));
    $this->assertIdentical("'abcdef'", $this->_enc("abcdef"));

    $this->assertIdentical("[]", $this->_enc(array()));
    $this->assertIdentical("&%", $this->_enc(array(), '&', '%'));
    $this->assertIdentical("[1,2,3]", $this->_enc(array(1, 2, 3)));
    $this->assertIdentical("{1:1,2:2,3:3}", $this->_enc(array(1 => 1, 2, 3)));
    $this->assertIdentical("[[]]", $this->_enc(array(array())));
    $this->assertIdentical("&[]%", $this->_enc(array(array()), '&', '%'));
    $this->assertIdentical("{foo:'bar'}", $this->_enc(array('foo' => 'bar'), '&', '%'));

    $strId = str_repeat('a', 24);
    $this->assertIdentical("MongoId({$strId})",
			   $this->_enc(new MongoId($strId)));

    $this->assertIdentical("{foo:'bar',baz:[1,2,NULL],bar:{opt1:true,opt2:false,opt3:MongoId({$strId})}}",
			   $this->_enc(array('foo' => 'bar',
					     'baz' => array(1,2,null),
					     'bar' => array('opt1' => true,
							    'opt2' => false,
							    'opt3' => new MongoId($strId)))));
  }

}
