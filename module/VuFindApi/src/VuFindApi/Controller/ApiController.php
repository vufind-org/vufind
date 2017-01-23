<?php
/**
 * API Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindApi\Controller;

/**
 * API Controller
 *
 * Controls the API functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ApiController extends \VuFind\Controller\AbstractBase
{
    use ApiTrait;

    /**
     * Array of available API controllers
     *
     * @var array
     */
    protected $apiControllers = [];

    /**
     * Add an API controller to the list of available controllers
     *
     * @param Zend\Mvc\Controller\AbstractActionController $controller API Controller
     *
     * @return void
     */
    public function addApi($controller)
    {
        if (!in_array($controller, $this->apiControllers)) {
            $this->apiControllers[] = $controller;
        }
    }

    /**
     * Index action
     *
     * Return Swagger specification or redirect to Swagger UI
     *
     * @return \Zend\Http\Response
     */
    public function indexAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        if (null === $this->getRequest()->getQuery('swagger')) {
            $urlHelper = $this->getViewRenderer()->plugin('url');
            $base = rtrim($urlHelper('home'), '/');
            $url = "$base/swagger-ui/?url="
                . urlencode("$base/api?swagger");
            return $this->redirect()->toUrl($url);
        }
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $config = $this->getConfig();
        $params = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'specs' => $this->getApiSpecs()
        ];
        $json = $this->getViewRenderer()->render('api/swagger', $params);
        $response->setContent($json);
        return $response;
    }

    /**
     * Get specification fragments from all APIs as JSON
     *
     * @return string
     */
    protected function getApiSpecs()
    {
        $results = [];

        foreach ($this->apiControllers as $controller) {
            $api = $controller->getSwaggerSpecFragment();
            $specs = json_decode($api, true);
            if (null === $specs) {
                throw new \Exception(
                    'Could not parse Swagger spec fragment of '
                    . get_class($controller)
                );
            }
            foreach ($specs as $key => $spec) {
                if (isset($results[$key])) {
                    $results[$key] = array_merge($results[$key], $spec);
                } else {
                    $results[$key] = $spec;
                }
            }
        }

        // Return the fragment without the enclosing curly brackets
        return substr(trim(json_encode($results, JSON_PRETTY_PRINT)), 1, -1);
    }
}
