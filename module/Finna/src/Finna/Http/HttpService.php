<?php

/**
 * VuFind HTTP service class file.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Http
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace Finna\Http;

/**
 * VuFind HTTP service.
 *
 * @category VuFind
 * @package  Http
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class HttpService extends \VuFindHttp\HttpService
{
    /**
     * Return a new HTTP client.
     *
     * @param string $url     Target URL
     * @param string $method  Request method
     * @param float  $timeout Request timeout in seconds
     *
     * @return \Laminas\Http\Client
     */
    public function createClient($url = null,
        $method = \Laminas\Http\Request::METHOD_GET, $timeout = null
    ) {
        $client = new Client();
        $client->setMethod($method);
        if (!empty($this->defaults)) {
            $client->setOptions($this->defaults);
        }
        if (null !== $this->defaultAdapter) {
            $client->setAdapter($this->defaultAdapter);
        }
        if (null !== $url) {
            $client->setUri($url);
        }
        if ($timeout) {
            $client->setOptions(['timeout' => $timeout]);
        }
        $this->proxify($client);
        return $client;
    }
}
