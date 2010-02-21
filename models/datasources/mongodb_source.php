<?php
/**
* A CakePHP datasource for the mongoDB (http://www.mongodb.org/) document-oriented database.
*
* This datasource uses Pecl Mongo (http://php.net/mongo)
* and is thus dependent on PHP 5.0 and greater.
*
* Original implementation by ichikaway(Yasushi Ichikawa) http://github.com/ichikaway/
*
* Reference:
*	Nate Abele's lithium mongoDB datasource (http://li3.rad-dev.org/)
*	Joél Perras' divan(http://github.com/jperras/divan/)
*
* Copyright 2010, Yasushi Ichikawa http://github.com/ichikaway/
*
* Licensed under The MIT License
* Redistributions of files must retain the above copyright notice.
*
* @copyright Copyright 2010, Yasushi Ichikawa http://github.com/ichikaway/
* @package mongodb
* @subpackage mongodb.models.datasources
* @license http://www.opensource.org/licenses/mit-license.php The MIT License
*/

/**
 * MongoDB Source
 *
 * @package mongodb
 * @subpackage mongodb.models.datasources
 */
class MongodbSource extends DataSource {

/**
 * Database Instance
 *
 * @var resource
 * @access protected
 */
	protected $_db = null;

	protected $_logger;

/**
 * MongoCollection cache
 * 
 * @var array
 */
	protected $_collections = array();

/**
 * Base Config
 *
 * @var array
 * @access protected
 */
	var $_baseConfig = array(
		'persistent' => false,
		'host'       => 'localhost',
		'database'   => '',
		'port'       => '27017'
	);


/**
 * column definition
 *
 * @var array
 */
    var $columns = array(
        'string' => array('name'  => 'varchar'),
        'text' => array('name' => 'text'),
        'integer' => array('name' => 'integer', 'formatter' => 'intval'),
        'float' => array('name' => 'float', 'formatter' => 'floatval'),
        'datetime' => array('name' => 'datetime', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
        'timestamp' => array('name' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
        'time' => array('name' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
        'date' => array('name' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
    );

/**
 * Default schema for the mongo models
 *
 * @var array
 * @access protected
 */
	protected $_defaultSchema = array('_id' => array('type' => 'string', 'length' => 24),
						'_schemaless_data' => array('type' => 'schemaless'));

/**
 * Behavior automatically attached to the mongo models
 * 
 * @var string
 * @access protected
 */
	protected $_sourceBehavior = 'MongoDocument';

/**
 * Constructor
 *
 * @param array $config Configuration array
 * @access public
 */
	public function __construct($config = array()) {
		// loaded as a plugin in CakePHP 1.3
		if(strpos($config['datasource'], '.') !== false) {
			list($plugin, $_source) = explode('.', $config['datasource'], 2);
			$this->_sourceBehavior = "{$plugin}.{$this->_sourceBehavior}";
		}

		$this->fullDebug = Configure::read('debug') > 1;
		if($this->fullDebug) {
		  $this->_logger = new MongodbSource_Logger($this);
		}

		parent::__construct($config);
		$this->connect();
	}


/**
 * Connect to the database
 *
 * @return boolean Connected
 * @access public
 */
	public function connect() {
		$this->connected = false;
		$host = $this->config['host'] . ':' . $this->config['port'];
		$this->connection = new Mongo($host, true, $this->config['persistent']);
		if ($this->_db = $this->connection->selectDB($this->config['database'])) {
			$this->connected = true;
		}
		return $this->connected;
	}

/**
 * check connection to the database
 *
 * @return boolean Connected
 * @access public
 */
	public function isConnected() {
		return $this->connected;
	}



/**
 * Close database connection
 *
 * @return boolean Connected
 * @access public
 */
	public function close() {
		if($this->fullDebug) {
		  $this->_logger->showLog();
		}
		return $this->disconnect();
	}	

/**
 * Disconnect from the database
 *
 * @return boolean Connected
 * @access public
 */
	public function disconnect() {
		if ($this->connected) {
			$this->connected = !$this->connection->close();
			unset($this->_db, $this->connection);
			return !$this->connected;
		}
		return true;
	}

/**
 * Get list of available Collections
 *
 * @param array $data 
 * @return array Collections
 * @access public
 */
	public function listSources($data = null) {
		return true;
		/*
		$list = $this->_db->listCollections();
		if (empty($list)) {
			return array();
		} else {
			$collections = null;		
			foreach($this->_db->listCollections() as $collection) {
				$collections[] = $collection->getName();
			}
			return $collections;
		}
		*/
	}

/**
 * Describe
 *
 * @param Model $model 
 * @return array if model instance has mongoSchema, return it.
 * @access public
 */
	public function describe(&$model) {
		$model->primaryKey = '_id';
		$this->_setSourceBehavior($model);
		$schema = isset($model->mongoSchema) && is_array($model->mongoSchema)
		  ? $model->mongoSchema : array();
		return $schema + $this->_defaultSchema;
	}

/**
 * Attaches $this->_sourceBehavior to the model
 *
 * @param Model $model 
 * @access protected
 */
	function _setSourceBehavior(&$model)
	{
		if(empty($model->actsAs)) {
			$model->actsAs = array();
		}
		if(!isset($model->actsAs[$this->_sourceBehavior]) &&
			 !in_array($this->_sourceBehavior, $model->actsAs)) {
			$model->actsAs[$this->_sourceBehavior] = array();
		}
	}

/**
 * Calculate
 *
 * @param Model $model 
 * @return array
 * @access public
 */
	public function calculate (&$model) {
		return array('count' => true);
	}

/**
 * Quotes identifiers.
 *
 * MongoDb does not need identifiers quoted, so this method simply returns the identifier.
 *
 * @param string $name The identifier to quote.
 * @return string The quoted identifier.
 */
	public function name($name) {
		return $name;
	}

/**
 * Retrieves data from $this->_sourceBehavior.
 *
 * @param Model $model
 * @return array  data to save
 */
	function _dataToSave(&$model, $fields=null, $values=null)
	{
		if ($fields !== null && $values !== null) {
			$data = array_combine($fields, $values);
		} else {
			$data = $model->data;
		}
		return $model->Behaviors->enabled($this->_sourceBehavior)
			? $model->getSchemalessData($data) : $data;
	}

/**
 * Creates condition array. 
 *
 * @param mixed $conditions Array or string of conditions, or any value.
 * @param Model $model A reference to the Model instance making the query
 * @return array or any value
 */
	public function conditions($conditions, $model = null) {
	  $ret = array();

	  if($conditions === false) {
	    $ret = array('$where' => 'false');
	  } elseif($conditions === true || empty($conditions) ||
		   (is_string($conditions) && trim($conditions) == '')) {
	    $ret = array();
	  } else {
	    $ret = $conditions;
	  }

	  if(!is_array($ret)) {
	    return $ret;
	  }

	  $id = null;
	  if(!empty($ret['_id'])) {
	    $id = $ret['_id'];
	  }
	  if(!empty($model) && !empty($ret[$model->alias.'._id'])) {
	    $id = $ret[$model->alias.'._id'];
	    unset($ret[$model->alias.'._id']);
	  }

	  if(!empty($id)) {
	    if(is_array($id)) {
	      $ids = array();
	      foreach($id as $_id) {
		$ids[] = is_string($_id) ? new MongoId($_id) : $_id;
	      }
	      $ret['_id'] = array('$in' => $ids);
	    } else {
	      $ret['_id'] = is_string($id) ? new MongoId($id) : $id;
	    }
	  }
	  return $ret;
	}

	protected function _idCondition($id) {
	  return array('_id' => new MongoId($id));
	}

	protected function _getCollection($model) {
	  if(isset($this->_collections[$model->table])) {
	    return $this->_collections[$model->table];
	  }
	  $coll = $this->_db->selectCollection($model->table);
	  return $this->_collections[$model->table] =
	    $this->fullDebug ? $this->_logger->createProxy($coll) : $coll;
	}

/**
 * Create Data
 *
 * @param Model $model Model Instance
 * @param array $fields Field data
 * @param array $values Save data
 * @return boolean Insert result
 * @access public
 */
	public function create(&$model, $fields = null, $values = null) {
		$data = $this->_dataToSave($model, $fields, $values);
		$result = $this->_getCollection($model)->insert($data, true);

		if ($result['ok'] === 1.0) {
			$id = is_object($data['_id']) ? $data['_id']->__toString() : null;
			$model->setInsertID($id);
			$model->id = $id;
			return true;
		}
		return false;
	}


/**
 * Update Data
 *
 * @param Model $model Model Instance
 * @param array $fields Field data
 * @param array $values Save data
 * @return boolean Update result
 * @access public
 */
	public function update(&$model, $fields = null, $values = null, $conditions = null) {
		$mongoCollectionObj = $this->_getCollection($model);

		//updateAll
		if($values === null) {
		  return $mongoCollectionObj->update($this->conditions($conditions, $model),
						     array('$set' => $fields),
						     array("multiple" => true));
		}

		$data = $this->_dataToSave($model, $fields, $values);

		$cond = null;
		if(!empty($model->id)) {
		  $cond = $this->_idCondition($model->id);
		}
		if (!empty($data['_id'])) {
		  $cond = $this->_idCondition($data['_id']);
		  unset($data['_id']);
		}

		if (!empty($cond)) {
		  return $mongoCollectionObj->update($cond,
						     array('$set' => $data),
						     array("multiple" => false));
		} else {
		  return false;
		}
	}



/**
 * Delete Data
 *
 * @param Model $model Model Instance
 * @param array $conditions
 * @return boolean Update result
 * @access public
 */
	public function delete(&$model, $conditions = null) {
	  $mongoCollectionObj = $this->_getCollection($model);

	  if($conditions === null && !empty($model->id)) {
	    return $mongoCollectionObj->remove($this->_idCondition($model->id), true);
	  }
	  return $mongoCollectionObj->remove($this->conditions($conditions, $model), false);
	}



	protected function _stringifyId($arr) {
	  if(!empty($arr['_id']) && is_object($arr['_id'])) {
	    $arr['_id'] = $arr['_id']->__toString();
	  }
	  return $arr;
	}



	public function limit($cur, $query=array()) {
	  // ignoring 0
	  if(!empty($query['limit'])) {
	    $cur->limit($query['limit']);
	  }
	  if(!empty($query['offset'])) {
	    $cur->skip($query['offset']);
	  } elseif(!empty($query['page']) && !empty($query['limit'])) {
	    $cur->skip(($query['page'] - 1) * $query['limit']);
	  }
	}


/**
 * Read Data
 *
 * @param Model $model Model Instance
 * @param array $query Query data
 * @return array Results
 * @access public
 */
	public function read(&$model, $query = array()) {
	  foreach(array('fields', 'order') as $k) {
	    $query[$k] = empty($query[$k]) ? array() : (array)$query[$k];
	  }
	  extract($query);

	  $cur = $this->_getCollection($model)
	    ->find($this->conditions($conditions, $model), $fields);
	  if(!empty($order[0])) {
	    $cur->sort($order[0]);
	  }
	  $this->limit($cur, $query);

	  if ($model->findQueryType === 'count') {
	    return array(array($model->alias => array('count' => $cur->count())));
	  }

	  $ret = array();
	  while($cur->hasNext()) {
	    $ret[][$model->alias] = empty($object_id) ?
	      $this->_stringifyId($cur->getNext()) : $cur->getNext();
	  }
	  return $ret;
	}


/**
 * Executes MongoCollection methods.
 *
 * @param string $method
 * @param array  $params
 * @param Model  $model
 * @return mixed
 */
	public function query($method, $params, $model = null) {
	  $coll = $this->_getCollection($model);

	  if(is_callable(array($coll, $method))) {
	    return call_user_func_array(array($coll, $method), $params);
	  }
	  return null;
	}

}


/**
 * MongoDB Source Logger
 *
 * @package mongodb
 * @subpackage mongodb.models.datasources.mongodb_source
 */
class MongodbSource_Logger
{
  protected $_source;
  protected $_log = array();
  protected $_proxyClass = array('MongoCollection' => 'MongodbSource_Tracer',
				 'MongoCursor' => 'MongodbSource_Tracer_MongoCursor');

  public function __construct($source) {
    $this->_source = $source;
  }

  public function trace() {
    $this->_log[] = func_get_args();
  }

  public function createProxy($target) {
    if(is_object($target)) {
      $class = get_class($target);
      if(isset($this->_proxyClass[$class])) {
	return new $this->_proxyClass[$class]($this, $target);
      }
    }
    return $target;
  }

  public function stringifyArg($arg, $pl="[", $pr="]") {
    switch(true) {
    case is_scalar($arg):
      return var_export($arg, true);
    case $arg === array():
      return $pl . $pr;
    case is_array($arg) && array_keys($arg) === range(0, count($arg)-1):
      return $pl. join(",", array_map(array($this, __FUNCTION__), $arg)) . $pr;
    case is_a($arg, 'MongoId'):
      return "MongoId(". $arg->__toString() .")";
    default:
      $pairs = array();
      foreach((array)$arg as $k => $v) {
	$pairs[] = $k .":". $this->stringifyArg($v);
      }
      return "{". join(",", $pairs) ."}";
    }
  }

  public function showLog() {
    if (PHP_SAPI != 'cli') {
      $tableId = "cakeRequestLog_" . preg_replace('/[^A-Za-z0-9_]/', '_', uniqid(time(), true));
      printf('<table class="cake-sql-log" id="%s" cellspacing="0" border= "0">', h($tableId));
      printf('<caption>(%s) %s method call(s)</caption>', h($this->_source->configKeyName), h(count($this->_log)));
      print ("<thead>\n<tr><th>Nr</th><th>Method</th><th>Took (ms)</th></tr>\n</thead>\n<tbody>\n");
      foreach($this->_log as $i => $r) {
	$class = str_replace('Mongo', '', get_class($r[0]));
	printf('<tr><td>%d</td><td>%s</td><td>%s</td></tr>',
	       $i+1, h($class ."->". $r[1] . $this->stringifyArg($r[2], "(", ")")), h($r[3]));
      }
      print ("</tbody></table>\n");
    } else {
      foreach($this->_log as $i => $r) {
	$class = str_replace('Mongo', '', get_class($r[0]));
	printf("%d %s\n", $i+1, $class ."->". $r[1] . $this->stringifyArg($r[2], "(", ")"));
      }
    }
  }
}

/**
 * MongoDB Source Method Tracer
 *
 * @package mongodb
 * @subpackage mongodb.models.datasources.mongodb_source
 */
class MongodbSource_Tracer
{
  protected $_logger, $_target, $_dontLog = array();

  public function __construct($logger, $target) {
    $this->_logger = $logger;
    $this->_target = $target;
  }

  public function __call($method, $args) {
    if(!method_exists($this->_target, $method)) {
      throw new Exception('undefined method ' . $method);
    }
    $t = getMicrotime();
    $ret = $this->_logger->
      createProxy(call_user_func_array(array($this->_target, $method), $args));
    $took = round((getMicrotime() - $t) * 1000, 0);

    if(!isset($this->_dontLog[$method])) {
      $this->_logger->trace($this->_target, $method, $args, $took);
    }
    return $ret;
  }
}

/**
 * MongoDB Source Method Tracer for MongoCursor
 *
 * @package mongodb
 * @subpackage mongodb.models.datasources.mongodb_source
 */
class MongodbSource_Tracer_MongoCursor extends MongodbSource_Tracer
{
  protected $_dontLog = array('hasNext' => true, 'getNext' => true);
}

?>
