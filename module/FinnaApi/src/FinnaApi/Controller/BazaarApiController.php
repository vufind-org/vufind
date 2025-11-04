<?php

/**
 * Bazaar API Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Aida Luuppala <aida.luuppala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace FinnaApi\Controller;

use Laminas\Http\Response;
use VuFind\Db\Service\AuthHashServiceInterface;
use VuFindApi\Controller\ApiController;
use VuFindApi\Controller\ApiInterface;
use VuFindApi\Controller\ApiTrait;

/**
 * Bazaar API Controller
 *
 * Controls the Bazaar API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Aida Luuppala <aida.luuppala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class BazaarApiController extends ApiController implements ApiInterface
{
    use ApiTrait;

    /**
     * Save request data to auth_hash table.
     *
     * @return Response
     */
    public function browseAction(): Response
    {
        try {
            $payload = $this->getRequest()->getContent();
            $authenticationData = $this->authenticateBazaarRequest($payload);
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 401, $e->getMessage());
        }
        try {
            $payload = json_decode($payload, true);
            if (null === $payload) {
                throw new \Exception('Invalid request payload');
            }
            $callbackUrl
                = $this->getParamUrlValue('add_resource_callback_url', $payload);
            $cancelUrl = $this->getParamUrlValue('cancel_url', $payload);
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 400, $e->getMessage());
        }

        $csrf = $this->serviceLocator->get(\VuFind\Validator\CsrfInterface::class);
        $hash = $csrf->getHash(true);
        $data = [
            'client_id' => $authenticationData['clientId'],
            'add_resource_callback_url' => $callbackUrl,
            'cancel_url' => $cancelUrl,
        ];
        $authHashService = $this->getDbService(AuthHashServiceInterface::class);
        $authHash = $authHashService->createEntity()
            ->setHash($hash)
            ->setHashType('bazaar')
            ->setData(json_encode($data, JSON_UNESCAPED_UNICODE));
        $authHashService->persistEntity($authHash);

        $browseUrl = $this->getServerUrl('bazaar-home')
            . '?hash=' . urlencode($hash);
        $response = [
            'browse_url' => $browseUrl,
        ];

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Get API specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getApiSpecFragment()
    {
        return $this->getViewRenderer()->render('bazaarapi/openapi');
    }

    /**
     * Authenticate a Bazaar API request.
     *
     * @param string $payload Payload to validate
     *
     * @return array Array containing authentication data
     * @throws \Exception If authentication fails
     */
    protected function authenticateBazaarRequest(string $payload): array
    {
        $authHeader = $this->getRequest()->getHeader('Authentication');
        if (false === $authHeader) {
            throw new \Exception('Missing authentication header');
        }

        $matches = [];
        $matched = preg_match(
            '/^BAZAAR (.+):(.+)$/',
            $authHeader->getFieldValue(),
            $matches
        );
        if (!$matched) {
            throw new \Exception('Invalid authentication header');
        }
        $clientId = $matches[1];
        $hashToken = $matches[2];

        $secretKey = $this->getConfig()->Bazaar->client[$clientId];
        if (!$secretKey) {
            throw new \Exception('Unknown client id');
        }

        $correctHash = hash_hmac('sha256', $payload, $secretKey);
        if ($hashToken !== $correctHash) {
            throw new \Exception('Invalid hash token');
        }

        return compact('clientId');
    }

    /**
     * Returns a validated parameter URL value from the request payload.
     *
     * @param string $name    Parameter name
     * @param array  $payload Request payload
     *
     * @return string
     * @throws \Exception
     */
    protected function getParamUrlValue(string $name, array $payload): string
    {
        if (!($value = $payload[$name] ?? null)) {
            throw new \Exception('Missing parameter: ' . $name);
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid URL in parameter: ' . $name);
        }
        return $value;
    }
}
