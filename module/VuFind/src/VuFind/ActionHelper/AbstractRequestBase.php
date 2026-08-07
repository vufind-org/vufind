<?php

/**
 * VuFind Action Helper - Requests Support Methods.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\ActionHelper;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Crypt\HMAC;
use VuFind\Date\Converter as DateConverter;
use VuFind\Http\RouteHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

use function count;
use function get_class;
use function in_array;

/**
 * Action helper base class to perform request-related actions.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractRequestBase implements HelperInterface
{
    /**
     * Session data.
     *
     * @var Container
     */
    protected Container $session;

    /**
     * Constructor.
     *
     * @param HMAC                    $hmac           HMAC generator
     * @param SessionManager          $sessionManager Session manager
     * @param DateConverter           $dateConverter  Date converter
     * @param RouteHelper             $routeHelper    Route helper
     * @param ForwardHelper           $forwardHelper  Forward helper
     * @param FlashMessengerInterface $flashMessenger Flash messenger
     */
    #[Autowire]
    public function __construct(
        protected HMAC $hmac,
        protected SessionManager $sessionManager,
        protected DateConverter $dateConverter,
        protected RouteHelper $routeHelper,
        #[Autowire(container: PluginManager::class)]
        protected ForwardHelper $forwardHelper,
        protected FlashMessengerInterface $flashMessenger,
    ) {
    }

    /**
     * Reset the array of valid IDs in the session (used for form submission validation).
     *
     * @return void
     */
    public function resetValidation(): void
    {
        $this->getSession()->validIds = [];
    }

    /**
     * Add an ID to the validation array.
     *
     * @param string $id ID to remember
     *
     * @return void
     */
    public function rememberValidId(string $id): void
    {
        // The session container doesn't allow modification of entries (as of 2012, anyway), so we have to do this in a
        // roundabout way. TODO: investigate whether this limitation has been lifted.
        $existingArray = $this->getSession()->validIds;
        $existingArray[] = $id;
        $this->getSession()->validIds = $existingArray;
    }

    /**
     * Validate supplied IDs against remembered IDs. Returns true if all supplied IDs are remembered, otherwise returns
     * false.
     *
     * @param array $ids IDs to validate
     *
     * @return bool
     */
    public function validateIds(array $ids): bool
    {
        return !(bool)array_diff($ids, $this->getValidIds());
    }

    /**
     * Method for validating contents of a request; returns an array of collected details if request is valid, otherwise
     * returns false.
     *
     * @param ServerRequestInterface $request  Request
     * @param array                  $linkData An array of keys to check
     *
     * @return array|false
     */
    public function validateRequest(ServerRequestInterface $request, array $linkData): array|false
    {
        $queryParams = $request->getQueryParams();
        $postParams = $request->getParsedBody();
        $routeParams = $request->getAttribute('route-match')?->getParams() ?? [];
        $keyValueArray = [];
        foreach ($linkData as $details) {
            // We expect most parameters to come via query, but some (mainly ID) may be in the route:
            $keyValueArray[$details] = $queryParams[$details] ?? $routeParams[$details] ?? null;
        }
        $hashKey = $this->hmac->generate($linkData, $keyValueArray);

        if (($queryParams['hashKey'] ?? null) != $hashKey) {
            return false;
        }

        // Initialize gatheredDetails with any POST values we find; this will allow us to repopulate the form with
        // user-entered values if there is an error. However, it is important that we load the POST data FIRST and then
        // override it with GET values in order to ensure that the user doesn't bypass the hashkey verification by
        // manipulating POST values.
        $gatheredDetails = $postParams['gatheredDetails'] ?? [];

        // Make sure the bib ID is included, even if it's not loaded as part of the validation loop below:
        $gatheredDetails['id'] = $routeParams['id'] ?? $queryParams['id'] ?? null;

        // Return all details:
        return array_merge($gatheredDetails, $keyValueArray);
    }

    /**
     * Check if the user-provided pickup location is valid.
     *
     * @param string $pickup          User-specified pickup location
     * @param array  $extraHoldFields Hold form fields enabled by configuration/driver
     * @param array  $pickUpLibs      Pickup library list from driver
     *
     * @return bool
     */
    public function validatePickUpInput(string $pickup, array $extraHoldFields, array $pickUpLibs): bool
    {
        // Not having to care for pickUpLocation is equivalent to having a valid one:
        if (!in_array('pickUpLocation', $extraHoldFields)) {
            return true;
        }

        // Check the valid pickup locations for a match against user input:
        return $this->validatePickUpLocation($pickup, $pickUpLibs);
    }

    /**
     * Check if the provided pickup location is valid.
     *
     * @param string $location   Location to check
     * @param array  $pickUpLibs Pickup locations list from driver
     *
     * @return bool
     */
    public function validatePickUpLocation(string $location, array $pickUpLibs): bool
    {
        foreach ($pickUpLibs as $lib) {
            if ($location == $lib['locationID']) {
                return true;
            }
        }

        // If we got this far, something is wrong!
        return false;
    }

    /**
     * Check if the user-provided request group is valid.
     *
     * @param array $gatheredDetails User hold parameters
     * @param array $extraHoldFields Form fields enabled by configuration/driver
     * @param array $requestGroups   Request group list from driver
     *
     * @return bool
     */
    public function validateRequestGroupInput(
        array $gatheredDetails,
        array $extraHoldFields,
        array $requestGroups
    ): bool {
        // Not having to care for requestGroup is equivalent to having a valid one:
        if (!in_array('requestGroup', $extraHoldFields)) {
            return true;
        }
        if (
            !isset($gatheredDetails['level'])
            || $gatheredDetails['level'] !== 'title'
        ) {
            return true;
        }

        // Check the valid request groups for a match against user input:
        return $this->validateRequestGroup(
            $gatheredDetails['requestGroupId'],
            $requestGroups
        );
    }

    /**
     * Check if the provided request group is valid.
     *
     * @param string $requestGroupId Id of the request group to check
     * @param array  $requestGroups  Request group list from driver
     *
     * @return bool
     */
    public function validateRequestGroup(string $requestGroupId, array $requestGroups): bool
    {
        foreach ($requestGroups as $group) {
            if ($requestGroupId == $group['id']) {
                return true;
            }
        }

        // If we got this far, something is wrong!
        return false;
    }

    /**
     * Getting a default required date based on hold settings.
     *
     * @param array       $checkHolds Hold settings returned by the ILS driver's checkFunction method
     * @param ?Connection $catalog    ILS connection (optional)
     * @param ?array      $patron     Patron details (optional)
     * @param ?array      $holdInfo   Hold details (optional)
     *
     * @return int A timestamp representing the default required date
     */
    public function getDefaultRequiredDate(
        array $checkHolds,
        ?Connection $catalog = null,
        ?array $patron = null,
        ?array $holdInfo = null
    ) {
        // Load config:
        $dateArray = isset($checkHolds['defaultRequiredDate'])
             ? explode(':', $checkHolds['defaultRequiredDate'])
             : [0, 1, 0];

        // Process special "driver" prefix and adjust default date settings accordingly:
        if ($dateArray[0] == 'driver') {
            $useDriver = true;
            array_shift($dateArray);
            if (count($dateArray) < 3) {
                $dateArray = [0, 1, 0];
            }
        } else {
            $useDriver = false;
        }

        // If the driver setting is active, try it out:
        if ($useDriver && $catalog) {
            $check = $catalog->checkCapability(
                'getHoldDefaultRequiredDate',
                [$patron, $holdInfo]
            );
            if ($check) {
                $result = $catalog->getHoldDefaultRequiredDate($patron, $holdInfo);
                if (!empty($result)) {
                    return $result;
                }
            }
        }

        // If the driver setting is off or the driver didn't work, use the standard relative date mechanism:
        return $this->getDateFromArray($dateArray);
    }

    /**
     * Grab the Container object for storing helper-specific session data.
     *
     * @return Container
     */
    protected function getSession(): Container
    {
        if (!isset($this->session)) {
            $this->session = new Container(
                get_class($this) . '_Helper',
                $this->sessionManager
            );
        }
        return $this->session;
    }

    /**
     * Support method for getDefaultRequiredDate() -- generate a date based on a days/months/years offset array.
     *
     * @param array $dateArray 3-element array containing day/month/year offsets
     *
     * @return int A timestamp representing the default required date
     */
    protected function getDateFromArray(array $dateArray): int
    {
        if (!isset($dateArray[2])) {
            return 0;
        }
        [$d, $m, $y] = $dateArray;
        return mktime(
            0,
            0,
            0,
            date('m') + $m,
            date('d') + $d,
            date('Y') + $y
        );
    }

    /**
     * Get remembered valid IDs.
     *
     * @return array
     */
    protected function getValidIds(): array
    {
        return $this->getSession()->validIds ?? [];
    }
}
