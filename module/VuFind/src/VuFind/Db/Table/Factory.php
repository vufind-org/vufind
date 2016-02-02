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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Db\Table;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for DB tables.
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Resource table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Resource
     */
    public static function getResource(ServiceManager $sm)
    {
        return new Resource($sm->getServiceLocator()->get('VuFind\DateConverter'));
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
        // Use a special row class when we're in privacy mode:
        $privacy = isset($config->Authentication->privacy)
            && $config->Authentication->privacy;
        $rowClass = 'VuFind\Db\Row\\' . ($privacy ? 'PrivateUser' : 'User');
        $session = null;
        if ($privacy) {
            $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            $session = new \Zend\Session\Container('Account', $sessionManager);
        }
        return new User($config, $rowClass, $session);
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
        return new UserList($session);
    }
}