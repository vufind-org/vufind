<?php

/**
 * Webhook connection class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Connection
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Connection;

use Psr\Log\LoggerAwareInterface;

/**
 * Webhook connection class.
 *
 * @category VuFind
 * @package  Connection
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Webhook implements
    \VuFindHttp\HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Send a webhook post to the given URL. Log but do not throw any errors.
     *
     * @param string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float $timeout Request timeout in seconds (overrides configuration)
     *
     * @return void
     */
    public function post(string $url, ?float $timeout = null): void
    {
        $client = $this->httpService->createClient($url, 'POST', $timeout);

        try {
            $response = $client->send();
            if (!$response->isSuccess()) {
                $this->logError(
                    "Failed to post to webhook. Code: {$response->getStatusCode()}, body: {$response->getBody()}"
                );
            }
        } catch (\Exception $e) {
            $this->logError('Failed to post webhook. Unexpected ' . $e::class . ': ' . (string)$e);
        }
    }
}
