<?php
/*
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 */

namespace BLKTech\DataBase\Driver;
use \mysqli;
use BLKTech\DataBase\Exception\ConnectException;
use BLKTech\DataBase\Exception\CommandException;
use BLKTech\DataBase\Exception\SelectException;
use BLKTech\DataBase\Exception\InsertException;
use BLKTech\DataBase\Exception\UpdateException;
use BLKTech\DataBase\Exception\DeleteException;

/**
 *
 * @author TheKito < blankitoracing@gmail.com >
 */


class MySQL extends \BLKTech\DataBase\Driver
{

    public static function getMySqlConnection($server = "127.0.0.1", $database = "test", $user = "test", $password = null)
    {
        static $CNNs = NULL;

        if($CNNs === NULL)
            $CNNs = array();

        $KEY = implode(',', array($server,$database,$user,$password));

        if(!isset($CNNs[$KEY]))
            $CNNs[$KEY] = new self($server, $database, $user, $password); 

        return $CNNs[$KEY];   
    }

    public static function getSqlConnectionServerAccount($server,$database)
    {
        return self::getMySqlConnection($server,$database, strtoupper(gethostname()), NULL);
    }

    public static function getSqlConnectionLocalHost($database,$user,$password = NULL)
    {
        return self::getMySqlConnection('127.0.0.1',$database,$user,$password);
    }    

    private $server = "127.0.0.1";
    private $database = "test";
    private $user = "test";
    private $password = null;
    private $cnn = null;
    var $__DEBUG = FALSE;

    public function getId() {
        return md5($this->server . $this->user . $this->password . $this->database);
    }

    private function __construct($server = "127.0.0.1", $database = "test", $user = "test", $password = null) {
        $this->server = $server;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;

        $this->connect();
    }

    public function isConnected() {
        if ($this->cnn === NULL)
            return false;

        return @$this->cnn->ping();
    }

    public function __destruct() {
        $this->disconnect();
    }

    public function connect() {
        if (!$this->isConnected()) {
            @$this->cnn = new mysqli($this->server, $this->user, $this->password, $this->database);

            if ($this->cnn->connect_errno > 0)
                throw new ConnectException ($this->cnn->connect_error.':'.$this->cnn->connect_errno);                

            $this->cnn->set_charset("utf8");
        }

        return true;
    }

    public function disconnect() {
        if ($this->isConnected())
            return @$this->cnn->close();

        return true;
    }

    private function doCall($sql) {
        $this->connect();

        if ($this->__DEBUG)
            echo "CALL: " . $sql . PHP_EOL;
            
        
        //echo "$sql\n";

        $rs = $this->cnn->query($sql);

        if ($rs === false)
            throw new Exception($this->cnn->error, $this->cnn->errno);

        if ($rs === true)
            return true;
        else {
            $data = array();

            while ($row = $rs->fetch_assoc())
                array_push($data, $row);

            $rs->free();

            return $data;
        }
    }

    public function query($query) {
        try {

            $t = microtime(true);
            
            $rs = $this->doCall($query);

            $t = round(microtime(true) - $t, 3);
            
            \Logger::getInstance()->debug("QUERY ($t): " . $query);

            return $rs;
        } catch (Exception $e) {
            throw new QueryException($query, $e->getMessage(), $e->getCode());
            
        }
    }

    public function command($command) {
        try {

            $t = microtime(true);
            
            $this->doCall($command);

            $t = round(microtime(true) - $t, 3);

            \Logger::getInstance()->debug("COMMAND ($t): " . $command);

            return true;
        } catch (Exception $e) {
            throw new CommandException($command, $e->getMessage(), $e->getCode());
        }
    }

    public function delete($table, $where = array(), $limit = 100) {
        try {
            return $this->command("DELETE FROM " . $table . $this->arrayToWhere($where) . self::getLimit($limit));
        } catch (Exception $ex) {
            throw new DeleteException($ex);
        }
    }

    public function insert($table, $data = array()) {
        try {
            return $this->command("INSERT INTO " . $table . " " . $this->arrayToInsert($data));
        } catch (Exception $ex) {
            throw new InsertException($ex);
        }
    }

    public function update($table, $data, $where = array(), $limit = 0) {
        try {
            return $this->command("UPDATE " . $table . " SET " . $this->arrayToEqual($data, ",", "= null") . $this->arrayToWhere($where) . self::getLimit($limit));
        } catch (Exception $ex) {
            throw new UpdateException($ex);
        }
    }

    public function select($table, $column = array(), $where = array(), $limit = 100, $rand = false) {
        try {
            if($rand)
                return $this->query("SELECT " . self::arrayToSelect($column) . " FROM " . $table . $this->arrayToWhere($where) . ' ORDER BY RAND() ' . self::getLimit($limit));
            else
                return $this->query("SELECT " . self::arrayToSelect($column) . " FROM " . $table . $this->arrayToWhere($where) . self::getLimit($limit));
        } catch (Exception $ex) {
            throw new SelectException($ex);
        }
    }

    public function count($table, $where = array()) {
        try {
            $rs = $this->query("SELECT COUNT(*) as TOTAL FROM " . $table . $this->arrayToWhere($where));
            $rs = $rs[0];
            return $rs["TOTAL"];
        } catch (Exception $ex) {
            throw new CountException($ex);
        }
    }

    public function max($table,$column, $where = array()) {
        try {
            $rs = $this->query("SELECT MAX(".$column.") as TOTAL FROM " . $table . $this->arrayToWhere($where));
            $rs = $rs[0];
            return $rs["TOTAL"];
        } catch (Exception $ex) {
            throw new MaxException($ex);
        }
    }

    public function min($table,$column, $where = array()) {
        try {
            $rs = $this->query("SELECT MIN(".$column.") as TOTAL FROM " . $table . $this->arrayToWhere($where));
            $rs = $rs[0];
            return $rs["TOTAL"];
        } catch (Exception $ex) {
            throw new MinException($ex);
        }
    }        
    protected static function getLimit($limit) {
        if (is_numeric($limit) && $limit > 0)
            return " LIMIT " . $limit . ";";
        else
            return ";";
    }

    public function getTables() {
        $tables = array();

        foreach ($this->query("SHOW TABLES;") as $ROW) {
            foreach ($ROW as $COL) {
                array_push($tables, $COL);
                break;
            }
        }

        return $tables;
    }

    public function getDatabase() {
        return $this->database;
    }

    public function getRows($table, $count) {
        static $pos = NULL;

        if ($pos === NULL)
            $pos = array();

        $hash = $table;

        if (isset($pos[$hash]))
            $start = $pos[$hash];
        else
            $start = 0;

        $pos[$hash] = $start + $count;


        $rs = $this->query("SELECT * FROM $table LIMIT $start,$count");

        if (count($rs) == 0) {
            $pos[$hash] = 0;

            if ($start > 0)
                return $this->getRows($table, $count);
        }

        return $rs;
    }

    protected function arrayToWhere($data) {
        $t = $this->arrayToEqual($data);
        if ($t != "")
            return " where " . $t;
        else
            return "";
    }

    protected  function arrayToEqual($data, $and = "and", $null_case = "is null") {
        $t = "";
        foreach ($data as $key => $value) {
            if ($t != "")
                $t.=" $and ";

            if(strpos($key, '!')===0)
            {
                $key = substr($key, 1);
                $t.='not ';
            }

            if ($value === null)
                $t.="`".$key . "` " . $null_case;
            else
                $t.="`".$key . "`='" . mysqli_real_escape_string($this->cnn,$value) . "'";
        }
        return $t;
    }

    protected static function arrayToSelect($data) {
        $t = "";
        foreach ($data as $value) {
            if ($t != "")
                $t.=",";

            $t.="`".$value."`";
        }
        if ($t != "")
            return $t;
        else
            return "*";
    }

    protected  function arrayToInsert($data) {
        $t0 = "";
        $t1 = "";
        foreach ($data as $key => $value) {
            if ($t0 != "")
                $t0.=",";

            if ($t1 != "")
                $t1.=",";

            $t0.="`".$key."`";

            if ($value === null)
                $t1.="null";
            else
                $t1.="'" . mysqli_real_escape_string($this->cnn,$value) . "'";
        }

        return "(" . $t0 . ") VALUES (" . $t1 . ")";
    }









    function insertUnique($table, $data){return $this->autoInsert($table, $data);}



    function upgradeTable($table, $data, $index){return $this->autoUpdate($table, $data, $index);}

   

    function existsRow($table,$where = array()){return $this->exists($table, $where);}

    function exists($table,$where = array())
    {
        return $this->count($table, $where)>0;
    }        

    function copyTable($sourceTable, $destinationTable) 
    {
        return $this->command('CREATE TABLE IF NOT EXISTS '.$destinationTable.' LIKE '.$sourceTable.';');
    }

    public function getDatabases()
    {
        $rs = $this->query('show databases;');
        
        foreach($rs as $index => $row)
            $rs[$index] = array_pop($row);
        
        return $rs;
    }

   

}


