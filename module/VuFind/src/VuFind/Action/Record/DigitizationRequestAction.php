<?php

/**
 * Record digitization request action.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\DigitizationRequestsHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
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
 * Record digitization request action.
 *
 * @category VuFind
 * @package  Action
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DigitizationRequestAction extends AbstractRecordAction implements TranslatorAwareInterface
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
     * Place a digitization request.
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
        // Digitization requests on API records (as opposed to Solr records; e.g. EDS) may require a different ID.
        // This id can be obtained from the getUniqueIDOverrideForRequest method
        $originalId = $driver->getUniqueID();
        $id = $driver->tryMethod('getUniqueIDOverrideForRequest', default: $originalId);

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $checkDigitization = $this->ilsConnection->checkFunction('DigitizationRequests', compact('id', 'patron'));
        if (!$checkDigitization) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        $digitizationHelper = $this->getHelper(DigitizationRequestsHelper::class);
        $gatheredDetails = $digitizationHelper->validateRequest($request, $checkDigitization['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // The gatheredDetails['id'] is the original ID, but for API digitization requests (e.g. EDS)
        // we may need to use the override ID. So only in that case we will set it to the
        // value returned by getUniqueIDOverrideForRequest.
        if ($originalId != $id && $originalId == $gatheredDetails['id']) {
            $gatheredDetails['id'] = $id;
        }

        // Block invalid requests:
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $validRequest = $this->ilsConnection->checkDigitizationRequestIsValid($id, $gatheredDetails, $patron);
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $flashMessagesHelper->addErrorMessage(
                is_array($validRequest) ? $validRequest['status'] : 'digitization_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Attach holdings data from requested item for template use
        $requestedItemId = $this->getPostOrQueryParam('item_id');
        if ($requestedItemId) {
            $holdings = $this->ilsConnection->getHolding($gatheredDetails['id'], $patron);
            $gatheredDetails['requestedItem'] = null;
            foreach ($holdings['holdings'] as $item) {
                if ($item['item_id'] === $requestedItemId) {
                    $gatheredDetails['requestedItem'] = $item;
                    break;
                }
            }
        }

        $extraDigitizationFields = $this->explodeSetting($checkDigitization['extraDigitizationFields'] ?? '');

        // Process form submissions if necessary:
        if (null !== $this->getPostParam('placeDigitizationRequest')) {
            // Validate the digitization request fields
            $validationResult = $digitizationHelper->validateDigitizationRequestInput(
                $gatheredDetails,
                $extraDigitizationFields
            );
            $validationErrors = $validationResult['errors'];
            if (!empty($validationErrors)) {
                foreach ($validationErrors as $msg) {
                    $flashMessagesHelper->addErrorMessage($msg);
                }
            } else {
                // Add patron data and converted dates to submitted data
                $details = $gatheredDetails + [
                    'patron' => $patron,
                    'requiredByTS' => $validationResults['requiredByTS'],
                ];

                // Attempt to place the digitization request:
                $function = (string)$checkDigitization['function'];
                $results = $this->ilsConnection->$function($details);

                // Success: Go to my digitization requests
                if ($results['success'] ?? false) {
                    $msg = [
                        'html' => true,
                        'msg' => 'digitization_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->routeHelper->getUrlFromRoute('myresearch-digitizationrequests'),
                        ],
                    ];
                    $flashMessagesHelper->addSuccessMessage($msg);
                    if (!empty($results['warningMessage'])) {
                        $flashMessagesHelper->addWarningMessage($results['warningMessage']);
                    }
                    $this->sessionViewHelper->put('reset_account_status', true);

                    $this->auditEventService->addEvent(
                        AuditEventType::ILS,
                        AuditEventSubtype::PlaceDigitizationRequest,
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
        $defaultRequiredDate = '';
        if (
            in_array('requiredByDate', $extraDigitizationFields)
            || in_array('requiredByDateOptional', $extraDigitizationFields)
        ) {
            $defaultRequiredTS = $digitizationHelper->getDefaultRequiredDate(
                $checkDigitization,
                $this->ilsConnection,
                $patron,
                $gatheredDetails
            );
            $defaultRequiredDate = $defaultRequiredTS
                ? $this->dateConverter->convertToDisplayDate('U', $defaultRequiredTS)
                : '';
        }

        // helpText is only for backward compatibility with legacy code:
        $helpText = $helpTextHtml = $checkDigitization['helpText'];

        // get defaults
        if (!isset($gatheredDetails['digitizationType'])) {
            $gatheredDetails['digitizationType'] = $this->config['Catalog']['defaultDigitizationType'] ?? 'full';
        }
        if (!isset($gatheredDetails['partialDigitizationType'])) {
            $gatheredDetails['partialDigitizationType'] = $this->config['Catalog']['defaultPartialDigitizationType'] ?? 'full';
        }

        $templateParams = $this->getTemplateParams(
            compact(
                'gatheredDetails',
                'extraDigitizationFields',
                'defaultRequiredDate',
                'helpText',
                'helpTextHtml'
            )
        );
        return $this->renderTemplate($request, $response, $templateParams, 'record/digitizationrequest');
    }
}
