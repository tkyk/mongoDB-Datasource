# CakePHP Mongo Plugin

This plugin provides basic interfaces to [MongoDB](http://www.mongodb.org/display/DOCS/Home).

## Requirements

-  PHP 5
-  CakePHP 1.2/1.3
-  [PECL mongo](http://pecl.php.net/package/mongo)


## How to install

### in CakePHP 1.3

    cd plugins/
    git clone git://github.com/tkyk/mongoDB-Datasource.git mongo

The `datasource` parameter should be `Mongo.MongodbSource`.

    class DATABASE_CONFIG {
      var $mongo = array('datasource' => 'Mongo.MongodbSource',
                         'database' => 'your_dbname',
                         /* See the documentations for more details */);
    }

### in CakePHP 1.2

You are not allowed to use DataSources provided by plugins.

Copy mongodb_source.php to your app/models/datasources or make a symbolic link to it.

    cd plugins/
    git clone git://github.com/tkyk/mongoDB-Datasource.git mongo
    cp mongo/models/datasources/mongodb_source.php YOUR_APP/models/datasources/

The `datasource` parameter should be `mongodb`.

    class DATABASE_CONFIG {
      var $mongo = array('datasource' => 'mongodb',
                         'database' => 'your_dbname',
                         /* See the documentations for more details */);
    }

## Documentations

- [CakePHP Mongo Plugin documentation](http://wiki.github.com/tkyk/mongoDB-Datasource/)

- [pecl mongo documentation](http://php.net/mongo)

- [MongoDB documentation](http://www.mongodb.org/display/DOCS/Home)


## Authors

This is originally developed by Yasushi Ichikawa (ichikaway).

-  [http://github.com/ichikaway/mongoDB-Datasource](http://github.com/ichikaway/mongoDB-Datasource)
-  [http://twitter.com/ichikaway](http://twitter.com/ichikaway)
-  [http://cake.eizoku.com/blog/](http://cake.eizoku.com/blog/)

And forked by Takayuki Miwa.

-  [http://github.com/tkyk/mongoDB-Datasource](http://github.com/tkyk/mongoDB-Datasource)
-  [http://twitter.com/tkykmw](http://twitter.com/tkykmw)
-  [http://wp.serpere.info](http://wp.serpere.info)


## Contributors

[Predominant](http://github.com/predominant/) : Cleanup code, add documentation

[Jrbasso](http://github.com/jrbasso/) : Cleanup code
