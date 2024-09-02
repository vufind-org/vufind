<?php

/**
 * Storage retrieval requests trait (for subclasses of AbstractRecord)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use function in_array;
use function is_array;

/**
 * Storage retrieval requests trait (for subclasses of AbstractRecord)
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait StorageRetrievalRequestsTrait
{
    /**
     * Action for dealing with storage retrieval requests.
     *
     * @return mixed
     */
    public function storageRetrievalRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'StorageRetrievalRequests',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron,
            ]
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->storageRetrievalRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkStorageRetrievalRequestIsValid(
            $driver->getUniqueID(),
            $gatheredDetails,
            $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status']
                    : 'storage_retrieval_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $extraFields = isset($checkRequests['extraFields'])
            ? explode(':', $checkRequests['extraFields']) : [];

        // Check that there are pick up locations to choose from if the field is
        // required:
        if (in_array('pickUpLocation', $extraFields) && !$pickup) {
            $this->flashMessenger()
                ->addErrorMessage('No pickup locations available');
            return $this->redirectToRecord('#top');
        }

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeStorageRetrievalRequest')) {
            // If we made it this far, we're ready to place the hold;
            // if successful, we will redirect and can stop here.

            // Check that any pick up location is valid:
            $validPickup = $this->storageRetrievalRequests()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? null,
                $extraFields,
                $pickup
            );
            if (!$validPickup) {
                $this->flashMessenger()
                    ->addErrorMessage('storage_retrieval_request_invalid_pickup');
            } else {
                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                // Success: Go to Display Storage Retrieval Requests
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'storage_retrieval_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-storageretrievalrequests'),
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    $this->getViewRenderer()->plugin('session')->put('reset_account_status', true);
                    return $this->redirectToRecord($this->inLightbox() ? '?layout=lightbox' : '');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->addErrorMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequiredDate = $this->storageRetrievalRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequiredDate = $this->getService(\VuFind\Date\Converter::class)
            ->convertToDisplayDate('U', $defaultRequiredDate);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $config = $this->getConfig();
        $homeLibrary = ($config->Account->set_home_library ?? true)
            ? $this->getUser()->getHomeLibrary() : '';
        // helpText is only for backward compatibility:
        $helpText = $helpTextHtml = $checkRequests['helpText'];

        $view = $this->createViewModel(
            compact(
                'gatheredDetails',
                'pickup',
                'defaultPickup',
                'homeLibrary',
                'extraFields',
                'defaultRequiredDate',
                'helpText',
                'helpTextHtml'
            )
        );
        $view->setTemplate('record/storageretrievalrequest');
        return $view;
    }
}
