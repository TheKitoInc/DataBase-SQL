<?php

declare(strict_types=1);

/**
 * SQLInterface
 * SQL command and query interface
 * php version 7.1.
 *
 * @category DataBase
 *
 * @author   TheKito <TheKito@blktech.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU GPL
 *
 */

namespace Kito\DataBase\SQL;

interface SQLInterface
{
    
    /**
     * Execute query with resultset in array
     *
     * @param string $sql
     *
     * @return array
     */
    public function query(string $sql): array;
    
    /**
     * Execute command without any resultset
     *
     * @param string $sql
     *
     * @return void
     */
    public function command(string $sql): void;
}
