<?php

/**
 * Record checkout action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\CheckoutHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Crypt\HMAC;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Service\AuditEventService;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\ILS\Connection;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Session;

use function is_array;

/**
 * Record checkout action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CheckoutAction extends AbstractRecordAction
{
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
     * @param HMAC              $hmac              HMAC generator
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
        protected HMAC $hmac,
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
     * Display self-checkout page or place a checkout.
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

        // TODO Need to understand this logic from HoldAction
        // Holds on API records (as opposed to Solr records; e.g. EDS) may require a different ID.
        // This id can be obtained from the getUniqueIDOverrideForRequest method
        $originalId = $driver->getUniqueID();
        // $id = $driver->tryMethod('getUniqueIDOverrideForRequest', default: $originalId);
        $id = $originalId;

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $barcode = $this->getQueryParam('barcode');
        $checkCheckout = $this->ilsConnection->checkFunction('Checkout', compact('id', 'barcode', 'patron'));
        if (!$checkCheckout) {
            return $this->redirectToRecord();
        }

        if (!$barcode) {
            $this->logger->warning('No barcode with checkout request for id: ' . $id);
            return $this->redirectToRecord();
        }

        // Do we have valid information? The initial display is reached directly from a scanned
        // barcode, so there is no hash to check yet; the confirmation step and the submission
        // both arrive via the hash-validated link/form built from $hashKey below.
        $checkoutHelper = $this->getHelper(CheckoutHelper::class);
        $askConfirm = (bool)$this->getQueryParam('askConfirm');
        if ($askConfirm || $this->isPost()) {
            $gatheredDetails = $checkoutHelper->validateRequest($request, $checkCheckout['HMACKeys']);
            if (!$gatheredDetails) {
                return $this->redirectToRecord();
            }
        } else {
            // TODO Actually do something with the source identifier
            $gatheredDetails = ['id' => $id, 'source' => $driver->getSourceIdentifier()];
        }

        // TODO understand, enable
        // The gatheredDetails['id'] is the original ID, but for API Holds (e.g. EDS)
        // we may need to use the override ID. So only in that case we will set it to the
        // value returned by getUniqueIDOverrideForRequest.
        // if ($originalId != $id && $originalId == $gatheredDetails['id']) {
        //     $gatheredDetails['id'] = $id;
        // }

        $gatheredDetails['barcode'] = $barcode;

        // Block invalid checkouts:
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $validCheckout = $this->ilsConnection->checkCheckoutIsValid(
            $id,
            $gatheredDetails,
            $patron
        );
        if ((is_array($validCheckout) && !$validCheckout['valid']) || !$validCheckout) {
            $flashMessagesHelper->addErrorMessage(
                is_array($validCheckout) ? $validCheckout['status'] : 'checkout_error_blocked'
            );
            return $this->redirectToRecord();
        }

        // Generate the hash key for the links/forms rendered by the templates. The values
        // must match what validateRequest() reads back from the query and route params.
        $hashKey = $this->hmac->generate(
            $checkCheckout['HMACKeys'],
            ['id' => $id, 'source' => $driver->getSourceIdentifier()]
        );
        $templateParams = $this->getTemplateParams();
        $templateParams['hashKey'] = $hashKey;

        // On the initial page display
        if (!$this->isPost()) {
            if ($askConfirm) {
                return $this->renderTemplate($request, $response, $templateParams, 'record/checkout-confirm');
            }

            return $this->renderTemplate($request, $response, $templateParams, 'record/checkout');
        }

        // Ready to process checkout

        // Add patron data to submitted data
        $checkoutDetails = $gatheredDetails + [
            'patron' => $patron,
        ];

        // Attempt to place the checkout:
        $function = (string)$checkCheckout['function'];
        $results = $this->ilsConnection->$function($checkoutDetails);

        // Success: Go to Display Checkouts
        if ($results['success'] ?? false) {
            $checkedOutUrl = $this->routeHelper->getUrlFromRoute('myresearch-checkedout');
            $msg = [
                'html' => true,
                'msg' => 'checkout_place_success_html',
                'tokens' => [
                    '%%url%%' => $checkedOutUrl,
                ],
                // Keeps the lightbox from collapsing the response down to this
                // message alone, which would discard the link out to the user's
                // checked out items.
                'dataset' => ['lightbox-ignore' => '1'],
            ];
            $flashMessagesHelper->addSuccessMessage($msg);
            if (!empty($results['warningMessage'])) {
                $flashMessagesHelper->addWarningMessage($results['warningMessage']);
            }
            $this->sessionViewHelper->put('reset_account_status', true);

            $this->auditEventService->addEvent(
                AuditEventType::ILS,
                AuditEventSubtype::PlaceCheckout,
                $this->getUser(),
                data: [
                    'username' => $patron['cat_username'],
                    'details' => $checkoutDetails,
                ]
            );
            return $this->renderTemplate($request, $response, compact('checkedOutUrl'), 'record/checkout-success');
        } else {
            // Failure: use flash messenger to display messages, stay on
            // the current form.
            if (isset($results['status'])) {
                $flashMessagesHelper->addErrorMessage($results['status']);
            }
            if (isset($results['sysMessage'])) {
                $flashMessagesHelper->addErrorMessage($results['sysMessage']);
            }
            return $this->renderTemplate($request, $response, $templateParams, 'record/checkout');
        }
    }
}
