<?php
/**
 * Summon Search API Interface (query model)
 *
 * PHP version 5
 *
 * Copyright (C) Serials Solutions 2011.
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
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */

/**
 * Summon REST API Interface (query model)
 *
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class SerialsSolutions_Summon_Query
{
    /**
     * The query terms
     * @var string
     */
    protected $query;

    /**
     * Whether to limit to the library's holdings or not
     * @var bool
     */
    protected $holdings = true;

    /**
     * An array of facets to be requested
     * @var array
     */
    protected $facets = null;

    /**
     * An array of filters to be applied
     * @var array
     */
    protected $filters = array();

    /**
     * An array of range filters to be applied
     * @var array
     */
    protected $rangeFilters = array();

    /**
     * Sort option
     * @var string
     */
    protected $sort = null;

    /**
     * Results per page
     * @var int
     */
    protected $pageSize = 25;

    /**
     * Page number to retrieve
     * @var int
     */
    protected $pageNumber = 1;

    /**
     * Whether to enable spell checking
     * @var bool
     */
    protected $didYouMean = false;

    /**
     * Whether to enable highlighting
     * @var bool
     */
    protected $highlight = false;

    /**
     * Highlight start string
     * @var string
     */
    protected $highlightStart = '';

    /**
     * Highlight end string
     * @var boolean
     */
    protected $highlightEnd = '';

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param string $query   Search query
     * @param array  $options Other options to set (associative array)
     */
    public function __construct($query = null, $options = array())
    {
        $this->query = $query;

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Define default facets to request if necessary:
        if (is_null($this->facets)) {
            $this->facets = array(
                'IsScholarly,or,1,2',
                'Library,or,1,30',
                'ContentType,or,1,30',
                'SubjectTerms,or,1,30',
                'Language,or,1,30'
            );
        }
    }

    /**
     * Turn the options within this object into an array of Summon parameters.
     *
     * @return array
     */
    public function getOptionsArray()
    {
        $options = array(
            's.q' => $this->query,
            's.ps' => $this->pageSize,
            's.pn' => $this->pageNumber,
            's.ho' => $this->holdings ? 'true' : 'false',
            's.dym' => $this->didYouMean ? 'true' : 'false'
        );
        if (!empty($this->facets)) {
            $options['s.ff'] = $this->facets;
        }
        if (!empty($this->filters)) {
            $options['s.fvf'] = $this->filters;
        }
        if (!empty($this->rangeFilters)) {
            $options['s.rf'] = $this->rangeFilters;
        }
        if (!empty($this->sort)) {
            $options['s.sort'] = $this->sort;
        }
        if ($this->highlight) {
            $options['s.hl'] = 'true';
            $options['s.hs'] = $this->highlightStart;
            $options['s.he'] = $this->highlightEnd;
        } else {
            $options['s.hl'] = 'false';
            $options['s.hs'] = $options['s.he'] = '';
        }
        return $options;
    }

    /**
     * Add a filter
     *
     * @param string $f Filter to apply
     *
     * @return void
     */
    public function addFilter($f)
    {
        $this->filters[] = $f;
    }

    /**
     * Add a range filter
     *
     * @param string $f Filter to apply
     *
     * @return void
     */
    public function addRangeFilter($f)
    {
        $this->rangeFilters[] = $f;
    }

    /**
     * Magic method for getting/setting properties.
     *
     * @param string $method Method being called
     * @param string $params Array of parameters
     *
     * @return mixed
     */
    public function __call($method, $params)
    {
        if (strlen($method) > 4) {
            $action = substr($method, 0, 3);
            $property = strtolower(substr($method, 3, 1)) . substr($method, 4);
            if ($action == 'get' && property_exists($this, $property)) {
                return $this->$property;
            }
            if ($action == 'set' && property_exists($this, $property)) {
                if (isset($params[0])) {
                    $this->$property = $params[0];
                    return;
                }
                throw new ErrorException(
                    $method . ' missing required parameter', 0, E_ERROR
                ); 
            }
        }
        throw new ErrorException(
            'Call to Undefined Method/Class Function', 0, E_ERROR
        ); 
    }

    /**
     * Escape a string for inclusion as part of a Summon parameter.
     *
     * @param string $input The string to escape.
     *
     * @return string       The escaped string.
     */
    public static function escapeParam($input)
    {
        // List of characters to escape taken from:
        //      http://api.summon.serialssolutions.com/help/api/search/parameters
        return addcslashes($input, ",:\\()\${}");
    }
}

?>
