<?php
/**
 * VuDL connection management class (goes through connections in priority order)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
namespace VuDL\Connection;

/**
 * VuDL connection manager
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class Manager
{
    /**
     * Array of classes to try in order
     */
    protected $priority;

    /**
     * Cache of class objects
     */
    protected $connections;

    /**
     * Used to load class objects
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param array          $priority Order we want to try connectors in
     * @param ServiceLocator $sm       Object to load everything from
     */
    public function __construct($priority, $sm)
    {
        $this->priority = $priority;
        $this->connections = [];
        $this->serviceLocator = $sm;
    }

    /**
     * Get a class object from a classname, save in cache
     *
     * @param string $className Class we want to load
     *
     * @return object
     */
    protected function get($className)
    {
        if (!isset($this->connections[$className])) {
            $this->connections[$className] = $this->serviceLocator
                ->get("VuDL\\Connection\\$className");
        }
        return $this->connections[$className];
    }

    /**
     * Try to call a function in each successive class
     * according to priority
     *
     * @param string $methodName The function we want to call
     * @param array  $params     The params to pass to the func
     *
     * @return mixed
     */
    public function __call($methodName, $params)
    {
        $index = 0;
        while ($index < count($this->priority)) {
            $object = $this->get($this->priority[$index]);
            if (method_exists($object, $methodName)) {
                $ret = call_user_func_array([$object, $methodName], $params);
                if (!is_null($ret)) {
                    //var_dump($methodName.' - '.$this->priority[$index]);
                    return $ret;
                }
            }
            $index ++;
        }
        throw new \Exception(
            'VuDL Connection Failed to resolved method "' . $methodName . '"'
        );
    }
}