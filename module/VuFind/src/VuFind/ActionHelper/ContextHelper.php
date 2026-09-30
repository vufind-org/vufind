<?php

/**
 * Action helper for context information.
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

use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for context information.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ContextHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param UrlHelper $urlHelper URL helper
     */
    public function __construct(
        #[Autowire(container: HelperPluginManager::class)]
        protected UrlHelper $urlHelper,
    ) {
    }

    /**
     * Are we currently in a lightbox context?
     *
     * @param ServerRequestInterface $request Request
     *
     * @return bool
     */
    public function inLightbox(ServerRequestInterface $request): bool
    {
        $layout = $request->getParsedBody()['layout'] ?? $request->getQueryParams()['layout'] ?? null;
        return
            'lightbox' === $layout
            || 'layout/lightbox' === $request->getAttribute('view-model')?->getTemplate();
    }

    /**
     * Get referrer of a request.
     *
     * @param ServerRequestInterface $request               Request
     * @param bool                   $checkLightboxReferrer Check lightbox referrer?
     * @param bool                   $allowExternalUrls     Allow external URLs too?
     * @param bool                   $allowCurrentUrl       Allow current URL as referrer?
     *
     * @return ?string
     */
    public function getReferrer(
        ServerRequestInterface $request,
        bool $checkLightboxReferrer = false,
        bool $allowExternalUrls = false,
        bool $allowCurrentUrl = true
    ): ?string {
        // lbreferer is the stored current url of the lightbox which overrides the url from the request when present
        $referrer = $checkLightboxReferrer ? ($request->getQueryParams()['lbreferer'] ?? null) : null;
        $referrer ??= $request->getHeader('Referer')[0] ?? null;
        if ($referrer && !$allowExternalUrls && !$this->urlHelper->isLocalUrl($referrer)) {
            $referrer = null;
        }
        // Check that the referrer is not current URL if not allowed:
        if (!$allowCurrentUrl && (string)$request->getUri() === $referrer) {
            return null;
        }

        // Return a non-empty string or null:
        return $referrer ?: null;
    }
}
