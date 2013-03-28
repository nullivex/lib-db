openlss/lib-db
====

PDO wrapper library with helpers

The Db class tracks query counts and can debug queries.
It will auto-generate SQL for insert/update queries.

Usage
====

```php
ld('db');

//connect
Db::_get()->setConfig($dbconfig)->connect();

//execute a fetch
$result = Db::_get()->fetch('SELECT * FROM `table` WHERE `col` = ?',array($col));
```

Reference
====

Call to PDO
----
Any functions not shown in the reference are passed directly to PDO

Singleton Information
----
Db can be and is recommended to be used as a singleton to reuse the same PDO instance.

If multiple connections are needed use a custom method of maintaining the instances.

### ($this) Db::setConfig($config)
Sets the config of the database system.

Takes an array with the following structure
```php
$config = array(
	 'driver'		=>	'mysql'
	,'database'		=>	'database_name'
	,'host'			=>	'server_host'
	,'port'			=>	'server_port'
	,'user'			=>	'username'
	,'password'		=>	'password'
);
```

### ($this) Db::connect()
Will use the current configuration and connect

### (int) Db::getQueryCount()
Returns the current query count

### (bool) Db::close()
Close the open PDO istance (if any)

### (array) Db::prepWhere($pairs,$type='WHERE')
Prepares WHERE strings to be used in queries
  * $pairs	array of clauses which can be in 4 formats
   * 'field-name'	=>	array($bool='AND',$operator='=',$value)
   * 'field-name'	=>	array($operator='=',$value) //bool defaults to AND
   * 'field-name'	=>	array($operator) //bool defaults to AND, value defaults to NULL
   * 'field-name'	=>	$value //bool defaults to AND, operator defaults to =
   * NOTE: use Db::IS_NULL and Db::IS_NOT_NULL for null value operators
  * $type	specify the start of the string defaults to 'WHERE'
  * returns an array, with members:
   * [0] <string> the resulting WHERE clause; compiled for use with PDO::prepare including leading space (ready-to-use)
   * [n] <array>  the values array; ready for use with PDO::execute

### (int) Db::insert($table,$params=array(),$update_if_exists=false)
Insert into a table with given parameters

When $update_if_exists is set to TRUE it will perform an INSERT OR UPDATE query.

### (bool) Db::update($table,$keys=array(),$params=array())
Updates a record in the database
  * $table	The table to be updates
  * $keys	Pairs compatible with prepWhere
  * $params	Array of name=>value pairs to update with

### (result) Db::fetch($stmt,$params=array(),$throw_exception=Db::NO_EXCEPTIONS,$except_code=null,$flatten=Db::NO_FLATTEN)
Fetches a single row from a query and returns the result
  * $stmt				The SQL query
  * $params				Parameters to be bound to the query
  * $throw_exception	When set to Db::EXCEPTIONS will throw an exception on result not found
  * $except_cde			Code to be throw with the exception
  * $flatten			When set to Db::FLATTEN will return an array of values from a specific column

### (array result) Db::fetchAll($stmt,$params=array(),$throw_exception=Db::NO_EXCEPTIONS,$except_code=null,$flatten=Db::NO_FLATTEN)
Same as fetch but returns all results in an array
