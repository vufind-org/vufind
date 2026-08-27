<?php

/**
 * Record storage retrieval request action.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\StorageRetrievalRequestsHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Service\AuditEventService;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ILS\Connection;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Session;

use function in_array;
use function is_array;

/**
 * Record storage retrieval request action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class StorageRetrievalRequestAction extends AbstractRecordAction implements TranslatorAwareInterface
{
    use ExplodeSettingTrait;
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param SearchMemory      $searchMemory      Search memory
     * @param TabManager        $tabManager        Tab manager
     * @param AuthManager       $authManager       Authentication manager
     * @param RecordLoader      $recordLoader      Record loader
     * @param RecordRouter      $recordRouter      Record router
     * @param ResultScroller    $resultScroller    Result scroller
     * @param array             $config            VuFind configuration
     * @param Connection        $ilsConnection     ILS connection
     * @param AuditEventService $auditEventService Audit event service
     * @param DateConverter     $dateConverter     Date converter
     * @param Session           $sessionViewHelper Session view helper
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        protected Connection $ilsConnection,
        protected AuditEventService $auditEventService,
        protected DateConverter $dateConverter,
        #[Autowire(container: 'ViewHelperManager')]
        protected Session $sessionViewHelper,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Place a storage retrieval request.
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
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $checkRequests = $this->ilsConnection->checkFunction(
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
        $storageRetrievalRequestsHelper = $this->getHelper(StorageRetrievalRequestsHelper::class);
        $gatheredDetails = $storageRetrievalRequestsHelper->validateRequest(
            $request,
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $this->ilsConnection->checkStorageRetrievalRequestIsValid(
            $driver->getUniqueID(),
            $gatheredDetails,
            $patron
        );
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $flashMessagesHelper->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status']
                    : 'storage_retrieval_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $pickup = $this->ilsConnection->getPickUpLocations($patron, $gatheredDetails);
        $extraFields = $this->explodeSetting($checkRequests['extraFields'] ?? '');

        // Check that there are pick up locations to choose from if the field is
        // required:
        if (in_array('pickUpLocation', $extraFields) && !$pickup) {
            $flashMessagesHelper->addErrorMessage('No pickup locations available');
            return $this->redirectToRecord('#top');
        }

        // Process form submissions if necessary:
        if (null !== $this->getPostParam('placeStorageRetrievalRequest')) {
            // If we made it this far, we're ready to place the hold;
            // if successful, we will redirect and can stop here.

            // Check that any pick up location is valid:
            $validPickup = $storageRetrievalRequestsHelper->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? null,
                $extraFields,
                $pickup
            );
            if (!$validPickup) {
                $flashMessagesHelper->addErrorMessage('storage_retrieval_request_invalid_pickup');
            } else {
                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $this->ilsConnection->$function($details);

                // Success: Go to Display Storage Retrieval Requests
                if ($results['success'] ?? false) {
                    $msg = [
                        'html' => true,
                        'msg' => 'storage_retrieval_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->routeHelper->getUrlFromRoute('myresearch-storageretrievalrequests'),
                        ],
                    ];
                    $flashMessagesHelper->addSuccessMessage($msg);
                    $this->sessionViewHelper->put('reset_account_status', true);

                    $this->auditEventService->addEvent(
                        AuditEventType::ILS,
                        AuditEventSubtype::PlaceStorageRetrievalRequest,
                        $this->getUser(),
                        data: [
                            'username' => $patron['cat_username'],
                            'details' => $details,
                        ]
                    );

                    return $this->redirectToRecord(
                        $this->getHelper(ContextHelper::class)->inLightbox($request) ? '?layout=lightbox' : ''
                    );
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $flashMessagesHelper->addErrorMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $flashMessagesHelper->addErrorMessage($results['sysMessage']);
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequiredDate = $storageRetrievalRequestsHelper
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequiredDate = $this->dateConverter->convertToDisplayDate('U', $defaultRequiredDate);
        try {
            $defaultPickup
                = $this->ilsConnection->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $homeLibrary = ($this->config['Account']['set_home_library'] ?? true) ? $this->getUser()->getHomeLibrary() : '';
        // helpText is only for backward compatibility with legacy code:
        $helpText = $helpTextHtml = $checkRequests['helpText'];

        $templateParams = $this->getTemplateParams(
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

        return $this->renderTemplate($request, $response, $templateParams, 'record/storageretrievalrequest');
    }
}
