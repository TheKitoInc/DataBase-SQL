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

namespace Kito\DataBase\SQL;

use Kito\DataBase\SQL\Exception\CommandException;
use Kito\DataBase\SQL\Exception\ConnectException;
use Kito\DataBase\SQL\Exception\ConnectionClosedException;
use Kito\DataBase\SQL\Exception\DeleteException;
use Kito\DataBase\SQL\Exception\GetResultSetException;
use Kito\DataBase\SQL\Exception\InsertException;
use Kito\DataBase\SQL\Exception\SelectDBException;
use Kito\DataBase\SQL\Exception\SelectException;
use Kito\DataBase\SQL\Exception\TooManyRowsException;
use Kito\DataBase\SQL\Exception\UpdateException;
use Kito\Exception\NotImplementedException;

/**
 * @author TheKito < blankitoracing@gmail.com >
 */
class MsSQL extends Driver
{
    private $server;
    private $user;
    private $password;
    private $scheme;
    private $cnn = null;

    public function __construct(string $server, string $user, string $password, string $scheme)
    {
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->scheme = $scheme;

        $this->connect();
    }

    public function connect()
    {
        if ($this->isConnected()) {
            $this->close();
        }

        $this->cnn = @mssql_connect($this->server, $this->user, $this->password);

        if ($this->cnn === false) {
            $this->cnn = null;

            throw new ConnectException($this->server, $this->user, mssql_get_last_message(), -1);
        }

        if (!@mssql_select_db($this->scheme, $this->cnn)) {
            @mssql_close($this->cnn);
            $this->cnn = null;

            throw new SelectDBException($this->scheme, mssql_get_last_message(), -1);
        }
    }

    public function close()
    {
        if (!$this->isConnected()) {
            return;
        }

        @mssql_close($this->cnn);
        $this->cnn = null;
    }

    public function isConnected(): bool
    {
        return $this->cnn !== null;
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->close();
        }
    }

    private function sendCommand(string $sql)
    {
        if (!$this->isConnected()) {
            throw new ConnectionClosedException();
        }

        $RS = @mssql_query($sql, $this->cnn);

        if ($RS === false) {
            throw new CommandException($sql, @mssql_get_last_message(), -1);
        }

        return $RS;
    }

    public function query(string $sql): array
    {
        $RS = $this->sendCommand($sql);

        if ($RS === true) {
            throw new GetResultSetException($sql, 'No ResultSet found', -1);
        }

        $RS2 = [];
        while ($ROW = @mssql_fetch_assoc($RS)) {
            $ROW2 = [];

            foreach ($ROW as $KEY => $VALUE) {
                $ROW2[strtoupper($KEY)] = utf8_encode(trim($VALUE));
            }

            array_push($RS2, $ROW2);
        }
        @mssql_free_result($RS);

        return $RS2;
    }

    public function command(string $sql)
    {
        $RS = $this->sendCommand($sql);

        if ($RS !== true) {
            throw new CommandException($sql, 'ResultSet found', -1);
        }

        return true;
    }

    private function arrayToEqual(array $data, string $and = 'and', string $null_case = 'is null')
    {
        $t = '';
        foreach ($data as $key => $value) {
            if ($t != '') {
                $t .= " $and ";
            }

            if (strpos($key, '!') === 0) {
                $key = substr($key, 1);
                $t .= 'not ';
            }

            if ($value === null) {
                $t .= ''.$key.' '.$null_case;
            } else {
                $t .= ''.$key.'='.self::mssql_escape($value).'';
            }
        }

        return $t;
    }

    private function arrayToWhere(array $data)
    {
        $t = $this->arrayToEqual($data);
        if ($t != '') {
            return ' where '.$t;
        } else {
            return '';
        }
    }

    private static function mssql_escape($data)
    {
        if (is_numeric($data)) {
            return $data;
        } else {
            return "'".$data."'";
        }

        $unpacked = unpack('H*hex', $data);

        return '0x'.$unpacked['hex'];
    }

    private static function arrayToSelect(array $data): string
    {
        if (is_array($data) && count($data) > 0) {
            return ''.implode(',', $data).'';
        } else {
            return '*';
        }
    }

    private function arrayToInsert(array $data): string
    {
        $t0 = '';
        $t1 = '';
        foreach ($data as $key => $value) {
            if ($t0 != '') {
                $t0 .= ',';
            }

            if ($t1 != '') {
                $t1 .= ',';
            }

            $t0 .= ''.$key.'';

            if ($value === null) {
                $t1 .= 'null';
            } else {
                $t1 .= ''.self::mssql_escape($value).'';
            }
        }

        return '('.$t0.') VALUES ('.$t1.')';
    }

    public function select(string $table, array $col = [], array $where = [])
    {
        try {
            return $this->query('SELECT '.self::arrayToSelect($col).' FROM '.$table.$this->arrayToWhere($where));
        } catch (Exception $ex) {
            throw new SelectException($ex);
        }
    }

    public function delete(string $table, array $where = [])
    {
        try {
            return $this->command('DELETE FROM '.$table.$this->arrayToWhere($where));
        } catch (Exception $ex) {
            throw new DeleteException($ex);
        }
    }

    public function insert(string $table, array $data = [])
    {
        try {
            return $this->command('INSERT INTO '.$table.' '.$this->arrayToInsert($data));
        } catch (Exception $ex) {
            throw new InsertException($ex);
        }
    }

    public function update(string $table, array $data, array $where = [])
    {
        try {
            return $this->command('UPDATE '.$table.' SET '.$this->arrayToEqual($data, ',', '= null').$this->arrayToWhere($where));
        } catch (Exception $ex) {
            throw new UpdateException($ex);
        }
    }

    public function selectRow(string $table, array $col = [], array $where = [])
    {
        $RS = $this->select($table, $col, $where);

        if (count($RS) > 1) {
            throw new TooManyRowsException();
        }

        if (count($RS) == 0) {
            return null;
        }

        return $RS[0];
    }

    public static function dateNormalizer($d): int
    {
        if ($d == null) {
            return null;
        } elseif ($d instanceof DateTime) {
            return $d->getTimestamp();
        } else {
            return strtotime($d);
        }
    }

    public static function unixTime2SQL(int $time): string
    {
        if ($time === null) {
            return null;
        }

        $date = new \DateTime('now');
        $date->setTimestamp($time);

        return $date->format("Y-m-d\TH:i:s");
    }

    public function count(string $table, array $where = [])
    {
        throw new NotImplementedException();
    }

    public function getDatabase(): string
    {
        return $this->scheme;
    }

    public function getDatabases(): array
    {
        throw new NotImplementedException();
    }

    public function getTables(): array
    {
        throw new NotImplementedException();
    }

    public function max(string $table, string $column, array $where = [])
    {
        throw new NotImplementedException();
    }

    public function min(string $table, string $column, array $where = [])
    {
        throw new NotImplementedException();
    }

    public function copyTable(string $sourceTable, string $destinationTable)
    {
        return $this->command('SELECT * INTO '.$destinationTable.' FROM '.$sourceTable.' WHERE 1=0;');
    }
}
