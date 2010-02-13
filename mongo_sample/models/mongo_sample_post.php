<?php

class MongoSamplePost extends MongoSampleAppModel {
	var $useDbConfig = 'mongo';
	var $mongoSchema = array(
			'created'=>array('type'=>'date'),
			'modified'=>array('type'=>'date'),
			);
}

?>
