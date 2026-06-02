<?php

/**
 * Action helper for bulk actions.
 *
 * PHP version 8
 *
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Config\ConfigManager;
use VuFind\Export;
use VuFind\Feature\BulkActionTrait;
use VuFind\Http\RouteHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

/**
 * Action helper for bulk actions.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class BulkActionHelper implements HelperInterface
{
    use BulkActionTrait;

    /**
     * Constructor.
     *
     * @param FlashMessengerInterface $flashMessenger Flash messenger
     * @param SessionManager          $sessionManager Session manager
     * @param RouteHelper             $routeHelper    Route helper
     * @param Export                  $export         Export handler
     * @param ConfigManager           $configManager  Config manager
     * @param ContextHelper           $contextHelper  Context helper
     * @param UrlHelper               $urlHelper      URL helper
     * @param RedirectHelper          $redirectHelper Redirect helper
     */
    public function __construct(
        protected FlashMessengerInterface $flashMessenger,
        protected SessionManager $sessionManager,
        protected RouteHelper $routeHelper,
        protected Export $export,
        protected ConfigManager $configManager,
        #[Autowire(container: HelperPluginManager::class)]
        protected ContextHelper $contextHelper,
        #[Autowire(container: HelperPluginManager::class)]
        protected UrlHelper $urlHelper,
        #[Autowire(container: HelperPluginManager::class)]
        protected RedirectHelper $redirectHelper,
    ) {
    }

    /**
     * Redirect to the page we were on when the bulk action was initiated.
     *
     * @param ServerRequestInterface $request            Request
     * @param ResponseInterface      $response           Response
     * @param string                 $flashNamespace     Namespace for flash message ('success', 'info', 'warning',
     * 'error', or null for none)
     * @param string|array|null      $flashMsg           Flash message to set (ignored if namespace null)
     * @param bool                   $redirectInLightbox If the redirects are performed even if in lightbox
     *
     * @return ?ResponseInterface
     */
    public function redirectToSource(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $flashNamespace = null,
        string|array|null $flashMsg = null,
        bool $redirectInLightbox = false
    ): ?ResponseInterface {
        // Set flash message if requested:
        if (null !== $flashNamespace && !empty($flashMsg)) {
            match ($flashNamespace) {
                'error' => $this->flashMessenger->addErrorMessage($flashMsg),
                'info' => $this->flashMessenger->addInfoMessage($flashMsg),
                'success' => $this->flashMessenger->addSuccessMessage($flashMsg),
                'warning' => $this->flashMessenger->addWarningMessage($flashMsg),
                default => throw new \InvalidArgumentException("Unknown flash message namespace '$flashNamespace'")
            };
        }

        // Do not redirect if in lightbox and not required:
        if (
            !($request->getParsedBody()['redirectInLightbox'] ?? false)
            && !$redirectInLightbox
            && $this->contextHelper->inLightbox($request)
        ) {
            return null;
        }

        // If we entered the controller in the expected way (i.e. via the
        // myresearchbulk action), we should have a source set in the followup
        // memory. If that's missing for some reason, just forward to MyResearch.
        $session = $this->getCartFollowupSession();
        if (isset($session->url)) {
            $target = $session->url;
            unset($session->url);
        } else {
            $target = $this->routeHelper->getUrlFromRoute('myresearch-home');
        }
        return $this->redirectHelper->redirectToUrl($response, $target);
    }

    /**
     * Get cart followup session container.
     *
     * @return Container
     */
    public function getCartFollowupSession(): Container
    {
        return new \Laminas\Session\Container('cart_followup', $this->sessionManager);
    }
}
