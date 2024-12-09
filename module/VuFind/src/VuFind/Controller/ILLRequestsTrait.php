<?php

/**
 * ILL trait (for subclasses of AbstractRecord)
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
 * ILL trait (for subclasses of AbstractRecord)
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ILLRequestsTrait
{
    /**
     * Action for dealing with ILL requests.
     *
     * @return mixed
     */
    public function illRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'ILLRequests',
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
        $gatheredDetails = $this->ILLRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkILLRequestIsValid(
            $driver->getUniqueID(),
            $gatheredDetails,
            $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'ill_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:

        $extraFields = isset($checkRequests['extraFields'])
            ? explode(':', $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeILLRequest')) {
            // If we made it this far, we're ready to place the hold;
            // if successful, we will redirect and can stop here.

            // Add Patron Data to Submitted Data
            $details = $gatheredDetails + ['patron' => $patron];

            // Attempt to place the hold:
            $function = (string)$checkRequests['function'];
            $results = $catalog->$function($details);

            // Success: Go to Display ILL Requests
            if (isset($results['success']) && $results['success'] == true) {
                $msg = [
                    'html' => true,
                    'msg' => 'ill_request_place_success_html',
                    'tokens' => [
                        '%%url%%' => $this->url()
                            ->fromRoute('myresearch-illrequests'),
                    ],
                ];
                $this->flashMessenger()->addMessage($msg, 'success');
                $this->getViewRenderer()->plugin('session')->put('reset_account_status', true);
                return $this->redirectToRecord($this->inLightbox() ? '?layout=lightbox' : '');
            } else {
                // Failure: use flash messenger to display messages, stay on
                // the current form.
                if (isset($results['status'])) {
                    $this->flashMessenger()
                        ->addMessage($results['status'], 'error');
                }
                if (isset($results['sysMessage'])) {
                    $this->flashMessenger()
                        ->addMessage($results['sysMessage'], 'error');
                }
            }
        }

        // Find and format the default required date:
        $defaultRequiredDate = $this->ILLRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequiredDate = $this->getService(\VuFind\Date\Converter::class)
            ->convertToDisplayDate('U', $defaultRequiredDate);

        // Get pickup libraries
        $pickupLibraries = $catalog->getILLPickUpLibraries(
            $driver->getUniqueID(),
            $patron,
            $gatheredDetails
        );

        // Get pickup locations. Note that these are independent of pickup library,
        // and library specific locations must be retrieved when a library is
        // selected.
        $pickupLocations = $catalog->getPickUpLocations($patron, $gatheredDetails);

        // Check that there are pick up locations to choose from if the field is
        // required:
        if (in_array('pickUpLocation', $extraFields) && !$pickupLocations) {
            $this->flashMessenger()
                ->addErrorMessage('No pickup locations available');
            return $this->redirectToRecord('#top');
        }

        $config = $this->getConfig();
        $homeLibrary = ($config->Account->set_home_library ?? true)
            ? $this->getUser()->getHomeLibrary() : '';
        // helpText is only for backward compatibility:
        $helpText = $helpTextHtml = $checkRequests['helpText'];

        $view = $this->createViewModel(
            compact(
                'gatheredDetails',
                'pickupLibraries',
                'pickupLocations',
                'homeLibrary',
                'extraFields',
                'defaultRequiredDate',
                'helpText',
                'helpTextHtml'
            )
        );
        $view->setTemplate('record/illrequest');
        return $view;
    }
}
