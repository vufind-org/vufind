<?php
/**
 * Code for generating Autocomplete objects
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
namespace VuFind\Autocomplete;
use VuFind\Config\Reader as ConfigReader, VuFind\Search\Options as SearchOptions;

/**
 * Code for generating Autocomplete objects
 *
 * This is a factory class to build autocomplete modules for use in searches.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
class Factory
{
    /**
     * initRecommendation
     *
     * This constructs an autocomplete plug-in object.
     *
     * @param string $module The name of the autocomplete module to build
     * @param string $params Configuration string to send to the constructor
     *
     * @return mixed         The $module object on success, false otherwise
     */
    public static function initAutocomplete($module, $params)
    {
        // backward compatibility with VuFind 1.x names:
        switch ($module) {
        case 'NoAutocomplete':
            $module = 'None';
            break;
        default:
            $module = str_replace('Autocomplete', '', $module);
            break;
        }

        // Try to load the appropriate class, if any:
        if (!empty($module)) {
            $module = 'VuFind\\Autocomplete\\' . $module;
            if (class_exists($module)) {
                $auto = new $module($params);
                return $auto;
            }
        }

        return false;
    }

    /**
     * getSuggestions
     *
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
    public static function getSuggestions($request, $typeParam = 'type',
        $queryParam = 'q'
    ) {
        // Process incoming parameters:
        $type = $request->get($typeParam, '');
        $query = $request->get($queryParam, '');

        // get Autocomplete_Type config
        $searcher = $request->get('searcher', 'Solr');
        $options = SearchOptions::getInstance($searcher);
        $config = ConfigReader::getConfig($options->getSearchIni());
        $types = isset($config->Autocomplete_Types) ?
            $config->Autocomplete_Types->toArray() : array();

        // Figure out which handler to use:
        if (!empty($type) && isset($types[$type])) {
            $module = $types[$type];
        } else if (isset($config->Autocomplete->default_handler)) {
            $module = $config->Autocomplete->default_handler;
        } else {
            $module = false;
        }

        // Get suggestions:
        if ($module) {
            if (strpos($module, ':') === false) {
                $module .= ':'; // force colon to avoid warning in explode below
            }
            list($name, $params) = explode(':', $module, 2);
            $handler = self::initAutocomplete($name, $params);
        }

        return (isset($handler) && is_object($handler))
            ? $handler->getSuggestions($query) : array();
    }
}
