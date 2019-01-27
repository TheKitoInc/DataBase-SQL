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

namespace BLKTech\DataBase\SQL\Driver\MySQL;
use \BLKTech\DataBase\SQL\Driver\MySQL;
use BLKTech\DataType\Integer;

/**
 *
 * @author TheKito < blankitoracing@gmail.com >
 */
 
class Dynamic 
{
    private $driver;
    function __construct(MySQL $driver) 
    {
        $this->driver = $driver;
    }
    
    
    public function delete($tablePrefix, $id) 
    {
        $id_ = Integer::unSignedInt64UnCombineIntoInt32($id);
        return $this->driver->delete($tablePrefix . $id_[0],array('id'=>$id_[1]));        
    }
    
    public function exists($tablePrefix, $id) 
    {
        $id_ = Integer::unSignedInt64UnCombineIntoInt32($id);
        return $this->driver->exists($tablePrefix . $id_[0],array('id'=>$id_[1]));   
    }

    public function get($tablePrefix, $id) 
    {
        $id_ = Integer::unSignedInt64UnCombineIntoInt32($id);
        return $this->driver->getRow($tablePrefix . $id_[0], array(), array('id'=>$id_[1]));        
    }    
    
    public function set($tablePrefix, $idHigh, $data = array()) 
    {        
        $idLow = $this->driver->autoTable($tablePrefix . $idHigh, $data, array('id'))['id'];        
        return Integer::unSignedInt32CombineIntoInt64(
                $idHigh, 
                $idLow
            );
    }    
}
