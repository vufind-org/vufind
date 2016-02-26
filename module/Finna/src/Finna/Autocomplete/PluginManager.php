<?php
/**
 * Autocomplete handler plugin manager
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Autocomplete
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
namespace Finna\Autocomplete;

/**
 * Autocomplete handler plugin manager
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class PluginManager extends \VuFind\Autocomplete\PluginManager
{
    /**
     * Faceting disabled?
     *
     * @var boolean
     */
    protected $disableFaceting = false;

    /**
     * Current search tab
     *
     * @var string
     */
    protected $searchTab = null;

    /**
     * This returns an array of suggestions based on current request parameters.
     * This logic is present in the factory class so that it can be easily shared
     * by multiple AJAX handlers.
     *
     * @param \Zend\Stdlib\Parameters $request    The user request
     * @param string                  $typeParam  Request parameter containing search
     * type
     * @param string                  $queryParam Request parameter containing query
     * string
     *
     * @return array
     */
    public function getSuggestions($request, $typeParam = 'type', $queryParam = 'q')
    {
        if ($request->onlySuggestions) {
            $this->disableFaceting = true;
        }
        if ($request->tab) {
            $this->searchTab = str_replace('###', ':', $request->tab);
        }
        $this->request = $request;
        return parent::getSuggestions($request, $typeParam, $queryParam);
    }

// @codingStandardsIgnoreStart
    /**
     * Retrieve a service from the manager by name
     *
     * Allows passing an array of options to use when creating the instance.
     * createFromInvokable() will use these and pass them to the instance
     * constructor if not null and a non-empty array.
     *
     * @param string $name
     * @param array  $options
     * @param bool   $usePeeringServiceManagers
     *
     * @return object
     *
     * @throws Exception\ServiceNotFoundException
     * @throws Exception\ServiceNotCreatedException
     * @throws Exception\RuntimeException
// @codingStandardsIgnoreEnd
     */
    public function get($name, $options = [], $usePeeringServiceManagers = true)
    {
        $handler = parent::get($name, $options, $usePeeringServiceManagers);
        if ($this->disableFaceting) {
            $handler->disableFaceting();
        }
        if ($this->searchTab) {
            $handler->setSearchTab($this->searchTab);
        }
        $handler->setRequest($this->request);
        return $handler;
    }
}
