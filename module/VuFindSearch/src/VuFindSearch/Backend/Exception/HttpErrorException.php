<?php

/**
 * HTTP error exception.
 *
 * PHP version 8
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Exception;

use Exception;
use Laminas\Http\Response;

/**
 * HTTP error exception.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class HttpErrorException extends BackendException
{
    /**
     * Server response.
     *
     * @var Response
     */
    protected $response;

    /**
     * Exception factory.
     *
     * Returns a RequestErrorException or RemoteErrorException depending on
     * the response's status code.
     *
     * @param Response $response Server response
     *
     * @return RequestErrorException|RemoteErrorException
     */
    public static function createFromResponse(Response $response)
    {
        $status = $response->getStatusCode();
        $phrase = $response->getReasonPhrase();
        if ($status >= 500) {
            return new RemoteErrorException(
                $status . ' ' . $phrase,
                $status,
                $response
            );
        } else {
            return new RequestErrorException(
                $status . ' ' . $phrase,
                $status,
                $response
            );
        }
    }

    /**
     * Constructor.
     *
     * @param string    $message  Exception message
     * @param int       $code     Exception code
     * @param Response  $response Server response
     * @param Exception $prev     Previous exception
     *
     * @return void
     */
    public function __construct(
        $message,
        $code,
        Response $response,
        Exception $prev = null
    ) {
        parent::__construct($message, $code, $prev);
        $this->response = $response;
    }

    /**
     * Return server response.
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
