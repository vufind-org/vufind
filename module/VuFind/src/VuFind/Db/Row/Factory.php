<?php
/**
 * Factory for DB rows.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Db\Row;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for DB tables.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct a generic row object.
     *
     * @param string         $name Name of row to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm   Service manager
     * @param array          $args Extra constructor arguments for row object
     *
     * @return object
     */
    public static function getGenericRow($name, ServiceManager $sm, $args = [])
    {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        $adapter = $sm->getServiceLocator()->get('VuFind\DbAdapter');
        return new $class($adapter, ...$args);
    }

    /**
     * Default factory behavior.
     *
     * @param string $name Method name being called
     * @param array  $args Method arguments
     *
     * @return object
     */
    public static function __callStatic($name, $args)
    {
        // Strip "get" off method name, and use the remainder as the table name;
        // grab the first argument to pass through as the service manager.
        return static::getGenericRow(substr($name, 3), array_shift($args));
    }

    /**
     * Construct the User row prototype.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return User
     */
    public static function getUser(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $privacy = isset($config->Authentication->privacy)
            && $config->Authentication->privacy;
        $rowClass = $privacy ? 'PrivateUser' : 'User';
        $prototype = static::getGenericRow($rowClass, $sm);
        $prototype->setConfig($config);
        if ($privacy) {
            $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            $session = new \Zend\Session\Container('Account', $sessionManager);
            $prototype->setSession($session);
        }
        return $prototype;
    }

    /**
     * Construct the UserList row prototype.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserList
     */
    public static function getUserList(ServiceManager $sm)
    {
        $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
        $session = new \Zend\Session\Container('List', $sessionManager);
        return static::getGenericRow('UserList', $sm, [$session]);
    }
}
