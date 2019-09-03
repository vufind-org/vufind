<?php
/**
 * Url view helper (extending core Zend helper with additional functionality)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Zend\Http\PhpEnvironment\Request;

/**
 * Url view helper (extending core Zend helper with additional functionality)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Url extends \Zend\View\Helper\Url
{
    /**
     * Request (or null if unavailable)
     *
     * @var Request
     */
    protected $request = null;

    /**
     * Constructor
     *
     * @param Request $request Request object for GET parameters
     */
    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Generates a url given the name of a route.
     *
     * @param string            $name               Name of the route
     * @param array             $params             Parameters for the link
     * @param array|Traversable $options            Options for the route
     * @param bool              $reuseMatchedParams Whether to reuse matched
     * parameters
     *
     * @see Zend\Mvc\Router\RouteInterface::assemble()
     * @see Zend\Router\RouteInterface::assemble()
     *
     * @throws Exception\RuntimeException If no RouteStackInterface was provided
     * @throws Exception\RuntimeException If no RouteMatch was provided
     * @throws Exception\RuntimeException If RouteMatch didn't contain a matched
     * route name
     * @throws Exception\InvalidArgumentException If the params object was not an
     * array or Traversable object.
     *
     * @return string Url For the link href attribute
     */
    public function __invoke(
        $name = null, $params = [], $options = [], $reuseMatchedParams = false
    ) {
        // If argument list is empty, return object for method access:
        return func_num_args() == 0 ? $this : parent::__invoke(...func_get_args());
    }

    /**
     * Get URL with current GET parameters and add one
     *
     * @param array $params             Key-paired parameters
     * @param bool  $reuseMatchedParams Whether to reuse matched parameters
     *
     * @return string
     */
    public function addQueryParameters($params, $reuseMatchedParams = true)
    {
        $requestQuery = (null !== $this->request)
            ? $this->request->getQuery()->toArray() : [];
        $options = ['query' => array_merge($requestQuery, $params)];
        return $this->__invoke(null, [], $options, $reuseMatchedParams);
    }
}
