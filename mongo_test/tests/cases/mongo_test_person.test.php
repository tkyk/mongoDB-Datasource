<?php

App::import('Model', 'MongoTest.MongoTestPerson');

class MongoTestPersonCase extends CakeTestCase
{
  var $Person;

  function start() {
    $this->Person = ClassRegistry::init('MongoTestPerson');
  }

  function testInit() {
    $this->assertIsA($this->Person, 'MongoTestPerson');
    $this->assertTrue($this->Person->Behaviors->enabled('MongoDocument'));
  }

}
