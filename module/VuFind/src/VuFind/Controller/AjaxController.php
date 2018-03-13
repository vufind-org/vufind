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
     * Type of output to use
     *
     * @var string
     */
    protected $outputMode;

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
        set_error_handler(['VuFind\Controller\AjaxController', "storeError"]);
        parent::__construct($sm);
    }

    /**
     * Turn an exception into error output.
     *
     * @param \Exception $e Exception to output.
     *
     * @return \Zend\Http\Response
     */
    protected function getExceptionOutput(\Exception $e)
    {
        $debugMsg = ('development' == APPLICATION_ENV)
            ? ': ' . $e->getMessage() : '';
        return $this->output(
            $this->translate('An error has occurred') . $debugMsg,
            AjaxHandlerInterface::STATUS_ERROR,
            500
        );
    }

    /**
     * Call an AJAX method and turn the result into a response.
     *
     * @param string $method     AJAX method to call
     * @param string $outputMode Output format to use (json/plainText/html)
     *
     * @return \Zend\Http\Response
     */
    protected function callAjaxMethod($method, $outputMode = 'json')
    {
        // Set the output mode
        $this->outputMode = $outputMode;

        // Check the AJAX handler plugin manager for the method.
        $manager = $this->serviceLocator->get('VuFind\AjaxHandler\PluginManager');
        if ($manager->has($method)) {
            try {
                $handler = $manager->get($method);
                return $this->output(...$handler->handleRequest($this->params()));
            } catch (\Exception $e) {
                return $this->getExceptionOutput($e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->output(
            $this->translate('Invalid Method'),
            AjaxHandlerInterface::STATUS_ERROR,
            400
        );
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
        $this->disableSessionWrites();  // avoid session write timing bug
        // Process recommendations -- for now, we assume Solr-based search objects,
        // since deferred recommendations work best for modules that don't care about
        // the details of the search objects anyway:
        $rm = $this->serviceLocator->get('VuFind\Recommend\PluginManager');
        $module = $rm->get($this->params()->fromQuery('mod'));
        $module->setConfig($this->params()->fromQuery('params'));
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $module->init($params, $this->getRequest()->getQuery());
        $module->process($results);

        // Set headers:
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/html');
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        // Render recommendations:
        $recommend = $this->getViewRenderer()->plugin('recommend');
        $response->setContent($recommend($module));
        return $response;
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $output = ['data' => $data, 'status' => $status];
            if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
                $output['php_errors'] = self::$php_errors;
            }
            $response->setContent(json_encode($output));
            return $response;
        } elseif ($this->outputMode == 'plaintext') {
            $headers->addHeaderLine('Content-type', 'text/plain');
            $response->setContent($data ? $status . " $data" : $status);
            return $response;
        } else {
            throw new \Exception('Unsupported output mode: ' . $this->outputMode);
        }
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
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    protected function systemStatusAction()
    {
        return $this->callAjaxMethod('systemStatus', 'plaintext');
    }

    /**
     * Convenience method for accessing results
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getResultsManager()
    {
        return $this->serviceLocator->get('VuFind\Search\Results\PluginManager');
    }
}
