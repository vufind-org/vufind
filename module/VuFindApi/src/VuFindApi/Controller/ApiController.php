<?php

/**
 * API Controller
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Controller;

use function in_array;

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
     * @param Laminas\Mvc\Controller\AbstractActionController $controller API
     * Controller
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
     * Return API specification or redirect to Swagger UI
     *
     * @return \Laminas\Http\Response
     */
    public function indexAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        if (
            null === $this->getRequest()->getQuery('swagger')
            && null === $this->getRequest()->getQuery('openapi')
        ) {
            $urlHelper = $this->getViewRenderer()->plugin('url');
            $base = rtrim($urlHelper('home'), '/');
            $url = "$base/swagger-ui/?url=" . urlencode("$base/api?openapi");
            return $this->redirect()->toUrl($url);
        }
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $json = json_encode($this->getApiSpecs(), JSON_PRETTY_PRINT);
        $response->setContent($json);
        return $response;
    }

    /**
     * Get API specification JSON fragment for the root nodes
     *
     * @return string
     */
    protected function getApiSpecFragment()
    {
        $config = $this->getConfig();
        $params = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
        ];
        return $this->getViewRenderer()->render('api/openapi', $params);
    }

    /**
     * Merge specification fragments from all APIs to an array
     *
     * @return array
     */
    protected function getApiSpecs(): array
    {
        $results = [];

        foreach (array_merge([$this], $this->apiControllers) as $controller) {
            $api = $controller->getApiSpecFragment();
            $specs = json_decode($api, true);
            if (null === $specs) {
                throw new \Exception(
                    'Could not parse API spec fragment of '
                    . $controller::class . ': ' . json_last_error_msg()
                );
            }
            foreach ($specs as $key => $spec) {
                if (isset($results[$key])) {
                    if ('components' === $key) {
                        $results['components']['schemas'] = array_merge(
                            $results['components']['schemas'] ?? [],
                            $spec['schemas'] ?? []
                        );
                    } else {
                        $results[$key] = array_merge($results[$key], $spec);
                    }
                } else {
                    $results[$key] = $spec;
                }
            }
        }

        return $results;
    }
}
