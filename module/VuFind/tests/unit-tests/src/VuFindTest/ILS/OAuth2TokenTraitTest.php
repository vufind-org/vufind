<?php

/**
 * Class OAuth2TokenTraitTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2021.
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
 * @category CPK-vufind-6
 * @package  VuFindTest\ILS
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

declare(strict_types=1);

namespace VuFindTest\ILS;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\ILS\Driver\XCNCIP2;

/**
 * Class OAuth2TokenTraitTest
 *
 * @category VuFind
 * @package  VuFindTest\ILS
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OAuth2TokenTraitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Tested service
     *
     * @var XCNCIP2
     */
    protected $driver;

    /**
     * Test for getNewOauth2Token
     *
     * @return void
     * @throws \VuFind\Exception\ILS
     */
    public function testGetNewOAuth2Token()
    {
        $response = <<<END
            HTTP/1.1 200 OK
            Content-Type: application/json
            Date: Wed, 18 Mar 2015 11:49:40 GMT

            {"access_token": "some_access_token","expires_in": 3600,"token_type": "Bearer"}
            END;

        $this->configureDriver();
        $this->mockResponse($response);
        $token = $this->driver->getNewOAuth2Token(
            'https://www.example.com/api/v1/oauth/token',
            'some_client',
            'some_secret'
        );
        $this->assertEquals('Bearer some_access_token', $token->getHeaderValue());

        $response = <<<END
            HTTP/1.1 403 Forbidden
            Content-Type: application/json
            Date: Wed, 18 Mar 2015 11:49:40 GMT
            END;
        $this->configureDriver();
        $this->mockResponse($response);
        $this->expectExceptionMessage('Problem getting authorization token: Bad status code returned');
        $this->driver->getNewOAuth2Token('https://www.example.com/api/v1/oauth/token', 'some_client', 'some_secret');

        $response = <<<END
            HTTP/1.1 200 OK
            Content-Type: application/json
            Date: Wed, 18 Mar 2015 11:49:40 GMT
            END;
        $this->configureDriver();
        $this->mockResponse($response);
        $this->expectExceptionMessage('Problem getting authorization token: Empty data');
        $this->driver->getNewOAuth2Token('https://www.example.com/api/v1/oauth/token', 'some_client', 'some_secret');
    }

    /**
     * Mock fixture as HTTP client response
     *
     * @param string|array|null $responseData String or array of string which
     * with raw http response
     *
     * @return void
     */
    protected function mockResponse($responseData)
    {
        $adapter = new TestAdapter();
        if (!empty($responseData)) {
            $responseData = (array)$responseData;
            $response = HttpResponse::fromString($responseData[0]);
            $adapter->setResponse($response);
            array_shift($responseData);
            foreach ($responseData as $r) {
                $response = HttpResponse::fromString($r);
                $adapter->addResponse($response);
            }
        }
        $httpService = new \VuFindHttp\HttpService();
        $httpService->setDefaultAdapter($adapter);
        $this->driver->setHttpService($httpService);
    }

    /**
     * Basic configuration for tested service
     *
     * @return void
     */
    public function configureDriver(): void
    {
        $this->driver = new XCNCIP2(new \VuFind\Date\Converter());
    }
}
