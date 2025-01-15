<?php

/**
 * Reservation list service
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  ReservationList
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\ReservationList;

use DateTime;
use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\Db\Service\FinnaResourceListResourceServiceInterface;
use Finna\Db\Service\FinnaResourceListServiceInterface;
use Laminas\Session\Container;
use Laminas\Stdlib\Parameters;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Cache as RecordCache;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\DefaultRecord;

use function is_string;

/**
 * Reservation list service
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ReservationListService implements TranslatorAwareInterface, DbServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Type of resource list
     *
     * @var string
     */
    public const RESOURCE_LIST_TYPE = 'reservationlist';

    /**
     * Default connection handler used for list connections
     *
     * @var string
     */
    public const DEFAULT_CONNECTION_HANDLER = 'email';

    /**
     * Default values for list config
     *
     * @var array
     */
    protected const RESERVATION_LIST_DEFAULT_VALUES  = [
        'Enabled' => false,
        'Recipient' => [],
        'Datasources' => [],
        'Information' => [],
        'LibraryCardSources' => [],
        'Forms' => [
            'PlaceOrder' => 'default',
        ],
        'Connection' =>  [
            'type' => self::DEFAULT_CONNECTION_HANDLER,
        ],
        'Identifier' => false,
    ];

    /**
     * Constructor
     *
     * @param FinnaResourceListServiceInterface         $resourceListService         Resource list database service
     * @param FinnaResourceListResourceServiceInterface $resourceListResourceService Resource and list relation
     *                                                                               database service
     * @param ResourceServiceInterface                  $resourceService             Resource database service
     * @param UserServiceInterface                      $userService                 User database service
     * @param ResourcePopulator                         $resourcePopulator           Resource populator service
     * @param RecordLoader                              $recordLoader                Record loader
     * @param ?RecordCache                              $recordCache                 Record cache (optional)
     * @param ?Container                                $session                     Session container for remembering
     *                                                                               state (optional)
     * @param array                                     $reservationListConfig       Reservation list configuration
     */
    public function __construct(
        protected FinnaResourceListServiceInterface $resourceListService,
        protected FinnaResourceListResourceServiceInterface $resourceListResourceService,
        protected ResourceServiceInterface $resourceService,
        protected UserServiceInterface $userService,
        protected ResourcePopulator $resourcePopulator,
        protected RecordLoader $recordLoader,
        protected ?RecordCache $recordCache = null,
        protected ?Container $session = null,
        protected array $reservationListConfig = []
    ) {
    }

    /**
     * Create a new list object for the specified user
     *
     * @param ?UserEntityInterface $user Logged in user (null if logged out)
     *
     * @return FinnaResourceListEntityInterface
     * @throws LoginRequiredException
     */
    public function createListForUser(?UserEntityInterface $user): FinnaResourceListEntityInterface
    {
        if (!$user) {
            throw new LoginRequiredException('Log in to create lists.');
        }

        return $this->resourceListService->createEntity()
            ->setUser($user)
            ->setCreated(new DateTime());
    }

    /**
     * Destroy a list.
     *
     * @param FinnaResourceListEntityInterface $list  List to destroy
     * @param ?UserEntityInterface             $user  Logged-in user (null if none)
     * @param bool                             $force Should we force the delete without checking permissions?
     *
     * @return void
     * @throws ListPermissionException
     */
    public function destroyList(
        FinnaResourceListEntityInterface $list,
        ?UserEntityInterface $user = null,
        bool $force = false
    ): void {
        if (!$force && !$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }
        $listUser = $list->getUser();
        $this->resourceListResourceService->unlinkResources(null, $listUser, $list);
        $this->resourceListService->deleteResourceList($list);
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @param FinnaResourceListEntityInterface $list List to remember
     *
     * @return void
     */
    public function rememberLastUsedList(FinnaResourceListEntityInterface $list): void
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $list->getId();
        }
    }

    /**
     * Get a list object for the specified ID.
     *
     * @param int                 $listId List ID
     * @param UserEntityInterface $user   The user saving the record
     *
     * @return FinnaResourceListEntityInterface
     *
     * @throws \VuFind\Exception\ListPermission
     */
    public function getAndRememberListObject(int $listId, UserEntityInterface $user): FinnaResourceListEntityInterface
    {
        $list = $this->resourceListService->getResourceListById($listId);
        // Validate incoming list ID:
        if (!$this->userCanEditList($user, $list)) {
            throw new \VuFind\Exception\ListPermission('Access denied.');
        }
        $this->rememberLastUsedList($list); // handled by saveListForUser() in other case
        return $list;
    }

    /**
     * Persist a resource to the record cache (if applicable).
     *
     * @param RecordDriver            $driver   Record driver to persist
     * @param ResourceEntityInterface $resource Resource row
     *
     * @return void
     */
    protected function persistToCache(
        RecordDriver $driver,
        ResourceEntityInterface $resource
    ) {
        if ($this->recordCache) {
            $this->recordCache->setContext(RecordCache::CONTEXT_FAVORITE);
            $this->recordCache->createOrUpdate(
                $resource->getRecordId(),
                $resource->getSource(),
                $driver->getRawData()
            );
        }
    }

    /**
     * Given an array of item ids, remove them from the specified list.
     *
     * @param FinnaResourceListEntityInterface $list   List being updated
     * @param ?UserEntityInterface             $user   Logged-in user
     * @param string[]                         $ids    IDs to remove from the list
     * @param string                           $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeListResourcesById(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $ids,
        string $source = DEFAULT_SEARCH_BACKEND
    ): void {
        if (!$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resources = $this->resourceService->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
        }

        // Remove Resource and related tags:
        $listUser = $list->getUser();
        $this->resourceListResourceService->unlinkResources($resourceIDs, $listUser, $list);
    }

    /**
     * Save this record to a resource list.
     *
     * @param Parameters          $params Array with some or all of these keys:
     *                                    <ul> <li>mytags - Tag array to
     *                                    associate with record (optional)</li>
     *                                    <li>notes - Notes to associate with
     *                                    record (optional)</li> <li>list - ID
     *                                    of list to save record into (omit to
     *                                    create new list)</li> </ul>
     * @param UserEntityInterface $user   The user saving the record
     * @param RecordDriver        $driver Record driver for record being saved
     *
     * @return array list information
     */
    public function saveRecordToReservationList(
        Parameters $params,
        UserEntityInterface $user,
        RecordDriver $driver
    ): array {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }
        // Get or create a list object as needed:
        $listId = (int)$params->get('list');
        $list = $this->getListById($listId, $user);

        // Get or create a resource object as needed:
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);

        // Persist record in the database for "offline" use
        $this->persistToCache($driver, $resource);
        $this->resourceListResourceService->createOrUpdateLink($resource, $user, $list, $params->get('desc', ''));
        return ['listId' => $list->getId()];
    }

    /**
     * Set list ordered
     *
     * @param UserEntityInterface              $user      User to check for rights to list
     * @param FinnaResourceListEntityInterface $list      List entity or id of the list
     * @param array                            $newValues New values to update as key value pairs
     *
     * @return void
     */
    public function setListOrdered(
        UserEntityInterface $user,
        FinnaResourceListEntityInterface $list,
        array $newValues
    ): void {
        if (!$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }
        $list->setPickupDate(DateTime::createFromFormat('Y-m-d', $newValues['pickup_date']))->setOrdered();
        $list->setExternalId($newValues['external_id'] ?? null);
        $list->setConnection($newValues['connection'] ?? self::DEFAULT_CONNECTION_HANDLER);
        $this->resourceListService->persistEntity($list);
    }

    /**
     * Saves the provided list to the database and remembers it in the session if it is valid;
     * throws an exception otherwise.
     *
     * @param FinnaResourceListEntityInterface $list List to save
     * @param UserEntityInterface              $user Logged-in user (null if none)
     *
     * @return void
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function saveListForUser(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user
    ): void {
        if (!$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }
        if (!$list->getTitle()) {
            throw new MissingFieldException('list_edit_name_required');
        }
        $this->resourceListService->persistEntity($list);
        $this->rememberLastUsedList($list);
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param FinnaResourceListEntityInterface $list    List to update
     * @param UserEntityInterface              $user    Logged-in user
     * @param Parameters                       $request Request to process
     *
     * @return int ID of newly created row
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function updateListFromRequest(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        Parameters $request
    ): int {
        $list->setTitle($request->get('title'))
            ->setDescription($request->get('desc'))
            ->setInstitution($request->get('institution'))
            ->setListConfigIdentifier($request->get('listIdentifier'))
            ->setUser($user)
            ->setListType(self::RESOURCE_LIST_TYPE)
            ->setConnection($request->get('connection', self::DEFAULT_CONNECTION_HANDLER));
        $this->saveListForUser($list, $user);
        return $list->getId();
    }

    /**
     * Is the provided user allowed to edit the provided list?
     *
     * @param ?UserEntityInterface             $user Logged-in user (null if none)
     * @param FinnaResourceListEntityInterface $list List to check
     *
     * @return bool
     */
    public function userCanEditList(?UserEntityInterface $user, FinnaResourceListEntityInterface $list): bool
    {
        return $user && $user->getId() === $list->getUser()?->getId();
    }

    /**
     * Delete a group of resources.
     *
     * @param string[]                         $ids  Array of IDs in source|id format.
     * @param FinnaResourceListEntityInterface $list List to delete from
     * @param UserEntityInterface              $user Logged in user
     *
     * @return void
     */
    public function deleteResourcesFromList(
        array $ids,
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user
    ): void {
        // Sort $ids into useful array:
        $sorted = [];
        foreach ($ids as $current) {
            [$source, $id] = explode('|', $current, 2);
            if (!isset($sorted[$source])) {
                $sorted[$source] = [];
            }
            $sorted[$source][] = $id;
        }
        foreach ($sorted as $source => $ids) {
            $this->removeListResourcesById($list, $user, $ids, $source);
        }
    }

    /**
     * Get resource list as an array containing formatted dates to be displayed in templates
     *
     * @param int                 $listId List id
     * @param UserEntityInterface $user   User entity object
     *
     * @return FinnaResourceListEntityInterface
     */
    public function getListById(int $listId, UserEntityInterface $user = null): FinnaResourceListEntityInterface
    {
        $list = $this->resourceListService->getResourceListById($listId);
        // Validate incoming list ID:
        if (!$this->userCanEditList($user, $list)) {
            throw new \VuFind\Exception\ListPermission('Access denied.');
        }
        return $list;
    }

    /**
     * Get resource lists identified as reservation list for user
     *
     * @param UserEntityInterface $user           Optional user ID or entity object (to limit results
     *                                            to a particular user).
     * @param string              $institution    List institution
     * @param string              $listIdentifier List identifier given by the institution
     *                                            for the list or empty for all
     *
     * @return FinnaResourceListEntityInterface[]
     */
    public function getReservationListsForUser(
        UserEntityInterface $user,
        string $institution = '',
        string $listIdentifier = ''
    ): array {
        return $this->resourceListService->getResourceListsForUser(
            $user,
            $listIdentifier,
            $institution,
            self::RESOURCE_LIST_TYPE
        );
    }

    /**
     * Get lists not containing a specific record.
     *
     * @param UserEntityInterface $user           User or id to check for lists
     * @param DefaultRecord       $record         Record object to check
     * @param string              $listIdentifier List identifier in list config
     * @param string              $institution    Institution name
     *
     * @return array
     */
    public function getListsNotContainingRecord(
        UserEntityInterface $user,
        DefaultRecord $record,
        string $listIdentifier = '',
        string $institution = ''
    ): array {
        // Get or create a resource object as needed:
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($record);
        return $this->resourceListService->getListsNotContainingResource(
            $user,
            $resource,
            $listIdentifier,
            $institution,
            self::RESOURCE_LIST_TYPE
        );
    }

    /**
     * Get resources for list
     *
     * @param FinnaResourceListEntityInterface $list List to get resources for
     * @param UserEntityInterface              $user User entity
     *
     * @return array
     */
    public function getResourcesForList(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user
    ): array {
        return $this->resourceListResourceService->getResourcesForList($user, $list);
    }

    /**
     * Get lists containing a specific record.
     *
     * @param UserEntityInterface $user           User entity object or ID
     * @param DefaultRecord       $record         Resource entity to look for
     * @param string              $listIdentifier Identifier of the list used by institution
     * @param string              $institution    Institution name
     *
     * @return FinnaResourceListEntityInterface[]
     */
    public function getListsContainingRecord(
        UserEntityInterface $user = null,
        DefaultRecord $record,
        string $listIdentifier = '',
        string $institution = ''
    ): array {
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($record);
        return $this->resourceListService->getListsContainingResource(
            $user,
            $resource,
            $listIdentifier,
            $institution,
            self::RESOURCE_LIST_TYPE
        );
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
     * @return array
     */
    public function getListProperties(
        string $institution,
        string $listIdentifier
    ): array {
        foreach ($this->reservationListConfig['Institutions'][$institution]['Lists'] ?? [] as $list) {
            $list = $this->ensureListKeys($list);
            if ($list['Identifier'] === $listIdentifier) {
                return [
                    'properties' => $list,
                    'institution_information'
                        => $this->reservationListConfig['Institutions'][$institution]['Information'] ?? [],
                    'translation_keys' => [
                        'title' => "ReservationList::list_title_{$institution}_{$listIdentifier}",
                        'description' => "ReservationList::list_description_{$institution}_{$listIdentifier}",
                    ],
                ];
            }
        }
        return [
            'properties' => self::RESERVATION_LIST_DEFAULT_VALUES,
            'institution_information' => [],
            'translation_keys' => [
                'title' => '',
                'description' => '',
            ],
        ];
    }

    /**
     * Ensure that lists have all the required keys defined needed in other places.
     * Sets the list disabled if list identifier is not set or is not a string.
     *
     * @param array $list Properties of the list to ensure
     *
     * @return array
     */
    public function ensureListKeys(array $list): array
    {
        $merged = array_merge(self::RESERVATION_LIST_DEFAULT_VALUES, $list);
        if (!is_string($merged['Identifier'])) {
            $merged['Enabled'] = false;
        }
        return $merged;
    }
}
