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
    // define some status constants
    const STATUS_OK = 'OK';                  // good
    const STATUS_ERROR = 'ERROR';            // bad
    const STATUS_NEED_AUTH = 'NEED_AUTH';    // must login first

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
            self::STATUS_ERROR,
            500
        );
    }

    /**
     * Handles passing data to the class
     *
     * @return mixed
     */
    public function jsonAction()
    {
        // Set the output mode to JSON:
        $this->outputMode = 'json';

        // Get the requested AJAX method:
        $method = $this->params()->fromQuery('method');

        // Check the AJAX handler plugin manager for the method.
        $manager = $this->serviceLocator->get('VuFind\AjaxHandler\PluginManager');
        if ($manager->has($method)) {
            $handler = $manager->get($method);
            try {
                return $this->output(...$handler->handleRequest($this->params()));
            } catch (\Exception $e) {
                return $this->getExceptionOutput($e);
            }
        }

        // Fallback: Call the method specified by the 'method' parameter; append
        // Ajax to the end to avoid access to arbitrary inappropriate methods.
        $callback = [$this, $method . 'Ajax'];
        if (is_callable($callback)) {
            try {
                return call_user_func($callback);
            } catch (\Exception $e) {
                return $this->getExceptionOutput($e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->output(
            $this->translate('Invalid Method'), self::STATUS_ERROR, 400
        );
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
     * Tag a record.
     *
     * @return \Zend\Http\Response
     */
    protected function tagRecordAjax()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }
        // empty tag
        try {
            $driver = $this->getRecordLoader()->load(
                $this->params()->fromPost('id'),
                $this->params()->fromPost('source', DEFAULT_SEARCH_BACKEND)
            );
            $tag = $this->params()->fromPost('tag', '');
            $tagParser = $this->serviceLocator->get('VuFind\Tags');
            if (strlen($tag) > 0) { // don't add empty tags
                if ('false' === $this->params()->fromPost('remove', 'false')) {
                    $driver->addTags($user, $tagParser->parse($tag));
                } else {
                    $driver->deleteTags($user, $tagParser->parse($tag));
                }
            }
        } catch (\Exception $e) {
            return $this->output(
                ('development' == APPLICATION_ENV) ? $e->getMessage() : 'Failed',
                self::STATUS_ERROR,
                500
            );
        }

        return $this->output($this->translate('Done'), self::STATUS_OK);
    }

    /**
     * Get all tags for a record.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordTagsAjax()
    {
        $user = $this->getUser();
        $is_me_id = null === $user ? null : $user->id;
        // Retrieve from database:
        $tagTable = $this->getTable('Tags');
        $tags = $tagTable->getForResource(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND),
            0, null, null, 'count', $is_me_id
        );

        // Build data structure for return:
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[] = [
                'tag'   => $tag->tag,
                'cnt'   => $tag->cnt,
                'is_me' => !empty($tag->is_me)
            ];
        }

        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');
        $view = $this->createViewModel(
            [
                'tagList' => $tagList,
                'loggedin' => null !== $user
            ]
        );
        $view->setTemplate('record/taglist');
        return $view;
    }

    /**
     * Comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function commentRecordAjax()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            return $this->output(
                $this->translate('Comments disabled'),
                self::STATUS_ERROR,
                403
            );
        }

        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $id = $this->params()->fromPost('id');
        $comment = $this->params()->fromPost('comment');
        if (empty($id) || empty($comment)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }

        $useCaptcha = $this->recaptcha()->active('userComments');
        $this->recaptcha()->setErrorMode('none');
        if (!$this->formWasSubmitted('comment', $useCaptcha)) {
            return $this->output(
                $this->translate('recaptcha_not_passed'),
                self::STATUS_ERROR,
                403
            );
        }

        $table = $this->getTable('Resource');
        $resource = $table->findResource(
            $id, $this->params()->fromPost('source', DEFAULT_SEARCH_BACKEND)
        );
        $id = $resource->addComment($comment, $user);

        return $this->output($id, self::STATUS_OK);
    }

    /**
     * Delete a comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function deleteRecordCommentAjax()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            return $this->output(
                $this->translate('Comments disabled'),
                self::STATUS_ERROR,
                403
            );
        }

        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $id = $this->params()->fromQuery('id');
        if (empty($id)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        $table = $this->getTable('Comments');
        if (!$table->deleteIfOwnedByUser($id, $user)) {
            return $this->output(
                $this->translate('edit_list_fail'),
                self::STATUS_ERROR,
                403
            );
        }

        return $this->output($this->translate('Done'), self::STATUS_OK);
    }

    /**
     * Get list of comments for a record as HTML.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordCommentsAsHTMLAjax()
    {
        $driver = $this->getRecordLoader()->load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND)
        );
        $html = $this->getViewRenderer()
            ->render('record/comments-list.phtml', ['driver' => $driver]);
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Keep Alive
     *
     * This is responsible for keeping the session alive whenever called
     * (via JavaScript)
     *
     * @return \Zend\Http\Response
     */
    protected function keepAliveAjax()
    {
        // Request ID from session to mark it active
        $this->serviceLocator->get('Zend\Session\SessionManager')->getId();
        return $this->output(true, self::STATUS_OK);
    }

    /**
     * Get pick up locations for a library
     *
     * @return \Zend\Http\Response
     */
    protected function getLibraryPickupLocationsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $this->params()->fromQuery('id');
        $pickupLib = $this->params()->fromQuery('pickupLib');
        if (null === $id || null === $pickupLib) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();
            if ($patron) {
                $results = $catalog->getILLPickupLocations($id, $pickupLib, $patron);
                foreach ($results as &$result) {
                    if (isset($result['name'])) {
                        $result['name'] = $this->translate(
                            'location_' . $result['name'],
                            [],
                            $result['name']
                        );
                    }
                }
                return $this->output(['locations' => $results], self::STATUS_OK);
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Get pick up locations for a request group
     *
     * @return \Zend\Http\Response
     */
    protected function getRequestGroupPickupLocationsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $this->params()->fromQuery('id');
        $requestGroupId = $this->params()->fromQuery('requestGroupId');
        if (null === $id || null === $requestGroupId) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();
            if ($patron) {
                $details = [
                    'id' => $id,
                    'requestGroupId' => $requestGroupId
                ];
                $results = $catalog->getPickupLocations($patron, $details);
                foreach ($results as &$result) {
                    if (isset($result['locationDisplay'])) {
                        $result['locationDisplay'] = $this->translate(
                            'location_' . $result['locationDisplay'],
                            [],
                            $result['locationDisplay']
                        );
                    }
                }
                return $this->output(['locations' => $results], self::STATUS_OK);
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Get hierarchical facet data for jsTree
     *
     * Parameters:
     * facetName  The facet to retrieve
     * facetSort  By default all facets are sorted by count. Two values are available
     * for alternative sorting:
     *   top = sort the top level alphabetically, rest by count
     *   all = sort all levels alphabetically
     *
     * @return \Zend\Http\Response
     */
    protected function getFacetDataAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $facet = $this->params()->fromQuery('facetName');
        $sort = $this->params()->fromQuery('facetSort');
        $operator = $this->params()->fromQuery('facetOperator');

        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet, null, $operator === 'OR');
        $params->initFromRequest($this->getRequest()->getQuery());

        $facets = $results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            return $this->output([], self::STATUS_OK);
        }

        $facetList = $facets[$facet]['data']['list'];

        $facetHelper = $this->serviceLocator
            ->get('VuFind\Search\Solr\HierarchicalFacetHelper');
        if (!empty($sort)) {
            $facetHelper->sortFacetList($facetList, $sort == 'top');
        }

        return $this->output(
            $facetHelper->buildFacetArray(
                $facet, $facetList, $results->getUrlQuery()
            ),
            self::STATUS_OK
        );
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
        $this->outputMode = 'plaintext';

        // Check system status
        $config = $this->getConfig();
        if (!empty($config->System->healthCheckFile)
            && file_exists($config->System->healthCheckFile)
        ) {
            return $this->output(
                'Health check file exists', self::STATUS_ERROR, 503
            );
        }

        // Test search index
        try {
            $results = $this->getResultsManager()->get('Solr');
            $params = $results->getParams();
            $params->setQueryIDs(['healthcheck']);
            $results->performAndProcessSearch();
        } catch (\Exception $e) {
            return $this->output(
                'Search index error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }

        // Test database connection
        try {
            $sessionTable = $this->getTable('Session');
            $sessionTable->getBySessionId('healthcheck', false);
        } catch (\Exception $e) {
            return $this->output(
                'Database error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }

        // This may be called frequently, don't leave sessions dangling
        $this->serviceLocator->get('Zend\Session\SessionManager')->destroy();

        return $this->output('', self::STATUS_OK);
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
