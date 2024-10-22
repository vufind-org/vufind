<?php

/**
 * Database table plugin manager
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Table;

/**
 * Database table plugin manager
 *
 * @category VuFind
 * @package  Db_Table
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
        'authhash' => AuthHash::class,
        'changetracker' => ChangeTracker::class,
        'comments' => Comments::class,
        'externalsession' => ExternalSession::class,
        'feedback' => Feedback::class,
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
        AccessToken::class => GatewayFactory::class,
        AuthHash::class => GatewayFactory::class,
        ChangeTracker::class => GatewayFactory::class,
        Comments::class => GatewayFactory::class,
        ExternalSession::class => GatewayFactory::class,
        Feedback::class => GatewayFactory::class,
        LoginToken::class => GatewayFactory::class,
        OaiResumption::class => GatewayFactory::class,
        Ratings::class => GatewayFactory::class,
        Record::class => GatewayFactory::class,
        Resource::class => ResourceFactory::class,
        ResourceTags::class => CaseSensitiveTagsFactory::class,
        Search::class => GatewayFactory::class,
        Session::class => GatewayFactory::class,
        Shortlinks::class => GatewayFactory::class,
        Tags::class => CaseSensitiveTagsFactory::class,
        User::class => UserFactory::class,
        UserCard::class => GatewayFactory::class,
        UserList::class => UserListFactory::class,
        UserResource::class => GatewayFactory::class,
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return Gateway::class;
    }
}
