<?php

/**
 * Modified BeSimple SoapClient for Zend HTTP Client
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Finna\ILS\Driver;

use VuFindHttp\HttpServiceInterface;

/**
 * Modified SoapClient that uses a cURL style proxy wrapper that in turn uses Zend
 * HTTP Client for all underlying HTTP requests in order to use proper authentication
 * for all requests. This also adds NTLM support. A custom WSDL downloader resolves
 * remote xsd:includes and allows caching of all remote referenced items.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andreas Schamberger <mail@andreass.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class ProxySoapClient extends \BeSimple\SoapClient\SoapClient
{
    /**
     * HTTP Service
     *
     * @var HttpServiceInterface
     */
    protected $httpService;

    /**
     * Create the Curl client
     *
     * @param array $options Client options
     *
     * @return Curl
     */
    protected function createCurlClient(array $options = [])
    {
        return new ProxyCurl($this->httpService, $options);
    }

    /**
     * Constructor.
     *
     * @param HttpServiceInterface $httpService HTTP Service
     * @param string               $wsdl        WSDL file
     * @param array(string=>mixed) $options     Options array
     *
     * @throws \SoapFault
     */
    public function __construct(
        HttpServiceInterface $httpService,
        $wsdl,
        array $options = []
    ) {
        $this->httpService = $httpService;
        parent::__construct($wsdl, $options);
    }
}
