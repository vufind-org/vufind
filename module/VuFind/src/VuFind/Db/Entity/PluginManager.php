<?php

/**
 * Database entity plugin manager.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use Laminas\ServiceManager\Factory\InvokableFactory;
use VuFind\ServiceManager\AbstractPluginFactory;

/**
 * Database entity plugin manager.
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
        ApiKeyEntityInterface::class => ApiKey::class,
        AuthHashEntityInterface::class => AuthHash::class,
        ChangeTrackerEntityInterface::class => ChangeTracker::class,
        CommentsEntityInterface::class => Comments::class,
        AuditEventEntityInterface::class => AuditEvent::class,
        ExternalSessionEntityInterface::class => ExternalSession::class,
        FeedbackEntityInterface::class => Feedback::class,
        LoginTokenEntityInterface::class => LoginToken::class,
        OaiResumptionEntityInterface::class => OaiResumption::class,
        PaymentEntityInterface::class => Payment::class,
        PaymentFeeEntityInterface::class => PaymentFee::class,
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

    /**
     * We do not want to create shared instances of database entities; build a new
     * one every time!
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * Constructor.
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
        $this->addAbstractFactory(AbstractPluginFactory::class);
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
