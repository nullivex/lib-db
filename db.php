<?php
/*
 * LSS Core
 * OpenLSS - Light, sturdy, stupid simple
 * 2010 Nullivex LLC, All Rights Reserved.
 * Bryan Tong <contact@nullivex.com>
 *
 *   OpenLSS is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   OpenLSS is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with OpenLSS.  If not, see <http://www.gnu.org/licenses/>.
 */

class Db {

	const EXCEPTIONS = false;
	const NO_EXCEPTIONS = false;
	const FLATTEN = true;
	const NO_FLATTEN = false;

	static $inst = false;

	private $config;
	private $pdo;
	private $connected = false;
	private $query_count = 0;
	
	public $debug = false;

	public static function _get(){
		if(self::$inst == false) self::$inst = new Db();
		return self::$inst;
	}

	public function setConfig($config){
		$this->config = $config;
		return $this;
	}

	public function connect(){
		try{
			$this->pdo = new PDO(
				sprintf(
					'%s:dbname=%s;host=%s;port=%i',
					$this->config['driver'],
					$this->config['database'],
					$this->config['host'],
					$this->config['port']
				)
				,$this->config['user']
				,$this->config['password']
				,array(
					 PDO::ATTR_ERRMODE				=>	PDO::ERRMODE_EXCEPTION
					,PDO::ATTR_DEFAULT_FETCH_MODE	=>	PDO::FETCH_ASSOC
				)
			);
			$this->connected = true;
		} catch(PDOException $error){
			$this->connected = false;
			throw new Exception("Database Connection Failed: ".$error->getMessage());
		}
		return $this;
	}

	public function exec($statement){
		$this->query_count++;
		return $this->pdo->exec($statement);
	}

	public function prepare($statement,$driver_options=array()){
		$this->query_count++;
		return $this->pdo->prepare($statement,$driver_options);
	}

	public function query($statement){
		$this->query_count++;
		return $this->pdo->query($statement);
	}

	public function getQueryCount(){
		return $this->query_count;
	}

	public function close(){
		static $inst = false;
	}

	// $pairs is an assoc array of [column_name]=>[value] [ex: array('age'=>21,'email'=>'jdoe@example.org')]
	// $bool  can be either: (string) valid SQL WHERE clause boolean comparator [default: 'AND']
	//                       (array)  array of strings like the above, to be used in order [ex: array('OR','AND','AND NOT')]
	// returns an array, with members:
	//     [0] <string> the resulting WHERE clause; compiled for use with PDO::prepare including leading space (ready-to-use)
	//     [n] <array>  the values array; ready for use with PDO::execute
	public static function prepwhere($pairs=array(),$bool='AND',$type='WHERE'){
		if(!count($pairs)) return array('',null);
		
		//pad bools
		if(is_array($bool)) foreach($bool as &$b) $b = '=? '.$b.' ';
		else $bool = '=? '.$bool.' ';
		//fill bools if not enough
		if(is_array($bool) && ($ca = count($pairs) - ($cb = count($bool))) > 0){
			$bool = array_merge($bool,array_fill($cb+1,$ca,$bool[($cb-1)]));
			unset($ca,$cb);
		}
		//prepare clause
		$clause = sprintf(' %s %s=?',$type,implodei($bool,self::escape(array_keys($pairs))));
		return array_merge(array($clause),array_values($pairs));
		
		/*
		//this is the old way
		$cols = $vals = array();
		foreach($pairs as $col=>$val){
			$cols[] = self::escape($col).'=?';
			// $cols[] = '`'.implode('`.`',explode('.',$col)).'`=?';
			$vals[] = $val;
		}
		if(is_string($bool))
			array_unshift($vals,' WHERE '.implode(' '.$bool.' ',$cols));
		else if(is_array($bool)){
			$clause = ' WHERE';
			$boolptr = 0;
			$maxptr = count($cols) - 1;
			for($c = 0; $c < $maxptr; $c++){
				$clause .= ' '.$col.' '.$bool[$boolptr];
				if(++$boolptr > $maxptr) $boolptr = $maxptr;
			}
			$clause .= ' '.$cols[$maxptr];
			array_unshift($clause);
		}
		return $vals;
		*/
	}
	
	public function run($stmt,$params=array()){
		if($this->debug) debug_dump($stmt,$params);
		$query = $this->prepare($stmt);
		$query->execute($params);
		return $query;
	}

	public function insert($table,$params=array(),$update_if_exists=false){
		if($update_if_exists) return $this->insertOrUpdate($table,$params);
		$stmt = sprintf(
			'INSERT INTO `%s` (%s) VALUES (%s)'
			,$table
			,implodei(',',self::escape(array_keys($params)))
			,rtrim(str_repeat('?,',count($params)),',')
		);
		$this->run($stmt,array_values($params));
		return $this->lastInsertId();
	}
	
	protected function insertOrUpdate($table,$params=array()){
		$stmt = sprintf(
			'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s'
			,$table
			,implodei(',',self::escape(array_keys($params)))
			,rtrim(str_repeat('?,',count($params)),',')
			,implodei('=?,',self::escape(array_keys($params)))
		);
		$this->run($stmt,array_merge(array_values($params),array_values($params)));
		return $this->lastInsertId();
	}

	public function update($table,$primary_key,$primary_key_value=null,$params=array()){
		if(is_array($primary_key)){
			$key_stmt = implodei('=? AND ',self::escape(array_keys($primary_key)));
			$params = $primary_key_value;
		} else {
			$key_stmt = self::escape($primary_key).' =?';
		}
		if(!count($params)) throw new Exception('No data provided for update to: '.$table);
		$stmt = sprintf(
			'UPDATE `%s` SET %s WHERE %s'
			,$table
			,implodei('=?, ',self::escape(array_keys($params))).'=?'
			,$key_stmt
		);
		if(!is_array($primary_key)) $params[] = $primary_key_value;
		else $params = array_merge($params,array_values($primary_key));
		return $this->run($stmt,array_values($params));
	}

	public function fetch($stmt,$params=array(),$throw_exception=false,$except_code=null,$flatten=false){
		if(is_array($stmt)) list($stmt,$params) = $stmt;
		$query = $this->run($stmt,$params);
		$result = $query->fetch();
		$query->closeCursor();
		if(((!is_array($result)) || (count($result)==0)) && $throw_exception !== false)
			throw new Exception($throw_exception,$except_code);
		if($flatten && is_array($result) && (count($result)>0) && (count(array_keys($result)) == 1)){
			$col = array_shift(array_keys($result));
			$result = $result[$col];
		}
		return $result;
	}

	public function fetchAll($stmt,$params=array(),$throw_exception=false,$except_code=null,$flatten=false){
		if(is_array($stmt)) list($stmt,$params) = $stmt;
		$query = $this->run($stmt,$params);
		$result = $query->fetchAll();
		if(!$result && $throw_exception !== false) throw new Exception($throw_exception,$except_code);
		if($flatten && is_array($result) && (count($result)>0) && is_array($result[0]) && (count(array_keys($result[0])) == 1)){
			$col = array_shift(array_keys($result[0]));
			$arr = array();
			foreach($result as $row) $arr[] = $row[$col];
			$result = $arr;
		}
		return $result;
	}

	public function __call($function_name, $parameters) {
		if(!is_array($parameters)) $parameters = array();
		return call_user_func_array(array($this->pdo, $function_name), $parameters);
	}
	
	public static function escape($arr=array()){
		if(!is_array($arr)) return '`'.$arr.'`';
		foreach($arr as &$f){
			//join parts of an array into escaped fields
			if(is_array($f)) $f = '`'.implode('`.`',$f).'`';
			//escape fields and blow up periods
			else $f = '`'.implode('`.`',explode('.',$f)).'`';
		}
		return $arr;
	}

}
