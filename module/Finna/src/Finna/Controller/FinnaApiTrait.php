<?php
/**
 * Additional functionality for API controllers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * Additional functionality for API controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaApiTrait
{
    /**
     * Callback function in JSONP mode
     *
     * @var string
     */
    protected $jsonpCallback = null;

    /**
     * Whether to pretty-print JSON
     *
     * @var bool
     */
    protected $jsonPrettyPrint = false;

    /**
     * Type of output to use
     *
     * @var string
     */
    protected $outputMode = 'json';

    /**
     * Determine the correct output mode based on content negotiation or the
     * view parameter
     *
     * @return void
     */
    protected function determineOutputMode()
    {
        $request = $this->getRequest();
        $this->jsonpCallback
            = $request->getQuery('callback', $request->getPost('callback', null));
        $this->jsonPrettyPrint = $request->getQuery(
            'prettyPrint', $request->getPost('prettyPrint', null)
        );
        $this->outputMode = !empty($this->jsonpCallback) ? 'jsonp' : 'json';
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     * @param string $message  Status message
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null, $message = '')
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        $output = $data;
        if (!isset($output['status'])) {
            $output['status'] = $status;
        }
        if ($message && !isset($output['statusMessage'])) {
            $output['statusMessage'] = $message;
        }
        $jsonOptions = $this->jsonPrettyPrint ? JSON_PRETTY_PRINT : 0;
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/json');
            $response->setContent(json_encode($output, $jsonOptions));
            return $response;
        } elseif ($this->outputMode == 'jsonp') {
            if (empty($this->jsonpCallback)) {
                throw new \Exception('No callback provided for jsonp request');
            }
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $response->setContent(
                $this->jsonpCallback . '(' . json_encode($output, $jsonOptions) . ')'
            );
            return $response;
        } else {
            throw new \Exception('Invalid output mode');
        }
    }
}
