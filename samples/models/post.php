<?php

class Post extends AppModel {
	var $useDbConfig = 'mongo';
	var $mongoSchema = array(
			'created'=>array('type'=>'date'),
			'modified'=>array('type'=>'date'),
			);

}

?>
