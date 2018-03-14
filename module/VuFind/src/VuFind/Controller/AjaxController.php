<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use VuFind\AjaxHandler\AjaxHandlerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AbstractBase
{
    /**
     * Array of PHP errors captured during execution
     *
     * @var array
     */
    protected static $php_errors = [];

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        // Add notices to a key in the output
        set_error_handler([static::class, 'storeError']);
        parent::__construct($sm);
    }


    /**
     * Format the content of the AJAX response based on the response type.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return string
     * @throws \Exception
     */
    protected function formatContent($type, $data, $status)
    {
        switch ($type) {
        case 'application/javascript':
            $output = compact('data', 'status');
            if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
                $output['php_errors'] = self::$php_errors;
            }
            return json_encode($output);
        case 'text/plain':
            return $data ? $status . " $data" : $status;
        case 'text/html':
            return $data ?: '';
        default:
            throw new \Exception("Unsupported content type: $type");
        }
    }

    /**
     * Send output data and exit.
     *
     * @param string $type     Content type to output
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function getAjaxResponse($type, $data, $status, $httpCode = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', $type);
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        $response->setContent($this->formatContent($type, $data, $status));
        return $response;
    }

    /**
     * Turn an exception into error response.
     *
     * @param string     $type Content type to output
     * @param \Exception $e    Exception to output.
     *
     * @return \Zend\Http\Response
     */
    protected function getExceptionResponse($type, \Exception $e)
    {
        $debugMsg = ('development' == APPLICATION_ENV)
            ? ': ' . $e->getMessage() : '';
        return $this->getAjaxResponse(
            $type,
            $this->translate('An error has occurred') . $debugMsg,
            AjaxHandlerInterface::STATUS_ERROR,
            500
        );
    }

    /**
     * Call an AJAX method and turn the result into a response.
     *
     * @param string $method AJAX method to call
     * @param string $type   Content type to output
     *
     * @return \Zend\Http\Response
     */
    protected function callAjaxMethod($method, $type = 'application/javascript')
    {
        // Check the AJAX handler plugin manager for the method.
        $manager = $this->serviceLocator->get('VuFind\AjaxHandler\PluginManager');
        if ($manager->has($method)) {
            try {
                $handler = $manager->get($method);
                return $this->getAjaxResponse(
                    $type, ...$handler->handleRequest($this->params())
                );
            } catch (\Exception $e) {
                return $this->getExceptionResponse($type, $e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->getAjaxResponse(
            $type,
            $this->translate('Invalid Method'),
            AjaxHandlerInterface::STATUS_ERROR,
            400
        );
    }

    /**
     * Store the errors for later, to be added to the output
     *
     * @param string $errno   Error code number
     * @param string $errstr  Error message
     * @param string $errfile File where error occurred
     * @param string $errline Line number of error
     *
     * @return bool           Always true to cancel default error handling
     */
    public static function storeError($errno, $errstr, $errfile, $errline)
    {
        self::$php_errors[] = "ERROR [$errno] - " . $errstr . "<br />\n"
            . " Occurred in " . $errfile . " on line " . $errline . ".";
        return true;
    }

    /**
     * Make an AJAX call with a JSON-formatted response.
     *
     * @return \Zend\Http\Response
     */
    public function jsonAction()
    {
        return $this->callAjaxMethod($this->params()->fromQuery('method'));
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return \Zend\Http\Response
     */
    public function recommendAction()
    {
        return $this->callAjaxMethod('recommend', 'text/html');
    }

    /**
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    public function systemStatusAction()
    {
        return $this->callAjaxMethod('systemStatus', 'text/plain');
    }
}
