<?php

class MongoTestCase extends CakeTestCase
{
  private $__prevDebug;

  protected function _setDebug($debug) {
    $this->__prevDebug = Configure::read('debug');
    Configure::write('debug', $debug);
  }

  protected function _restoreDebug() {
    Configure::write('debug', $this->__prevDebug);
  }


  protected function _isCake12() {
    return version_compare(Configure::version(), '1.3.0-beta', '<');
  }

  protected function _buildModelPath() {
    $testApp = realpath(dirname(__FILE__) . DS . ".." . DS . "test_app");
    $modelPaths = array($testApp . DS . 'models' . DS);

    if($this->_isCake12()) {
      Configure::write('modelPaths', $modelPaths);
    } else {
      App::build(array('models' => $modelPaths));
    }
  }

  protected function _restoreModelPath() {
    if($this->_isCake12()) {
      Configure::write('modelPaths', array());
    } else {
      App::build();
    }
  }

}
