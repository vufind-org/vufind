<?php
/**
 * Table Definition for user_list
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;
use VuFind\Db\Table\PluginManager;
use Zend\Db\Adapter\Adapter;

/**
 * Table Definition for user_list
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserList extends \VuFind\Db\Table\UserList
{
    /**
     * Constructor
     *
     * @param Adapter                 $adapter Database adapter
     * @param PluginManager           $tm      Table manager
     * @param array                   $cfg     Zend Framework configuration
     * @param \Zend\Session\Container $session Session container (must use same
     * namespace as container provided to \VuFind\View\Helper\Root\UserList).
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        \Zend\Session\Container $session
    ) {
        parent::__construct($adapter, $tm, $cfg, $session);
        $resultSetPrototype = $this->getResultSetPrototype();
        $resultSetPrototype->setArrayObjectPrototype(
            $this->initializeRowPrototype('Finna\Db\Row\UserList')
        );
    }

    /**
     * Retrieve user's list object by title.
     *
     * @param int    $userId User id
     * @param string $title  Title of the list to retrieve
     *
     * @return \Finna\Db\Row\UserList|false User list row or false if not found
     */
    public function getByTitle($userId, $title)
    {
        if (!is_numeric($userId)) {
            return false;
        }

        $callback = function ($select) use ($userId, $title) {
            $select->where->equalTo('user_id', $userId)->equalTo('title', $title);
        };
        return $this->select($callback)->current();
    }
}
