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

use Kito\DataBase\SQL\Exception\InsertException;
use Kito\DataBase\SQL\Exception\TooManyRowsException;

/**
 * @author TheKito < blankitoracing@gmail.com >
 */
abstract class Driver
{
    abstract public function isConnected(): bool;

    abstract public function query(string $query): array;

    abstract public function command(string $command);

    abstract public function delete(string $table, array $where = [], int $limit = 100);

    abstract public function insert(string $table, array $data = []);

    abstract public function update(string $table, array $data, array $where = [], int $limit = 0);

    abstract public function select(string $table, array $column = [], array $where = [], int $limit = 100, bool $rand = false);

    abstract public function count(string $table, array $where = []);

    abstract public function max(string $table, array $column, array $where = []);

    abstract public function min(string $table, array $column, array $where = []);

    abstract public function getTables(): array;

    abstract public function getDatabases(): array;

    abstract public function getDatabase(): string;

    abstract public function copyTable(string $sourceTable, string $destinationTable);

    final public function getArray(string $table, string $column, array $where = []): array
    {
        $r = [];

        foreach ($this->select($table, [$column], $where) as $ROW) {
            array_push($r, $ROW[$column]);
        }

        return $r;
    }

    final public function getHashMap(string $table, string $columnKey, string $columnValue, array $where = []): array
    {
        $r = [];

        foreach ($this->select($table, [$columnKey, $columnValue], $where) as $ROW) {
            $r[$ROW[$columnKey]] = $ROW[$columnValue];
        }

        return $r;
    }

    final public function getRow(string $table, array $column = [], array $where = []): ?array
    {
        $RS = $this->select($table, $column, $where, 2);

        if (count($RS) > 1) {
            throw new TooManyRowsException();
        }

        if (count($RS) == 0) {
            return null;
        }

        return $RS[0];
    }

    final public function getText(string $table, string $column, array $where = []): string
    {
        $ROW = $this->getRow($table, [$column], $where);

        if ($ROW == null) {
            return null;
        }

        return $ROW[$column];
    }

    final public function autoTable(string $table, array $data, array $column = [], bool $create = true): ?array
    {
        $rs = $this->select($table, $column, $data, 1);

        if (count($rs) > 0) {
            return $rs[0];
        } elseif ($create) {
            if ($this->insert($table, $data)) {
                $rs = $this->select($table, $column, $data, 1);

                if (count($rs) > 0) {
                    return $rs[0];
                } else {
                    throw new InsertException(print_r([$table, $data], true));
                }
            } else {
                throw new InsertException(print_r([$table, $data], true));
            }
        } else {
            return null;
        }
    }

    final public function autoUpdate(string $table, array $data, array $index): int
    {
        $UPDATES = 0;

        $ROW = $this->autoTable($table, $index);

        foreach ($ROW as $KEY => $VALUE) {
            unset($ROW[$KEY]);
            $ROW[strtolower($KEY)] = $VALUE;
        }

        foreach ($data as $KEY => $VALUE) {
            unset($data[$KEY]);
            $data[strtolower($KEY)] = $VALUE;
        }

        foreach ($ROW as $KEY => $VALUE) {
            if (array_key_exists($KEY, $data) && $VALUE != $data[$KEY]) {
                $this->update($table, [$KEY => $data[$KEY]], $index, 1);
                $UPDATES++;
            }
        }

        return $UPDATES;
    }

    final public function autoInsert(string $table, array $data): bool
    {
        $rs = $this->select($table, [], $data, 1);

        if (count($rs) > 0) {
            return true;
        }

        if ($this->insert($table, $data)) {
            return true;
        }

        return false;
    }

    public function getTablesWithPrefix(string $prefix): array
    {
        $prefixLen = strlen($prefix);

        $_ = [];

        foreach ($this->getTables() as $table) {
            if (substr($table, 0, $prefixLen) == $prefix) {
                $_[] = $table;
            }
        }

        return $_;
    }
}
