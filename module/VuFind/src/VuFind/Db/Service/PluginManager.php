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

use VuFind\Auth\UserSessionPersistenceInterface;

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
        AccessTokenServiceInterface::class => AccessTokenService::class,
        AuthHashServiceInterface::class => AuthHashService::class,
        ChangeTrackerServiceInterface::class => ChangeTrackerService::class,
        CommentsServiceInterface::class => CommentsService::class,
        ExternalSessionServiceInterface::class => ExternalSessionService::class,
        FeedbackServiceInterface::class => FeedbackService::class,
        LoginTokenServiceInterface::class => LoginTokenService::class,
        OaiResumptionServiceInterface::class => OaiResumptionService::class,
        RatingsServiceInterface::class => RatingsService::class,
        RecordServiceInterface::class => RecordService::class,
        ResourceServiceInterface::class => ResourceService::class,
        ResourceTagsServiceInterface::class => ResourceTagsService::class,
        SearchServiceInterface::class => SearchService::class,
        SessionServiceInterface::class => SessionService::class,
        ShortlinksServiceInterface::class => ShortlinksService::class,
        TagServiceInterface::class => TagService::class,
        UserCardServiceInterface::class => UserCardService::class,
        UserListServiceInterface::class => UserListService::class,
        UserResourceServiceInterface::class => UserResourceService::class,
        UserServiceInterface::class => UserService::class,
        UserSessionPersistenceInterface::class => UserService::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AccessTokenService::class => AccessTokenServiceFactory::class,
        AuthHashService::class => AbstractDbServiceFactory::class,
        ChangeTrackerService::class => AbstractDbServiceFactory::class,
        CommentsService::class => AbstractDbServiceFactory::class,
        ExternalSessionService::class => AbstractDbServiceFactory::class,
        FeedbackService::class => AbstractDbServiceFactory::class,
        LoginTokenService::class => AbstractDbServiceFactory::class,
        OaiResumptionService::class => AbstractDbServiceFactory::class,
        RatingsService::class => AbstractDbServiceFactory::class,
        RecordService::class => AbstractDbServiceFactory::class,
        ResourceService::class => ResourceServiceFactory::class,
        ResourceTagsService::class => AbstractDbServiceFactory::class,
        SearchService::class => AbstractDbServiceFactory::class,
        SessionService::class => AbstractDbServiceFactory::class,
        ShortlinksService::class => AbstractDbServiceFactory::class,
        TagService::class => AbstractDbServiceFactory::class,
        UserCardService::class => UserCardServiceFactory::class,
        UserListService::class => AbstractDbServiceFactory::class,
        UserResourceService::class => AbstractDbServiceFactory::class,
        UserService::class => UserServiceFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return DbServiceInterface::class;
    }
}
