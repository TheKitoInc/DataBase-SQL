<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace BLKTech\DataBase\SQL\Driver\MySQL;
use \BLKTech\DataBase\SQL\Driver\MySQL;
/**
 * Description of Dynamic
 *
 * @author instalacion
 */
class Dynamic 
{
    private $driver;
    function __construct(MySQL $driver) 
    {
        $this->driver = $driver;
    }

}
