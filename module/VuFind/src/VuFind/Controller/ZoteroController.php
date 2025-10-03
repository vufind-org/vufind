<?php

/**
 * Zotero Controller.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container as SessionContainer;
use VuFind\Exception\ZoteroException;
use VuFind\Export\Zotero\ZoteroService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Log\LoggerAwareTrait;

use function is_string;

/**
 * Zotero Controller.
 *
 * These actions work as sort of a proxy between VuFind and Zotero's OAuth authorization and v3 API.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ZoteroController extends AbstractBase implements LoggerAwareInterface, TranslatorAwareInterface
{
    use LoggerAwareTrait;
    use TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm               Service manager
     * @param SessionContainer        $sessionContainer Session container
     * @param ZoteroService           $zoteroService    Zotero service
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected SessionContainer $sessionContainer,
        protected ZoteroService $zoteroService
    ) {
        parent::__construct($sm);
    }

    /**
     * Export record(s) to Zotero.
     *
     * @return mixed
     */
    public function exportAction()
    {
        // Store referrer separately for redirecting back before potentially redirecting to login:
        $this->storeReferrer();

        // Require logged-in user so that we can manage the access token:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        if (!($callbackUrl = $this->params()->fromQuery('callback'))) {
            $this->logError('Export callback URL missing');
            throw new \Exception('Export callback URL missing');
        }

        try {
            $result = $this->zoteroService->export($user, $callbackUrl);
            if (is_string($result)) {
                return $this->redirect()->toUrl($result);
            }
            $this->flashMessenger()->addSuccessMessage(
                $this->translate('export_records_complete', ['count' => $result], null, true)
            );
        } catch (ZoteroException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        if ($url = $this->getAndClearReferrer()) {
            return $this->redirect()->toUrl($url);
        }
        return $this->createViewModel();
    }

    /**
     * Handle Zotero OAuth authorization response.
     *
     * @return mixed
     */
    public function authCallbackAction()
    {
        // Require logged-in user so that we can manage the access token:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        try {
            $result = $this->zoteroService->handleAuthCallback($user, $this->params()->fromQuery());
            if (is_string($result)) {
                return $this->redirect()->toUrl($result);
            }
            $this->flashMessenger()->addSuccessMessage(
                $this->translate('export_records_complete', ['count' => $result], null, true)
            );
        } catch (ZoteroException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        if ($url = $this->getAndClearReferrer()) {
            return $this->redirect()->toUrl($url);
        }
        // Fallback just in case we are missing the referrer:
        $view = $this->createViewModel();
        $view->setTemplate('zotero/export');
        return $view;
    }

    /**
     * Store referrer unless we already have one.
     *
     * @return void
     */
    protected function storeReferrer(): void
    {
        if (!$this->sessionContainer->referrer) {
            $referrer = $this->getRequest()->getServer()->get('HTTP_REFERER', null);
            if ($referrer && $this->isLocalUrl($referrer) && !str_contains($referrer, '/Login')) {
                $this->sessionContainer->referrer = $referrer;
            }
        }
    }

    /**
     * Get and clear any stored referrer.
     *
     * @return ?string
     */
    protected function getAndClearReferrer(): ?string
    {
        $referrer = $this->sessionContainer->referrer;
        unset($this->sessionContainer->referrer);
        return $referrer;
    }
}
