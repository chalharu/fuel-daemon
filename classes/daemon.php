<?php
/**
 * Daemon
 *
 * @package	fuel-daemon
 * @version	0.1
 * @author	chalharu
 * @license	MIT License
 * @copyright	Copyright 2012, chalharu
 * @link	http://chrysolite.hatenablog.com/
 */

namespace Daemon;

/**
 * Daemon class
 */
class Daemon
{
    public static function _init()
    {
        
    }

    public static function forge()
    {
        static::_init();

        if(!extension_loaded('pcntl')){
            throw new \FuelException();
		}
        if(!extension_loaded('posix')){
            throw new \FuelException();
		}
        //Windowsの場合はwin32serviceを利用予定

        if(php_sapi_name()!='cli'){
            throw new \FuelException();
        }

        $driver = new Daemon_Driver;
        return $driver;
    }
}