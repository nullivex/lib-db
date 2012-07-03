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

	static $inst = false;

	private $config;
	private $pdo;
	private $connected = false;
	private $query_count = 0;

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
				$this->config['driver'].':dbname='.$this->config['database'].
				';host='.$this->config['host'].';port='.$this->config['port'],
				$this->config['user'],
				$this->config['password'],
				array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
			);
			$this->connected = true;
		}
		catch(PDOException $error){
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

	public function __call($function_name, $parameters) {
		if(!is_array($parameters)) $parameters = array();
		return call_user_func_array(array($this->pdo, $function_name), $parameters);
	}

}
