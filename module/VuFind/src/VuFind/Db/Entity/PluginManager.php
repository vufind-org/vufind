<?php
/**
 * Database entity plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace VuFind\Db\Entity;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Database entity plugin manager
 *
 * @category VuFind
 * @package  Database
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
        'authhash' => AuthHash::class,
        'changetracker' => ChangeTracker::class,
        'comments' => Comments::class,
        'externalsession' => ExternalSession::class,
        'feedback' => Feedback::class,
        'oairesumption' => OaiResumption::class,
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
        AuthHash::class => InvokableFactory::class,
        ChangeTracker::class => InvokableFactory::class,
        Comments::class => InvokableFactory::class,
        ExternalSession::class => InvokableFactory::class,
        Feedback::class => InvokableFactory::class,
        OaiResumption::class => InvokableFactory::class,
        Record::class => InvokableFactory::class,
        Resource::class => InvokableFactory::class,
        ResourceTags::class => InvokableFactory::class,
        Search::class => InvokableFactory::class,
        Session::class => InvokableFactory::class,
        Shortlinks::class => InvokableFactory::class,
        Tags::class => InvokableFactory::class,
        User::class => InvokableFactory::class,
        UserCard::class => InvokableFactory::class,
        UserList::class => InvokableFactory::class,
        UserResource::class => InvokableFactory::class,
    ];

    /**
     * We do not want to create shared instances of database entities; build a new
     * one every time!
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return EntityInterface::class;
    }
}
