<?php

/**
 * MyResearch profile action.
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
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

use function is_array;

/**
 * MyResearch profile action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ProfileAction extends AbstractMyResearchAction
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
     * @param ILSAuthenticator                $ilsAuthenticator   ILS authenticator
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
        protected ILSAuthenticator $ilsAuthenticator,
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
     * Display user profile.
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
        // Not logged in?  Force user to log in:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Begin building template params:
        $templateParams = compact('user');

        $allowHomeLibrary = $this->config['Account']['set_home_library'] ?? true;

        $patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response, false);
        if (is_array($patron)) {
            // Process home library parameter (if present and allowed):
            $homeLibrary = $this->getPostParam('home_library');
            if ($allowHomeLibrary && null !== $homeLibrary) {
                // Note: for backward compatibility user's home library defaults to empty string indicating system
                // default. We also allow null for "Always ask me", and the choice is encoded as ' ** ' on the form:
                if (' ** ' === $homeLibrary) {
                    $homeLibrary = null;
                }
                $this->ilsAuthenticator->updateUserHomeLibrary($user, $homeLibrary);
                $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('profile_update');
            }

            // Obtain user information from ILS:
            $this->getHelper(FlashMessagesHelper::class)->addAccountBlocks($this->ilsConnection, $patron);
            $profile = $this->ilsConnection->getMyProfile($patron);
            $profile['home_library'] = $allowHomeLibrary
                ? $user->getHomeLibrary()
                : ($profile['home_library'] ?? '');
            $templateParams['profile'] = $profile;
            $pickup = $defaultPickupLocation = null;
            try {
                $pickup = $this->ilsConnection->getPickUpLocations($patron);
                $defaultPickupLocation = $this->ilsConnection->getDefaultPickUpLocation($patron);
            } catch (\Exception $e) {
                // Do nothing; if we're unable to load information about pickup
                // locations, they are not supported and we should ignore them.
            }

            // Set things up differently depending on whether or not the user is
            // allowed to set a home library.
            if ($allowHomeLibrary) {
                $templateParams['pickup'] = $pickup;
                $templateParams['defaultPickupLocation'] = $defaultPickupLocation;
            } elseif ($pickup) {
                foreach ($pickup as $lib) {
                    if ($defaultPickupLocation == $lib['locationID']) {
                        $templateParams['preferredLibraryDisplay'] = $lib['locationDisplay'];
                        break;
                    }
                }
            }

            // Add proxy details if available
            if ($this->ilsConnection->checkCapability('getProxiedUsers', [$patron])) {
                $templateParams['proxiedUsers'] = $this->ilsConnection->getProxiedUsers($patron);
            }
            if ($this->ilsConnection->checkCapability('getProxyingUsers', [$patron])) {
                $templateParams['proxyingUsers'] = $this->ilsConnection->getProxyingUsers($patron);
            }
        } elseif ($patron instanceof Response) {
            return $patron;
        } else {
            $templateParams['showCatalogLoginForm'] = true;
        }

        $templateParams['accountDeletion'] = (bool)($this->config['Authentication']['account_deletion'] ?? false);

        $this->addPendingEmailChangeMessage($user);

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
