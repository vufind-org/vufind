<?php

/**
 * Interlibrary loan requests list action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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

namespace VuFind\Action\MyResearch;

use Laminas\Http\Response;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\IllRequestsHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

use function is_array;

/**
 * Interlibrary loan requests list action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class IllRequestsAction extends AbstractMyResearchAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager        Authentication manager
     * @param FollowupHelper                  $followupHelper     Followup helper
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService User session database service
     * @param AuditEventServiceInterface      $auditEventService  Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper    Server URL helper
     * @param Mailer                          $mailer             Mailer
     * @param SessionManager                  $sessionManager     Session manager
     * @param Connection                      $ilsConnection      ILS connection
     * @param array                           $config             VuFind configuration
     * @param RecordsHelper                   $ilsRecordsHelper   ILS records helper
     */
    public function __construct(
        AuthManager $authManager,
        FollowupHelper $followupHelper,
        EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        UserSessionPersistenceInterface $userSessionService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        ServerUrlHelper $serverUrlHelper,
        Mailer $mailer,
        SessionManager $sessionManager,
        Connection $ilsConnection,
        #[Autowire(config: 'config')]
        array $config,
        protected RecordsHelper $ilsRecordsHelper,
    ) {
        parent::__construct(
            $authManager,
            $followupHelper,
            $emailAuthenticator,
            $userSessionService,
            $auditEventService,
            $serverUrlHelper,
            $mailer,
            $sessionManager,
            $ilsConnection,
            $config
        );
    }

    /**
     * Display interlibrary loan requests.
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

        // Process cancel requests if necessary:
        $illRequestsHelper = $this->getHelper(IllRequestsHelper::class);
        $cancelStatus = $this->ilsConnection->checkFunction('cancelILLRequests', compact('patron'));
        $cancelResults = $cancelStatus
            ? $illRequestsHelper->cancelIllRequests($request, $response, $this->ilsConnection, $patron)
            : [];
        // Check if we need to confirm cancellation:
        if ($cancelResults instanceof ResponseInterface) {
            return $cancelResults;
        }
        if ($cancelResults) {
            $this->auditEventService->addEvent(
                AuditEventType::ILS,
                AuditEventSubtype::CancelILLRequests,
                $this->authManager->getUserObject(),
                data: [
                    'username' => $patron['cat_username'],
                    'results' => $cancelResults,
                ]
            );
        }

        $templateParams = [
            'cancelResults' => $cancelResults,
            // By default, assume we will not need to display a cancel form:
            'cancelForm' => false,
        ];

        // Get request details:
        $result = $this->ilsConnection->getMyILLRequests($patron);
        $driversNeeded = [];
        $illRequestsHelper->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $illRequestsHelper->addCancelDetails(
                $this->ilsConnection,
                $current,
                $cancelStatus,
                $patron
            );
            if (
                $cancelStatus
                && $cancelStatus['function'] !== 'getCancelILLRequestLink'
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $templateParams['cancelForm'] = true;
            }

            $driversNeeded[] = $current;
        }

        $templateParams['recordList'] = $this->ilsRecordsHelper->getDrivers($driversNeeded);
        $templateParams['accountStatus'] = $this->ilsRecordsHelper->collectRequestStats($templateParams['recordList']);

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
