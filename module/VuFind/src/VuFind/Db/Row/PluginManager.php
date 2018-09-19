<?php
/**
 * Database row plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace VuFind\Db\Row;

/**
 * Database row plugin manager
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'changetracker' => 'VuFind\Db\Row\ChangeTracker',
        'comments' => 'VuFind\Db\Row\Comments',
        'externalsession' => 'VuFind\Db\Row\ExternalSession',
        'oairesumption' => 'VuFind\Db\Row\OaiResumption',
        'record' => 'VuFind\Db\Row\Record',
        'resource' => 'VuFind\Db\Row\Resource',
        'resourcetags' => 'VuFind\Db\Row\ResourceTags',
        'search' => 'VuFind\Db\Row\Search',
        'session' => 'VuFind\Db\Row\Session',
        'tags' => 'VuFind\Db\Row\Tags',
        'user' => 'VuFind\Db\Row\User',
        'usercard' => 'VuFind\Db\Row\UserCard',
        'userlist' => 'VuFind\Db\Row\UserList',
        'userresource' => 'VuFind\Db\Row\UserResource',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Db\Row\ChangeTracker' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Comments' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\ExternalSession' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\OaiResumption' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Record' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Resource' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\ResourceTags' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Search' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Session' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\Tags' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\User' => 'VuFind\Db\Row\UserFactory',
        'VuFind\Db\Row\UserCard' => 'VuFind\Db\Row\RowGatewayFactory',
        'VuFind\Db\Row\UserList' => 'VuFind\Db\Row\UserListFactory',
        'VuFind\Db\Row\UserResource' => 'VuFind\Db\Row\RowGatewayFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Db\Row\RowGateway';
    }
}
