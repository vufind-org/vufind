<?php

/**
 * Record Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use Finna\Controller\Feature\FinnaRecordPreviewSupportTrait;
use Finna\Controller\Plugin\Preview;
use Finna\Form\Form;

use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends \VuFind\Controller\RecordController
{
    use FinnaRecordControllerTrait;
    use \Finna\Statistics\ReporterTrait;
    use FinnaRecordPreviewSupportTrait;

    /**
     * Create record feedback form and send feedback to correct recipient.
     *
     * @return \Laminas\View\Model\ViewModel
     * @throws \Exception
     */
    public function feedbackAction()
    {
        return $this->getRecordForm(Form::RECORD_FEEDBACK_FORM);
    }

    /**
     * Create repository library request form.
     *
     * @return \Laminas\View\Model\ViewModel
     * @throws \Exception
     */
    public function repositoryLibraryRequestAction()
    {
        $driver = $this->loadRecord();
        $recordPlugin = $this->getViewRenderer()->plugin('record')($driver);
        if (!$recordPlugin->repositoryLibraryRequestEnabled()) {
            throw new \Exception('Repository library request is not enabled');
        }
        if (!$formId = $recordPlugin->getRepositoryLibraryRequestFormId()) {
            throw new \Exception('Repository library request form not configured');
        }
        return $this->getRecordForm($formId);
    }

    /**
     * Create archive request form and send to correct recipient.
     *
     * @return     \Laminas\View\Model\ViewModel
     * @throws     \Exception
     * @deprecated Use ReservationList::placeSingleOrderAction
     */
    public function archiveRequestAction()
    {
        return $this->getRecordForm(Form::ARCHIVE_MATERIAL_REQUEST);
    }

    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        $result = parent::homeAction();
        $this->triggerStatsRecordView($result->driver ?? null);
        $this->addValidationResultMessage();
        return $result;
    }

    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction(
            'Holds',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron,
            ]
        );
        if (!$checkHolds) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Call checkFunction once more now that we have the gathered details since
        // details may affect the fields to display:
        if ($gatheredDetails) {
            $checkHolds = $catalog->checkFunction(
                'Holds',
                [
                    'id' => $driver->getUniqueID(),
                    'patron' => $patron,
                    'details' => $gatheredDetails,
                ]
            );
            if (!$checkHolds) {
                return $this->redirectToRecord();
            }
        }

        // Block invalid requests:
        try {
            $validRequest = $catalog->checkRequestIsValid(
                $driver->getUniqueID(),
                $gatheredDetails,
                $patron
            );
        } catch (\VuFind\Exception\ILS $e) {
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            return $this->redirectToRecord('#top');
        }
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $message = is_array($validRequest)
                ? $validRequest['status'] : 'hold_error_blocked';
            if (is_string($message)) {
                $message = $this->convertToHtmlMessageIfAvailable($message);
            }

            $this->flashMessenger()->addErrorMessage($message);
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $requestGroups = $catalog->checkCapability(
            'getRequestGroups',
            [$driver->getUniqueID(), $patron, $gatheredDetails]
        ) ? $catalog->getRequestGroups(
            $driver->getUniqueID(),
            $patron,
            $gatheredDetails
        ) : [];
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(':', $checkHolds['extraHoldFields']) : [];

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || ($gatheredDetails['level'] != 'copy'
                    || count($requestGroups) > 1));

        $pickupDetails = $gatheredDetails;
        if (
            !$requestGroupNeeded && !empty($requestGroups)
            && count($requestGroups) == 1
        ) {
            // Request group selection is not required, but we have a single request
            // group, so make sure pickup locations match with the group
            $pickupDetails['requestGroupId'] = $requestGroups[0]['id'];
        }
        try {
            $pickup = $catalog->getPickUpLocations($patron, $pickupDetails);
        } catch (\VuFind\Exception\ILS $e) {
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            return $this->redirectToRecord('#top');
        }

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeHold')) {
            // If the form contained a pickup location, request group, start date or
            // required by date, make sure they are valid:
            $validGroup = $this->holds()->validateRequestGroupInput(
                $gatheredDetails,
                $extraHoldFields,
                $requestGroups
            );
            $validPickup = $validGroup && $this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? null,
                $extraHoldFields,
                $pickup
            );
            $dateValidationResults = $this->holds()->validateDates(
                $gatheredDetails['startDate'] ?? null,
                $gatheredDetails['requiredBy'] ?? null,
                $extraHoldFields
            );
            $termsOk = !in_array('acceptTerms', $extraHoldFields)
                || !empty($gatheredDetails['acceptTerms']);
            if (!$validGroup) {
                $this->flashMessenger()
                    ->addErrorMessage('hold_invalid_request_group');
            }
            if (!$validPickup) {
                $this->flashMessenger()->addErrorMessage('hold_invalid_pickup');
            }
            foreach ($dateValidationResults['errors'] as $msg) {
                $this->flashMessenger()->addErrorMessage($msg);
            }
            if (!$termsOk) {
                $this->flashMessenger()->addErrorMessage('must_accept_terms');
            }
            if (
                $termsOk && $validGroup && $validPickup
                && !$dateValidationResults['errors']
            ) {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Pass start date to the driver only if it's in the future:
                if (
                    !empty($gatheredDetails['startDate'])
                    && $dateValidationResults['startDateTS'] < strtotime('+1 day')
                ) {
                    $gatheredDetails['startDate'] = '';
                    $dateValidationResults['startDateTS'] = 0;
                }

                // Add patron data and converted dates to submitted data
                $holdDetails = $gatheredDetails + [
                    'patron' => $patron,
                    'startDateTS' => $dateValidationResults['startDateTS'],
                    'requiredByTS' => $dateValidationResults['requiredByTS'],
                ];

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()->fromRoute('holds-list'),
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    if (!empty($results['warningMessage'])) {
                        $this->flashMessenger()
                            ->addWarningMessage($results['warningMessage']);
                    }
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->addErrorMessage(
                            $this->convertToHtmlMessageIfAvailable(
                                $results['status']
                            )
                        );
                    }
                    if (!empty($results['sysMessage'])) {
                        $this->flashMessenger()->addErrorMessage(
                            $this->convertToHtmlMessageIfAvailable(
                                $results['sysMessage']
                            )
                        );
                    }
                }
            }
        }

        // Set default start date to today:
        $dateConverter = $this->serviceLocator->get(\VuFind\Date\Converter::class);
        $defaultStartDate = $dateConverter->convertToDisplayDate('U', time());

        // Find and format the default required date:
        $defaultRequiredTS = $this->holds()->getDefaultRequiredDate(
            $checkHolds,
            $catalog,
            $patron,
            $gatheredDetails
        );
        $defaultRequiredDate = $defaultRequiredTS
            ? $dateConverter->convertToDisplayDate(
                'U',
                $defaultRequiredTS
            ) : '';
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
                ? false
                : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $config = $this->getConfig();
        $homeLibrary = ($config->Account->set_home_library ?? true)
            ? $this->getUser()->getHomeLibrary() : '';
        $helpText = $checkHolds['helpText'] ?? null;
        // acceptTermsText kept for backward-compatibility:
        $acceptTermsText = $acceptTermsTextHtml
            = $checkHolds['acceptTermsText'] ?? null;

        $view = $this->createViewModel(
            compact(
                'gatheredDetails',
                'pickup',
                'defaultPickup',
                'homeLibrary',
                'extraHoldFields',
                'defaultStartDate',
                'defaultRequiredDate',
                'requestGroups',
                'defaultRequestGroup',
                'requestGroupNeeded',
                'helpText',
                'acceptTermsText',
                'acceptTermsTextHtml'
            )
        );
        $view->setTemplate('record/hold');
        return $view;
    }

    /**
     * Convert a message to a html translation key if a translation with the _html
     * suffix is available.
     *
     * @param string $message Message to translate
     *
     * @return string|array
     */
    protected function convertToHtmlMessageIfAvailable(string $message)
    {
        // Check for _html version of the message:
        $translationEmpty = $this->getViewRenderer()->plugin('translationEmpty');
        if (!$translationEmpty($message . '_html')) {
            $message = [
                'html' => true,
                'msg' => $message . '_html',
            ];
        }
        return $message;
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

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeStorageRetrievalRequest')) {
            if (
                in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms',
                    'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

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
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->addMessage(
                            $results['status'],
                            'error'
                        );
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->storageRetrievalRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get(\VuFind\Date\Converter::class)
            ->convertToDisplayDate('U', $defaultRequired);
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
                'homeLibrary' => $this->getUser()->getHomeLibrary(),
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                // For backward-compatibility:
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null,
                'acceptTermsTextHtml' => $checkRequests['acceptTermsText'] ?? null,
            ]
        );
        $view->setTemplate('record/storageretrievalrequest');
        return $view;
    }

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
            if (
                in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms',
                    'error'
                );
            } else {
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
                    return $this->redirectToRecord('#top');
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
        }

        // Find and format the default required date:
        $defaultRequired = $this->ILLRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get(\VuFind\Date\Converter::class)
            ->convertToDisplayDate('U', $defaultRequired);

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

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickupLibraries' => $pickupLibraries,
                'pickupLocations' => $pickupLocations,
                'homeLibrary' => $this->getUser()->getHomeLibrary(),
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                // For backward-compatibility:
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null,
                'acceptTermsTextHtml' => $checkRequests['acceptTermsText'] ?? null,
            ]
        );
        $view->setTemplate('record/illrequest');
        return $view;
    }

    /**
     * Action for displaying a record validation report.
     *
     * @return mixed
     */
    public function validationReportAction()
    {
        $manager = $this->serviceLocator->get(\Laminas\Session\SessionManager::class);
        $sessionContainer = new \Laminas\Session\Container('RecordPreview', $manager);
        if (null === ($validationReport = $sessionContainer->validation_report ?? null)) {
            $this->flashMessenger()->addErrorMessage('Validation report unavailable');
        }

        return $this->createViewModel(compact('validationReport'));
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processSave()
    {
        $result = parent::processSave();
        if ($this->inLightbox()) {
            if ($this->flashMessenger()->hasErrorMessages()) {
                return $result;
            }
            // Return HTTP 204 so that the modal gets closed.
            // Clear success flashmessages so that they dont get shown in
            // the following modal.
            $this->flashMessenger()->clearMessagesFromNamespace('success');
            $this->flashMessenger()->clearCurrentMessages('success');
            $response = $this->getResponse();
            $response->setStatusCode(204);
            return $response;
        }
        return $result;
    }

    /**
     * Download 3D model
     *
     * @return \Laminas\Http\Response
     */
    public function downloadModelAction()
    {
        $params = $this->params();
        $index = $params->fromQuery('index');
        $format = $params->fromQuery('format');
        $response = $this->getResponse();
        if ($format && $index) {
            $driver = $this->loadRecord();
            $id = $driver->getUniqueID();
            $models = $driver->tryMethod('getModels')[$index]['models'] ?? [];
            $found = array_search('preview', array_column($models, 'type'));
            if (false === $found) {
                $response->setStatusCode(404);
                return $response;
            }
            // Always force preview model to be fetched
            $url = $models[$found]['url'];
            if (!empty($url)) {
                $fileName = urlencode($id) . '-' . $index . '.' . $format;
                $fileLoader = $this->serviceLocator->get(\Finna\File\Loader::class);
                $file = $fileLoader->getFile(
                    $url,
                    $fileName,
                    'Models',
                    'public'
                );
                if (empty($file['result'])) {
                    $response->setStatusCode(500);
                } else {
                    $contentType = '';
                    switch ($format) {
                        case 'gltf':
                            $contentType = 'model/gltf+json';
                            break;
                        case 'glb':
                            $contentType = 'model/gltf+binary';
                            break;
                        default:
                            $contentType = 'application/octet-stream';
                            break;
                    }
                    // Set headers for downloadable file
                    header("Content-Type: $contentType");
                    header(
                        "Content-disposition: attachment; filename=\"{$fileName}\""
                    );
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file['path']));
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($file['path']);
                }
            } else {
                $response->setStatusCode(404);
            }
        } else {
            $response->setStatusCode(400);
        }

        return $response;
    }

    /**
     * Download a file
     *
     * @return \Laminas\Http\Response
     */
    public function downloadFileAction()
    {
        $params = $this->params();
        $index = $params->fromQuery('index');
        $format = $params->fromQuery('format');
        $type = $params->fromQuery('type');

        $response = $this->getResponse();
        if ($type && $format && isset($index)) {
            $driver = $this->loadRecord();
            $id = urlencode($driver->getUniqueID());
            $formedFilename = "$id-$index.$format";
            $representation = [];
            switch ($type) {
                case 'highresimg':
                    $size = $params->fromQuery('size');
                    $key = $params->fromQuery('key', -1);
                    $representations = $driver->tryMethod('getAllImages');
                    $representation
                        = $representations[$index]['highResolution'][$size][$key]
                        ?? [];
                    $formedFilename = "$id-$index-$size.$format";
                    break;
                case 'document':
                    $representations = $driver->tryMethod('getDocuments');
                    $representation = $representations[$index] ?? [];
                    break;
                default:
                    $response->setStatusCode(400);
                    break;
            }

            if ($url = $representation['url'] ?? false) {
                $fileName = $representation['description']
                    ?? $representation['desc']
                    ?? $formedFilename;
                $fileLoader = $this->serviceLocator->get(\Finna\File\Loader::class);
                $success = $fileLoader->proxyFileLoad(
                    $url,
                    $fileName,
                    $format
                );
                if (!$success) {
                    $response->setStatusCode(500);
                }
            } else {
                $response->setStatusCode(404);
            }
        } else {
            $response->setStatusCode(400);
        }

        return $response;
    }

    /**
     * Helper for building a route to a record form
     * (Feedback, Repository library request).
     *
     * @param string $id Form id
     *
     * @return \Laminas\View\Model\ViewModel
     */
    protected function getRecordForm($id)
    {
        $driver = $this->loadRecord();
        return $this->redirect()->toRoute(
            'feedback-form',
            ['id' => $id],
            ['query' => [
                'layout' => $this->getRequest()->getQuery('layout', false),
                'record_id'
                    => $driver->getSourceIdentifier() . '|' . $driver->getUniqueID(),
            ]]
        );
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Special case -- handle lightbox login request if login has already been
        // done
        if (
            $this->inLightbox()
            && $this->params()->fromQuery('catalogLogin', 'false') == 'true'
            && is_array($this->catalogLogin())
        ) {
            $response = $this->getResponse();
            $response->setStatusCode(205);
            return $response;
        }

        return parent::showTab($tab, $ajax);
    }
}
