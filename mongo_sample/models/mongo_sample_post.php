<?php

class MongoSamplePost extends MongoSampleAppModel {
	var $useDbConfig = 'mongo_sample';
	var $mongoSchema = array(
			'created'=>array('type'=>'date'),
			'modified'=>array('type'=>'date'),
			);
}

?>
