<?php

/**
 * Turnstile challenge action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Turnstile;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turnstile challenge action.
 *
 * @category VuFind
 * @package  Action
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ChallengeAction extends AbstractTurnstileAction
{
    /**
     * Present the Turnstile challenge to the user.
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
        if (!($contextParam = $this->getQueryParam('context'))) {
            throw new Exception('Context parameter missing');
        }
        $context = json_decode(base64_decode($contextParam), true);
        $context['siteKey'] = $this->getSiteKey();
        $context['jsLibraryUrl'] = $this->rateLimiterConfig['Turnstile']['jsLibraryUrl']
            ?? 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        $context['hash'] = $this->hmac->generate($this->hashKeys, $context);

        $this->globalsContainer['searchbox'] = false;
        return $this->renderTemplate($request, $response, $context);
    }
}
