
<?php echo $html->link('Add data', 'add'); ?>
<br>
<br>
<?php foreach($results as $result): ?>
<?php   $post = $result['MongoSamplePost']; ?>
	id: <?php echo $post['_id']; ?>
	[<?php echo $html->link('edit','edit/'.$post['_id']); ?>] [<?php echo $html->link('delete','delete/'.$post['_id']); ?>]<br>
	title: <?php echo $post['title']; ?><br>
	body: <?php echo $post['body']; ?><br>
	hoge: <?php echo $post['hoge']; ?><br>

<hr>
<?php endforeach; ?>

