<?php

/**
 * Abstract base class for Zotero actions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Zotero;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ContextHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Export\Zotero\ZoteroService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for Zotero actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractZoteroAction extends AbstractTemplateRenderingAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Session container.
     *
     * @var ?Container
     */
    protected ?Container $sessionContainer = null;

    /**
     * Constructor.
     *
     * @param SessionManager $sessionManager Session manager
     * @param AuthManager    $authManager    Authentication manager
     * @param ZoteroService  $zoteroService  Zotero service
     */
    #[Autowire]
    public function __construct(
        protected SessionManager $sessionManager,
        protected AuthManager $authManager,
        protected ZoteroService $zoteroService,
    ) {
        parent::__construct();
    }

    /**
     * Get session container for referrer information.
     *
     * @return Container
     */
    protected function getSessionContainer(): Container
    {
        if (null === $this->sessionContainer) {
            $this->sessionContainer = new Container('zotero', $this->sessionManager);
        }
        return $this->sessionContainer;
    }

    /**
     * Store referrer unless we already have one.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return void
     */
    protected function storeReferrer(ServerRequestInterface $request): void
    {
        $sessionContainer = $this->getSessionContainer();
        if (!$sessionContainer->referrer) {
            $referrer = $this->getHelper(ContextHelper::class)->getReferrer($request);
            if ($referrer && !str_contains($referrer, '/Login')) {
                $sessionContainer->referrer = $referrer;
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
        $sessionContainer = $this->getSessionContainer();
        $referrer = $sessionContainer->referrer;
        unset($this->sessionContainer->referrer);
        return $referrer;
    }
}
