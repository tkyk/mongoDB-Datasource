<?php
/**
 * MongoDocumentBehavior
 * 
 * This behavior provides some mongo specific methods for Models,
 * and will be automatically attached to the models within MongodbSource.
 * 
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 * Copyright (c) 2010 Takayuki Miwa http://github.com/tkyk/
 *
 * @copyright Copyright (c) 2010 Takayuki Miwa http://github.com/tkyk/
 * @package mongodb
 * @subpackage mongodb.models.behaviors
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * MongoDocument Behavior
 *
 * @package mongodb
 * @subpackage mongodb.models.behaviors
 */
class MongoDocumentBehavior extends ModelBehavior
{
/**
 * @var string	name of schemaless column
 */
	var $_schemalessColumn = '_schemaless_data';

/**
 * @var string	type of schemaless column.
 */
	var $_schemalessType = 'schemaless';

/**
 * @var array	default settings
 */
	var $_defaultSettings = array();

/**
 * setup callback
 */
	function setup(&$model, $settings=array())
	{
		$settings = array_merge($this->_defaultSettings,
					$settings);

		$this->settings[$model->alias] = $settings;
	}

/**
 * If the model has schemaless column, returns its name.
 * 
 * @param $model
 * @return string or null
 */
	function getSchemalessField(&$model)
	{
		if($model->getColumnType($this->_schemalessColumn)
			 == $this->_schemalessType) {
			return $this->_schemalessColumn;
		}
		return null;
	}

/**
 * beforeSave callback
 * 
 * Removes values undefined in the $_schema from $data
 * and then stores them into $data[$schemalessField].
 * $whiltelist can be used to determine which values are stored.
 * 
 * @param $model
 * @param $options array
 * @return boolean
 */
	function beforeSave(&$model, $options)
	{
		if(!($schemalessField = $model->getSchemalessField())) {
			return true;
		}

		$keys = array_diff(array_keys($model->data[$model->alias]),
					 array_keys($model->schema()));
		if(!empty($model->whitelist)) {
			if(!in_array($schemalessField, $model->whitelist)) {
				$model->whitelist[] = $schemalessField;
			}
			$keys = array_intersect($keys, $model->whitelist);
		}

		$schemaless = array();
		foreach($keys as $key) {
			$schemaless[$key] = $model->data[$model->alias][$key];
			unset($model->data[$model->alias][$key]);
		}
		$model->data[$model->alias][$schemalessField] = $schemaless;

		return true;
	}

/**
 * This method is called from the DataSources.
 * 
 * Extracts values from $data[$schemalessField] and merges them with $data.
 * 
 * @param $model
 * @param $data array
 */
	function getSchemalessData(&$model, $data)
	{
		if(($schemalessField = $model->getSchemalessField()) &&
			isset($data[$schemalessField])) {
			$schemaless = $data[$schemalessField];
			unset($data[$schemalessField]);
			return $data + $schemaless;
		}
		return $data;
	}
}
