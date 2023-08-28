<?php

/**
 * Database service plugin manager
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

namespace VuFind\Db\Service;

/**
 * Database service plugin manager
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
        'changetracker' => ChangeTrackerService::class,
        'comments' => CommentsService::class,
        'feedback' => FeedbackService::class,
        'oairesumption' => OaiResumptionService::class,
        'ratings' => RatingsService::class,
        'record' => RecordService::class,
        'resource' => ResourceService::class,
        'session' => SessionService::class,
        'shortlinks' => ShortlinksService::class,
        'tag' => TagService::class,
        'user' => UserService::class,
        'usercard' => UserCardService::class,
        'userresource' => UserResourceService::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        ChangeTrackerService::class => AbstractServiceFactory::class,
        CommentsService::class => AbstractServiceFactory::class,
        FeedbackService::class => AbstractServiceFactory::class,
        OaiResumptionService::class => AbstractServiceFactory::class,
        RatingsService::class => AbstractServiceFactory::class,
        RecordService::class => AbstractServiceFactory::class,
        ResourceService::class => ResourceServiceFactory::class,
        SessionService::class => AbstractServiceFactory::class,
        ShortlinksService::class => AbstractServiceFactory::class,
        TagService::class => TagServiceFactory::class,
        UserService::class => UserServiceFactory::class,
        UserCardService::class => AbstractServiceFactory::class,
        UserResourceService::class => AbstractServiceFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return AbstractService::class;
    }
}
