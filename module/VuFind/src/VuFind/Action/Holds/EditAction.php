<?php

/**
 * Edit holds action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021-2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Holds;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\HoldsHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

use function count;
use function in_array;
use function is_array;

/**
 * Edit holds action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditAction extends AbstractHoldAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Edit holds.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $holdConfig = $this->ilsConnection->checkFunction('Holds', compact('patron'));
        $selectedIds = $this->getPostOrQueryParam('selectedIDS');
        if (empty($holdConfig['updateFields']) || empty($selectedIds)) {
            // Shouldn't be here. Redirect back to holds.
            return $this->getRefreshOrRedirectToListResponse($request, $response);
        }
        // If the user input contains a value not found in the session legal list, something has been tampered
        // with -- abort the process.
        $holdsHelper = $this->getHelper(HoldsHelper::class);
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!$holdsHelper->validateIds($selectedIds)) {
            $flashMessagesHelper->addErrorMessage('error_inconsistent_parameters');
            return $this->getRefreshOrRedirectToListResponse($request, $response);
        }

        $pickupLocationInfo = $this->getPickupLocationsForEdit(
            $patron,
            $selectedIds,
            $holdConfig['pickUpLocationCheckLimit']
        );

        $gatheredDetails = $this->getPostParam('gatheredDetails', []);
        if ($this->getPostParam('updateHolds')) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }

            $updateFields = $this->getUpdateFieldsFromGatheredDetails(
                $holdConfig,
                $gatheredDetails,
                $pickupLocationInfo['pickupLocations']
            );
            if ($updateFields) {
                $results = $this->ilsConnection->updateHolds($selectedIds, $updateFields, $patron);
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
                    $flashMessagesHelper->addSuccessMessage($msg);
                }
                if ($failed) {
                    $msg = $this->translate(
                        'hold_edit_failed_items',
                        ['%%count%%' => $failed]
                    );
                    $flashMessagesHelper->addErrorMessage($msg);
                }

                $this->auditEventService->addEvent(
                    AuditEventType::ILS,
                    AuditEventSubtype::UpdateHolds,
                    $this->authManager->getUserObject(),
                    data: [
                        'username' => $patron['cat_username'],
                        'results' => $results,
                    ]
                );

                return $this->getRefreshOrRedirectToListResponse($request, $response);
            }
        }

        $templateParams = [
            'selectedIDS' => $selectedIds,
            'fields' => $holdConfig['updateFields'],
            'gatheredDetails' => $gatheredDetails,
            'pickupLocations' => $pickupLocationInfo['pickupLocations'],
            'conflictingPickupLocations' => $pickupLocationInfo['differences'],
            'helpTextHtml' => $holdConfig['updateHelpText'],
        ];

        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Get a refresh or redirect response depending on context.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    protected function getRefreshOrRedirectToListResponse(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->getHelper(ContextHelper::class)->inLightbox($request)
            ? $this->getHelper(ResponseHelper::class)->getRefreshResponse($response)
            : $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'holds-list');
    }

    /**
     * Get list of pickup locations based on the first selected hold. This may not be perfect as pickup locations may
     * differ per hold, but it's the best we can do.
     *
     * @param array $patron      Patron information
     * @param array $selectedIds Selected holds
     * @param int   $checkLimit  Maximum number of pickup location checks to make (0 = no limit)
     *
     * @return array An array of any common pickup locations and a flag indicating any differences between them.
     */
    protected function getPickupLocationsForEdit(
        array $patron,
        array $selectedIds,
        int $checkLimit = 0
    ): array {
        // Get holds from cache if available:
        $holds = $this->getCachedData($this->getCacheId($patron, 'holds'))
            ?? $this->ilsConnection->getMyHolds($patron, $this->getPageOptions($patron)['ilsParams']);
        $checks = 0;
        $pickupLocations = [];
        $differences = false;
        foreach ($holds['records'] as $hold) {
            if (in_array((string)($hold['updateDetails'] ?? ''), $selectedIds)) {
                try {
                    $locations = $this->ilsConnection->getPickUpLocations($patron, $hold);
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
                    $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('ils_connection_failed');
                }
            }
        }

        return compact('pickupLocations', 'differences');
    }

    /**
     * Get fields to update from details gathered from the user.
     *
     * @param array $holdConfig      Hold configuration from the driver
     * @param array $gatheredDetails Details gathered from the user
     * @param array $pickupLocations Valid pickup locations
     *
     * @return ?array Array of fields to update or null on validation error
     */
    protected function getUpdateFieldsFromGatheredDetails(
        array $holdConfig,
        array $gatheredDetails,
        array $pickupLocations
    ): ?array {
        $validPickup = true;
        $selectedPickupLocation = $gatheredDetails['pickUpLocation'] ?? '';
        $holdsHelper = $this->getHelper(HoldsHelper::class);
        if ('' !== $selectedPickupLocation) {
            $validPickup = $holdsHelper->validatePickUpInput(
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
            $dateValidationResults = $holdsHelper->validateDates(
                $gatheredDetails['startDate'] ?? null,
                $gatheredDetails['requiredBy'] ?? null,
                $holdConfig['updateFields']
            );
        }
        if (in_array('frozenThrough', $holdConfig['updateFields'])) {
            $frozenThroughValidationResults = $holdsHelper->validateFrozenThrough(
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
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!$validPickup) {
            $flashMessagesHelper->addErrorMessage('hold_invalid_pickup');
        }
        foreach ($dateValidationResults['errors'] as $msg) {
            $flashMessagesHelper->addErrorMessage($msg);
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
            $updateFields['startDateTS'] = $dateValidationResults['startDateTS'];
        }
        if (($gatheredDetails['requiredBy'] ?? '') !== '') {
            $updateFields['requiredBy'] = $gatheredDetails['requiredBy'];
            $updateFields['requiredByTS'] = $dateValidationResults['requiredByTS'];
        }
        if (($gatheredDetails['frozen'] ?? '') !== '') {
            $updateFields['frozen'] = $gatheredDetails['frozen'] === '1';
            if (($gatheredDetails['frozenThrough']) ?? '' !== '') {
                $updateFields['frozenThrough'] = $gatheredDetails['frozenThrough'];
                $updateFields['frozenThroughTS'] = $frozenThroughValidationResults['frozenThroughTS'];
            }
        }

        return $updateFields;
    }
}
