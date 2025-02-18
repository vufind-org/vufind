<?php

/**
 * Holds Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\Validator\CsrfInterface;

use function count;
use function in_array;
use function is_array;

/**
 * Controller for the user holds area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HoldsController extends AbstractBase
{
    use Feature\CatchIlsExceptionsTrait;
    use \VuFind\Cache\CacheTrait;

    /**
     * CSRF validator
     *
     * @var CsrfInterface
     */
    protected $csrf;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm    Service locator
     * @param CsrfInterface           $csrf  CSRF validator
     * @param StorageInterface        $cache Cache
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        CsrfInterface $csrf,
        StorageInterface $cache
    ) {
        parent::__construct($sm);
        $this->csrf = $csrf;
        $this->setCacheStorage($cache);
        // Cache the data related to holds for up to 10 minutes:
        $this->cacheLifetime = 600;
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function listAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelHolds', compact('patron'));
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus ? $this->holds()->cancelHolds($catalog, $patron) : [];
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // Process any update request results stored in the session:
        $updateResultsContainer = $this->getHoldUpdateResultsContainer();
        $holdUpdateResults = $updateResultsContainer->results ?? null;
        if ($holdUpdateResults) {
            $view->updateResults = $holdUpdateResults;
            $updateResultsContainer->results = null;
        }
        // Process update requests if necessary:
        if ($this->params()->fromPost('updateSelected')) {
            $selectedIds = $this->params()->fromPost('selectedIDS');
            if (empty($selectedIds)) {
                $this->flashMessenger()->addErrorMessage('hold_empty_selection');
                if ($this->inLightbox()) {
                    return $this->getRefreshResponse();
                }
            } else {
                return $this->forwardTo('Holds', 'Edit');
            }
        }

        // By default, assume we will not need to display a cancel or update form:
        $view->cancelForm = false;
        $view->updateForm = false;

        // Get held item details:
        $result = $catalog->getMyHolds($patron);
        $driversNeeded = [];
        $this->holds()->resetValidation();
        $holdConfig = $catalog->checkFunction('Holds', compact('patron'));
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->holds()->addCancelDetails(
                $catalog,
                $current,
                $cancelStatus,
                $patron
            );
            if (
                $cancelStatus && $cancelStatus['function'] !== 'getCancelHoldLink'
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            // Add update details if appropriate
            if (isset($current['updateDetails'])) {
                if (
                    empty($holdConfig['updateFields'])
                    || '' === $current['updateDetails']
                ) {
                    unset($current['updateDetails']);
                } else {
                    $view->updateForm = true;
                    $this->holds()->rememberValidId($current['updateDetails']);
                }
            }

            $driversNeeded[] = $current;
        }
        // Cache the current list of requests for editing:
        $this->putCachedData(
            $this->getCacheId($patron, 'holds'),
            $driversNeeded
        );

        // Get List of PickUp Libraries based on patron's home library
        try {
            $pickupCacheId = $this->getCacheId($patron, 'pickup');
            $view->pickup = $this->getCachedData($pickupCacheId);
            if (null === $view->pickup) {
                $view->pickup = $catalog->getPickUpLocations($patron);
                $this->putCachedData($pickupCacheId, $view->pickup);
            }
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }

        $view->recordList = $this->ilsRecords()->getDrivers($driversNeeded);
        $view->accountStatus = $this->ilsRecords()
            ->collectRequestStats($view->recordList);
        return $view;
    }

    /**
     * Edit holds
     *
     * @return mixed
     */
    public function editAction()
    {
        $this->ilsExceptionResponse = $this->createViewModel(
            [
                'selectedIDS' => [],
                'fields' => [],
            ]
        );

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        $holdConfig = $catalog->checkFunction('Holds', compact('patron'));
        $selectedIds = $this->params()->fromPost('selectedIDS')
            ?: $this->params()->fromQuery('selectedIDS');
        if (empty($holdConfig['updateFields']) || empty($selectedIds)) {
            // Shouldn't be here. Redirect back to holds.
            return $this->inLightbox()
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('holds-list');
        }
        // If the user input contains a value not found in the session
        // legal list, something has been tampered with -- abort the process.
        if (!$this->holds()->validateIds($selectedIds)) {
            $this->flashMessenger()
                ->addErrorMessage('error_inconsistent_parameters');
            return $this->inLightbox()
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('holds-list');
        }

        $pickupLocationInfo = $this->getPickupLocationsForEdit(
            $patron,
            $selectedIds,
            $holdConfig['pickUpLocationCheckLimit']
        );

        $gatheredDetails = $this->params()->fromPost('gatheredDetails', []);
        if ($this->params()->fromPost('updateHolds')) {
            if (!$this->csrf->isValid($this->params()->fromPost('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }

            $updateFields = $this->getUpdateFieldsFromGatheredDetails(
                $holdConfig,
                $gatheredDetails,
                $pickupLocationInfo['pickupLocations']
            );
            if ($updateFields) {
                $results
                    = $catalog->updateHolds($selectedIds, $updateFields, $patron);
                $successful = 0;
                $failed = 0;
                foreach ($results as $result) {
                    if ($result['success']) {
                        ++$successful;
                    } else {
                        ++$failed;
                    }
                }
                // Store results in the session so that they can be displayed when
                // the user is redirected back to the holds list:
                $this->getHoldUpdateResultsContainer()->results = $results;
                if ($successful) {
                    $msg = $this->translate(
                        'hold_edit_success_items',
                        ['%%count%%' => $successful]
                    );
                    $this->flashMessenger()->addSuccessMessage($msg);
                }
                if ($failed) {
                    $msg = $this->translate(
                        'hold_edit_failed_items',
                        ['%%count%%' => $failed]
                    );
                    $this->flashMessenger()->addErrorMessage($msg);
                }
                return $this->inLightbox()
                    ? $this->getRefreshResponse(true)
                    : $this->redirect()->toRoute('holds-list');
            }
        }

        $view = $this->createViewModel(
            [
                'selectedIDS' => $selectedIds,
                'fields' => $holdConfig['updateFields'],
                'gatheredDetails' => $gatheredDetails,
                'pickupLocations' => $pickupLocationInfo['pickupLocations'],
                'conflictingPickupLocations' => $pickupLocationInfo['differences'],
                'helpTextHtml' => $holdConfig['updateHelpText'],
            ]
        );

        return $view;
    }

    /**
     * Get list of pickup locations based on the first selected hold. This may not be
     * perfect as pickup locations may differ per hold, but it's the best we can do.
     *
     * @param array $patron      Patron information
     * @param array $selectedIds Selected holds
     * @param int   $checkLimit  Maximum number of pickup location checks to make
     * (0 = no limit)
     *
     * @return array An array of any common pickup locations and a flag
     * indicating any differences between them.
     */
    protected function getPickupLocationsForEdit(
        array $patron,
        array $selectedIds,
        int $checkLimit = 0
    ): ?array {
        $catalog = $this->getILS();
        // Get holds from cache if available:
        $holds = $this->getCachedData($this->getCacheId($patron, 'holds'))
            ?? $catalog->getMyHolds($patron);
        $checks = 0;
        $pickupLocations = [];
        $differences = false;
        foreach ($holds as $hold) {
            if (in_array((string)($hold['updateDetails'] ?? ''), $selectedIds)) {
                try {
                    $locations = $catalog->getPickUpLocations($patron, $hold);
                    if (!$pickupLocations) {
                        $pickupLocations = $locations;
                    } else {
                        $ids1 = array_column($pickupLocations, 'locationID');
                        $ids2 = array_column($locations, 'locationID');
                        if (
                            count($ids1) !== count($ids2) || array_diff($ids1, $ids2)
                        ) {
                            $differences = true;
                            // Find out any common pickup locations:
                            $common = array_intersect($ids1, $ids2);
                            if (!$common) {
                                $pickupLocations = [];
                                break;
                            }
                            $pickupLocations = array_filter(
                                $pickupLocations,
                                function ($location) use ($common) {
                                    return in_array(
                                        $location['locationID'],
                                        $common
                                    );
                                }
                            );
                        }
                    }
                    ++$checks;
                    if ($checkLimit && $checks >= $checkLimit) {
                        break;
                    }
                } catch (ILSException $e) {
                    $this->flashMessenger()
                        ->addErrorMessage('ils_connection_failed');
                }
            }
        }

        return compact('pickupLocations', 'differences');
    }

    /**
     * Get fields to update from details gathered from the user
     *
     * @param array $holdConfig      Hold configuration from the driver
     * @param array $gatheredDetails Details gathered from the user
     * @param array $pickupLocations Valid pickup locations
     *
     * @return null|array Array of fields to update or null on validation error
     */
    protected function getUpdateFieldsFromGatheredDetails(
        array $holdConfig,
        array $gatheredDetails,
        array $pickupLocations
    ): ?array {
        $validPickup = true;
        $selectedPickupLocation = $gatheredDetails['pickUpLocation'] ?? '';
        if ('' !== $selectedPickupLocation) {
            $validPickup = $this->holds()->validatePickUpInput(
                $selectedPickupLocation,
                $holdConfig['updateFields'],
                $pickupLocations
            );
        }
        $dateValidationResults = [
            'errors' => [],
        ];
        $frozenThroughValidationResults = [
            'frozenThroughTS' => null,
            'errors' => [],
        ];
        // The dates are not required unless one of them is set, so check that first:
        if (
            !empty($gatheredDetails['startDate'])
            || !empty($gatheredDetails['requiredBy'])
        ) {
            $dateValidationResults = $this->holds()->validateDates(
                $gatheredDetails['startDate'] ?? null,
                $gatheredDetails['requiredBy'] ?? null,
                $holdConfig['updateFields']
            );
        }
        if (in_array('frozenThrough', $holdConfig['updateFields'])) {
            $frozenThroughValidationResults = $this->holds()->validateFrozenThrough(
                $gatheredDetails['frozenThrough'] ?? null,
                $holdConfig['updateFields']
            );
            $dateValidationResults['errors'] = array_unique(
                array_merge(
                    $dateValidationResults['errors'],
                    $frozenThroughValidationResults['errors']
                )
            );
        }
        if (!$validPickup) {
            $this->flashMessenger()->addErrorMessage('hold_invalid_pickup');
        }
        foreach ($dateValidationResults['errors'] as $msg) {
            $this->flashMessenger()->addErrorMessage($msg);
        }
        if (!$validPickup || $dateValidationResults['errors']) {
            return null;
        }

        $updateFields = [];
        if ($selectedPickupLocation !== '') {
            $updateFields['pickUpLocation'] = $selectedPickupLocation;
        }
        if ($gatheredDetails['startDate'] ?? '' !== '') {
            $updateFields['startDate'] = $gatheredDetails['startDate'];
            $updateFields['startDateTS']
                = $dateValidationResults['startDateTS'];
        }
        if (($gatheredDetails['requiredBy'] ?? '') !== '') {
            $updateFields['requiredBy'] = $gatheredDetails['requiredBy'];
            $updateFields['requiredByTS']
                = $dateValidationResults['requiredByTS'];
        }
        if (($gatheredDetails['frozen'] ?? '') !== '') {
            $updateFields['frozen'] = $gatheredDetails['frozen'] === '1';
            if (($gatheredDetails['frozenThrough']) ?? '' !== '') {
                $updateFields['frozenThrough']
                    = $gatheredDetails['frozenThrough'];
                $updateFields['frozenThroughTS']
                    = $frozenThroughValidationResults['frozenThroughTS'];
            }
        }

        return $updateFields;
    }

    /**
     * Return a session container for hold update results.
     *
     * @return \Laminas\Session\Container
     */
    protected function getHoldUpdateResultsContainer()
    {
        return new \Laminas\Session\Container(
            'hold_update',
            $this->getService(\Laminas\Session\SessionManager::class)
        );
    }

    /**
     * Get a unique cache id for a patron
     *
     * @param array  $patron Patron
     * @param string $type   Type of cached data
     *
     * @return string
     */
    protected function getCacheId(array $patron, string $type): string
    {
        return "$type::" . $patron['id'] . '::'
            . ($patron['cat_id'] ?? $patron['cat_username'] ?? '');
    }
}
