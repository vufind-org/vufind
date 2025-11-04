<?php

/**
 * Reservation list view helper
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\ReservationList\Handler\HandlerInterface;
use Finna\ReservationList\ReservationListService;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\RecordDriver\DefaultRecord;

/**
 * Reservation list view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ReservationList extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * User logged in or null
     *
     * @var UserEntityInterface|null
     */
    protected ?UserEntityInterface $user;

    /**
     * Constructor
     *
     * @param ReservationListService $reservationListService Reservation list service
     * @param ILSAuthenticator       $ilsAuthenticator       Authenticator to ILS
     * @param array                  $reservationListConfig  Reservation list configuration
     * @param array                  $configSection          Reservation list section from config.ini
     */
    public function __construct(
        protected ReservationListService $reservationListService,
        protected ILSAuthenticator $ilsAuthenticator,
        protected array $reservationListConfig = [],
        protected array $configSection = []
    ) {
    }

    /**
     * Invoke
     *
     * @param ?UserEntityInterface $user User currently logged in or null
     *
     * @return self
     */
    public function __invoke(?UserEntityInterface $user = null): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get list properties defined by institution and list identifier in ReservationList.yaml,
     * institution specified information and
     * formed translation_keys for the list.
     *
     * Institution information contains keys and values:
     *     - name => Example institution name
     *     - address => Example institution address
     *     - postal => Example institution postal
     *     - city => Example institution city
     *     - email => Example institution email
     *
     * Translation keys formed:
     *     - title => list_title_{$institution}_{$listIdentifier},
     *     - description => list_description_{$institution}_{$listIdentifier},
     *
     * @param string $institution    Lists controlling institution
     * @param string $listIdentifier List identifier
     *
     * @return HandlerInterface
     */
    public function getListHandler(
        string $institution,
        string $listIdentifier
    ): HandlerInterface {
        return $this->reservationListService->getListHandler($institution, $listIdentifier);
    }

    /**
     * Display buttons which routes the request to proper list procedures
     * Checks if the list should be displayed for logged-in only users.
     *
     * @param DefaultRecord $driver Driver to use for checking available lists
     *
     * @return string
     */
    public function renderReserveTemplate(DefaultRecord $driver): string
    {
        if (!$this->isFunctionalityEnabled()) {
            return '';
        }
        // Collect lists where we could potentially save this:
        $listHandlers = $this->getAvailableListsForRecord($driver);

        // Set up the needed context in the view:
        $view = $this->getView();
        return $view->render('Helpers/reservationlist-reserve.phtml', compact('listHandlers', 'driver'));
    }

    /**
     * Get associative array of [institution => configured lists] where driver matches
     *
     * @param DefaultRecord $driver Record driver
     *
     * @return array
     */
    public function getAvailableListsForRecord(DefaultRecord $driver): array
    {
        return $this->reservationListService->getAvailableListsForRecord($driver);
    }

    /**
     * Get available reservation lists for user, user must be invoked
     *
     * @return FinnaResourceListEntityInterface[]
     */
    public function getReservationListsForUser(): array
    {
        if (!$this->isFunctionalityEnabled()) {
            return [];
        }
        return $this->reservationListService->getReservationListsForUser($this->user);
    }

    /**
     * Get lists containing record
     *
     * @param DefaultRecord $record Record
     *
     * @return FinnaResourceListEntityInterface[]
     */
    public function getListsContainingRecord(DefaultRecord $record): array
    {
        if (!$this->isFunctionalityEnabled() || !$this->user) {
            return [];
        }
        return $this->reservationListService->getListsContainingRecord($this->user, $record);
    }

    /**
     * Check if reservation lists are enabled
     *
     * @return bool
     */
    public function isFunctionalityEnabled(): bool
    {
        return !empty($this->configSection['enabled']);
    }

    /**
     * Check if single order is possible
     *
     * @return bool
     */
    public function singleOrderEnabled(): bool
    {
        return $this->reservationListService->singleOrderEnabled();
    }
}
