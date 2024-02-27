<?php

/**
 * Database row plugin manager
 *
 * PHP version 8
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
        'accesstoken' => AccessToken::class,
        'changetracker' => ChangeTracker::class,
        'comments' => Comments::class,
        'externalsession' => ExternalSession::class,
        'logintoken' => LoginToken::class,
        'oairesumption' => OaiResumption::class,
        'ratings' => Ratings::class,
        'record' => Record::class,
        'resource' => Resource::class,
        'resourcetags' => ResourceTags::class,
        'search' => Search::class,
        'session' => Session::class,
        'shortlinks' => Shortlinks::class,
        'tags' => Tags::class,
        'user' => User::class,
        'usercard' => UserCard::class,
        'userlist' => UserList::class,
        'userresource' => UserResource::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AccessToken::class => RowGatewayFactory::class,
        AuthHash::class => RowGatewayFactory::class,
        ChangeTracker::class => RowGatewayFactory::class,
        Comments::class => RowGatewayFactory::class,
        ExternalSession::class => RowGatewayFactory::class,
        Feedback::class => RowGatewayFactory::class,
        LoginToken::class => RowGatewayFactory::class,
        OaiResumption::class => RowGatewayFactory::class,
        Ratings::class => RowGatewayFactory::class,
        Record::class => RowGatewayFactory::class,
        Resource::class => RowGatewayFactory::class,
        ResourceTags::class => RowGatewayFactory::class,
        Search::class => RowGatewayFactory::class,
        Session::class => RowGatewayFactory::class,
        Shortlinks::class => RowGatewayFactory::class,
        Tags::class => RowGatewayFactory::class,
        User::class => UserFactory::class,
        UserCard::class => RowGatewayFactory::class,
        UserList::class => UserListFactory::class,
        UserResource::class => RowGatewayFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return RowGateway::class;
    }
}
