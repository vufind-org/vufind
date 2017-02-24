<?php
/**
 * Factory for DB tables.
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
namespace VuFind\Db\Table;
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
     * Return row prototype object (null if unavailable)
     *
     * @param ServiceManager $sm   Service manager
     * @param string         $name Name of row prototype to retrieve
     *
     * @return object
     */
    public static function getRowPrototype(ServiceManager $sm, $name)
    {
        if ($name) {
            $rowManager = $sm->getServiceLocator()->get('VuFind\DbRowPluginManager');
            return $rowManager->has($name) ? $rowManager->get($name) : null;
        }
        return null;
    }

    /**
     * Construct a generic table object.
     *
     * @param string         $name    Name of table to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm      Service manager
     * @param string         $rowName Name of custom row prototype object to
     * retrieve (null for none).
     * @param array          $args    Extra constructor arguments for table object
     *
     * @return object
     */
    public static function getGenericTable($name, ServiceManager $sm,
        $rowName = null, $args = []
    ) {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        $adapter = $sm->getServiceLocator()->get('VuFind\DbAdapter');
        $config = $sm->getServiceLocator()->get('config');
        return new $class(
            $adapter, $sm, $config, static::getRowPrototype($sm, $rowName), ...$args
        );
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
        $dbName = substr($name, 3);
        return static::getGenericTable(
            $dbName, array_shift($args), strtolower($dbName)
        );
    }

    /**
     * Construct the Resource table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Resource
     */
    public static function getResource(ServiceManager $sm)
    {
        $converter = $sm->getServiceLocator()->get('VuFind\DateConverter');
        $loader = $sm->getServiceLocator()->get('VuFind\RecordLoader');
        return static::getGenericTable(
            'Resource', $sm, 'resource', [$converter, $loader]
        );
    }

    /**
     * Construct the ResourceTags table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return User
     */
    public static function getResourceTags(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $caseSensitive = isset($config->Social->case_sensitive_tags)
            && $config->Social->case_sensitive_tags;
        return static::getGenericTable(
            'ResourceTags', $sm, 'resourcetags', [$caseSensitive]
        );
    }

    /**
     * Construct the Tags table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return User
     */
    public static function getTags(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $caseSensitive = isset($config->Social->case_sensitive_tags)
            && $config->Social->case_sensitive_tags;
        return static::getGenericTable('Tags', $sm, 'tags', [$caseSensitive]);
    }

    /**
     * Construct the User table.
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
        $session = null;
        if ($privacy) {
            $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            $session = new \Zend\Session\Container('Account', $sessionManager);
        }
        return static::getGenericTable('User', $sm, 'user', [$config, $session]);
    }

    /**
     * Construct the UserList table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserList
     */
    public static function getUserList(ServiceManager $sm)
    {
        $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
        $session = new \Zend\Session\Container('List', $sessionManager);
        return static::getGenericTable('UserList', $sm, 'userlist', [$session]);
    }
}
