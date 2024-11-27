<?php

/**
 * Turnstile Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFindHttp\HttpServiceAwareInterface;
use VuFindHttp\HttpServiceAwareTrait;

/**
 * Controller Cloudflare Turnstile access checks.
 *
 * @category VuFind
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class TurnstileController extends AbstractBase implements
    HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use HttpServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Present the Turnstile challenge to the user
     *
     * @return mixed
     */
    public function challengeAction()
    {
        $context = json_decode(base64_decode($this->params()->fromQuery('context')), true);

        $yamlReader = $this->getService(\VuFind\Config\YamlReader::class);
        $config = $yamlReader->get('RateLimiter.yaml');
        $context['siteKey'] = $config['Turnstile']['siteKey'];
        $context['jsLibraryUrl'] = $config['Turnstile']['jsLibraryUrl'] ??
            'https://challenges.cloudflare.com/turnstile/v0/api.js';

        $this->layout()->searchbox = false;
        return $this->createViewModel($context);
    }

    /**
     * Verify the Turnstile widget result against the Turnstile backend
     *
     * @return mixed
     */
    public function verifyAction()
    {
        $token = $this->params()->fromPost('token');
        $policyId = $this->params()->fromPost('policyId');
        $destination = $this->params()->fromPost('destination');

        // Call the Turnstile verify API to validate the token
        $yamlReader = $this->getService(\VuFind\Config\YamlReader::class);
        $config = $yamlReader->get('RateLimiter.yaml');
        $secretKey = $config['Turnstile']['secretKey'];
        $url = $config['Turnstile']['verifyUrl'] ??
            'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $body = [
            'secret' => $secretKey,
            'response' => $token,
        ];
        $response = $this->httpService->post(
            $url,
            json_encode($body),
            'application/json'
        );

        if ($response->isOk()) {
            $responseData = json_decode($response->getBody(), true);
            $success = $responseData['success'];
        } else {
            // Unexpected error.  Treat as a positive result, since it's not the user's fault.
            $this->logWarning('Verification process failed, allowing traffic: '
                . $response->getStatusCode() . $response->getBody());
            $success = true;
        }

        // Save the Turnstile result for future requests
        $rateLimiterManager = $this->getService(\VuFind\RateLimiter\RateLimiterManager::class);
        $rateLimiterManager->setTurnstileResult(
            $policyId,
            $this->event->getRequest()->getServer('REMOTE_ADDR'),
            $success
        );

        // Either way, return a http redirect to the referrer page.
        return $this->redirect()->toUrl($destination);
    }
}
