<?php
/**
 * Additional functionality for API controllers.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library 2015-2016.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindApi\Controller;

/**
 * Additional functionality for API controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait ApiTrait
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
     * Execute the request
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $headers->addHeaderLine(
                'Access-Control-Allow-Methods', 'GET, POST, OPTIONS'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        return parent::onDispatch($e);
    }

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
            'prettyPrint', $request->getPost('prettyPrint', false)
        );
        $this->outputMode = empty($this->jsonpCallback) ? 'json' : 'jsonp';
    }

    /**
     * Check whether access is denied and return the appropriate message or false.
     *
     * @param string $permission Permission to check
     *
     * @return \Zend\Http\Response|boolean
     */
    protected function isAccessDenied($permission)
    {
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        if (!$auth->isGranted($permission)) {
            return $this->output([], self::STATUS_ERROR, 403, 'Permission denied');
        }
        return false;
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
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if (null === $data) {
            return $response;
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
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $response->setContent(
                $this->jsonpCallback . '(' . json_encode($output, $jsonOptions)
                . ');'
            );
            return $response;
        } else {
            throw new \Exception('Invalid output mode');
        }
    }
}
