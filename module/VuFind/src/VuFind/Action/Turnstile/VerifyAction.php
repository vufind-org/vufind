<?php

/**
 * Turnstile verify action.
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
use VuFind\ActionHelper\RedirectHelper;

/**
 * Turnstile verify action.
 *
 * @category VuFind
 * @package  Action
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class VerifyAction extends AbstractTurnstileAction
{
    /**
     * Verify the Turnstile widget result against the Turnstile backend.
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
        $token = $this->getPostParam('token');
        $policyId = $this->getPostParam('policyId');
        $destination = $this->getPostParam('destination');
        $priorHash = $this->getPostParam('hash');
        $siteKey = $this->getSiteKey();

        $newHash = $this->hmac->generate($this->hashKeys, compact($this->hashKeys));
        if ($newHash != $priorHash) {
            throw new Exception('Wrong hash value used in Turnstile verification.');
        }

        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $this->turnstile->validateAndCacheResult($token, $policyId, $ipAddress);

        // Either way, return an http redirect to the referrer page:
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $destination);
    }
}
