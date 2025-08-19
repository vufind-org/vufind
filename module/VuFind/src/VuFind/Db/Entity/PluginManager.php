<?php

/**
 * Database entity plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
        AccessTokenEntityInterface::class => AccessToken::class,
        AuthHashEntityInterface::class => AuthHash::class,
        ChangeTrackerEntityInterface::class => ChangeTracker::class,
        CommentsEntityInterface::class => Comments::class,
        ExternalSessionEntityInterface::class => ExternalSession::class,
        FeedbackEntityInterface::class => Feedback::class,
        LoginTokenEntityInterface::class => LoginToken::class,
        OaiResumptionEntityInterface::class => OaiResumption::class,
        RatingsEntityInterface::class => Ratings::class,
        RecordEntityInterface::class => Record::class,
        ResourceEntityInterface::class => Resource::class,
        ResourceTagsEntityInterface::class => ResourceTags::class,
        SearchEntityInterface::class => Search::class,
        SessionEntityInterface::class => Session::class,
        ShortlinksEntityInterface::class => Shortlinks::class,
        TagsEntityInterface::class => Tags::class,
        UserEntityInterface::class => User::class,
        UserCardEntityInterface::class => UserCard::class,
        UserListEntityInterface::class => UserList::class,
        UserResourceEntityInterface::class => UserResource::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AccessToken::class => InvokableFactory::class,
        AuthHash::class => InvokableFactory::class,
        ChangeTracker::class => InvokableFactory::class,
        Comments::class => InvokableFactory::class,
        ExternalSession::class => InvokableFactory::class,
        Feedback::class => InvokableFactory::class,
        LoginToken::class => InvokableFactory::class,
        OaiResumption::class => InvokableFactory::class,
        Ratings::class => InvokableFactory::class,
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

    /**
     * Get aliases.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
