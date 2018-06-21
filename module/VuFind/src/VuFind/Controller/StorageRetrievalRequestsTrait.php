<?php
/**
 * Storage retrieval requests trait (for subclasses of AbstractRecord)
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

/**
 * Storage retrieval requests trait (for subclasses of AbstractRecord)
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait StorageRetrievalRequestsTrait
{
    /**
     * Action for dealing with blocked storage retrieval requests.
     *
     * @return mixed
     */
    public function blockedStorageRetrievalRequestAction()
    {
        $this->flashMessenger()
            ->addMessage('storage_retrieval_request_error_blocked', 'error');
        return $this->redirectToRecord('#top');
    }

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
                'patron' => $patron
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
        if (!$catalog->checkStorageRetrievalRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedStorageRetrievalRequestAction();
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $extraFields = isset($checkRequests['extraFields'])
            ? explode(":", $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeStorageRetrievalRequest'))) {
            // If we made it this far, we're ready to place the hold;
            // if successful, we will redirect and can stop here.

            // Add Patron Data to Submitted Data
            $details = $gatheredDetails + ['patron' => $patron];

            // Attempt to place the hold:
            $function = (string)$checkRequests['function'];
            $results = $catalog->$function($details);

            // Success: Go to Display Storage Retrieval Requests
            if (isset($results['success']) && $results['success'] == true) {
                $this->flashMessenger()->addMessage(
                    'storage_retrieval_request_place_success', 'success'
                );
                if ($this->inLightbox()) {
                    return false;
                }
                return $this->redirect()->toRoute(
                    'myresearch-storageretrievalrequests'
                );
            } else {
                // Failure: use flash messenger to display messages, stay on
                // the current form.
                if (isset($results['status'])) {
                    $this->flashMessenger()->addMessage($results['status'], 'error');
                }
                if (isset($results['sysMessage'])) {
                    $this->flashMessenger()
                        ->addMessage($results['sysMessage'], 'error');
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->storageRetrievalRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->getServiceLocator()->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => isset($checkRequests['helpText'])
                    ? $checkRequests['helpText'] : null
            ]
        );
        $view->setTemplate('record/storageretrievalrequest');
        return $view;
    }
}
