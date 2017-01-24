<?php
/**
 * Recaptcha request wrapper for Zend\Http\Client
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Service;
use LosReCaptcha\Service\Request\Parameters;
use Zend\Http\Client;

/**
 * Recaptcha request wrapper for Zend\Http\Client
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptchaRequest implements \LosReCaptcha\Service\Request\RequestInterface
{
    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * Constructor
     *
     * @param Client $client HTTP client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ? $client : new Client();
    }

    /**
     * Submit ReCaptcha API request, return response body.
     *
     * @param Parameters $params ReCaptcha parameters
     *
     * @return string
     */
    public function send(Parameters $params)
    {
        $this->client->setUri(ReCaptcha::VERIFY_SERVER);
        $this->client->setRawBody($params->toQueryString());
        $this->client->setEncType('application/x-www-form-urlencoded');
        $result = $this->client->setMethod('POST')->send();
        return $result ? $result->getBody() : null;
    }
}
