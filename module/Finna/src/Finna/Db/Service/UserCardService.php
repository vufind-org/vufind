<?php

/**
 * Database service for UserCard.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Closure;
use Doctrine\ORM\EntityManager;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\UserCardEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;

use function in_array;

/**
 * Database service for UserCard.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserCardService extends \VuFind\Db\Service\UserCardService
{
    /**
     * Constructor
     *
     * @param EntityManager       $entityManager          Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager    VuFind entity plugin manager
     * @param PersistenceManager  $persistenceManager     Entity persistence manager
     * @param ILSAuthenticator    $ilsAuthenticator       ILS authenticator
     * @param AccountCapabilities $capabilities           Account capabilities configuration
     * @param Closure             $getLoginTargetPrefixes Callback for getting a list of active login target prefixes
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        ILSAuthenticator $ilsAuthenticator,
        AccountCapabilities $capabilities,
        protected Closure $getLoginTargetPrefixes
    ) {
        parent::__construct(
            $entityManager,
            $entityPluginManager,
            $persistenceManager,
            $ilsAuthenticator,
            $capabilities
        );
    }

    /**
     * Get all library cards associated with the user.
     *
     * @param UserEntityInterface|int $userOrId    User object or identifier
     * @param ?int                    $id          Optional card ID filter
     * @param ?string                 $catUsername Optional catalog username filter
     *
     * @return UserCardEntityInterface[]
     */
    public function getLibraryCards(
        UserEntityInterface|int $userOrId,
        ?int $id = null,
        ?string $catUsername = null
    ): array {
        $cards = parent::getLibraryCards($userOrId, $id, $catUsername);
        // Filter cards by active login targets unless a specific subset of cards was requested:
        if ($cards && null === $id && null === $catUsername) {
            $prefixes = ($this->getLoginTargetPrefixes)();
            $cards = array_filter(
                $cards,
                function ($card) use ($prefixes) {
                    [$catPrefix] = explode('.', $card->getCatUsername());
                    return in_array("$catPrefix.", $prefixes);
                }
            );
        }
        return $cards;
    }
}
