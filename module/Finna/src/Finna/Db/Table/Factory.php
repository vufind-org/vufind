<?php
/**
 * Factory for DB tables.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Db\Table;

use Zend\Console\Console;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for DB tables.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Db\Table\Factory
{
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
        $className = "\\Finna\\Db\\Table\\$name";
        if (!class_exists($className)) {
            $className = $name;
        }
        return parent::getGenericTable(
            $className, $sm, $rowName, $args
        );
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
        // For user anonymization console utility
        if (Console::isConsole()) {
            $session = new \Zend\Session\Container('List');
        } else {
            $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            $session = new \Zend\Session\Container('List', $sessionManager);
        }
        return static::getGenericTable('UserList', $sm, 'userlist', [$session]);
    }
}
