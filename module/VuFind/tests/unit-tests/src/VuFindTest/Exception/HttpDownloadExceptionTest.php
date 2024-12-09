<?php

/**
 * HttpDownloadException Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Tests
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Exception;

use Laminas\Http\Headers;
use VuFind\Exception\HttpDownloadException;

/**
 * HttpDownloadException Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HttpDownloadExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the exception
     *
     * @return void
     */
    public function testException()
    {
        $message = 'Not Found';
        $url = 'https://mock.codes/404';
        $statusCode = 404;
        $responseHeaders = Headers::fromString(
            'content-type: application/json'
        );
        $responseBody = '{"statusCode" : 404, "description": "Not Found"}';
        $previous = null;

        $exception = new HttpDownloadException(
            $message,
            $url,
            $statusCode,
            $responseHeaders,
            $responseBody,
            $previous
        );

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($url, $exception->getUrl());
        $this->assertEquals($statusCode, $exception->getHttpStatus());
        $this->assertEquals($responseHeaders, $exception->getResponseHeaders());
        $this->assertEquals($responseBody, $exception->getResponseBody());
        $this->assertEquals($previous, $exception->getPrevious());
    }
}
