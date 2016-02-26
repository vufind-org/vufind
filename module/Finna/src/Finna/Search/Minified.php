<?php
/**
 * Finna Minified Search Object
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

/**
 * Finna Minified Search Object
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Minified implements \Serializable
{
    // Serializable properties:

    /**
     * Date range type (search_daterange_mvtype)
     *
     * @var string
     */
    public $f_dty;

    /**
     * MetaLib search set
     *
     * @var string
     */
    public $f_mset;

    /**
     * Parent search object
     *
     * @var \VuFind\Search\Minified
     */
    protected $parentSO;

    /**
     * Constructor. Building minified object from the
     *    searchObject passed in. Needs to be kept
     *    up-to-date with the deminify() function on
     *    searchObject.
     *
     * @param object $searchObject Search Object to minify
     */
    public function __construct($searchObject)
    {
        $params = $searchObject->getParams();
        $daterange = $params->getSpatialDateRangeFilter();
        if ($daterange && isset($daterange['type'])
        ) {
            $this->f_dty = $daterange['type'];
        }
        if ($set = $params->getMetaLibSearchSet()) {
            $this->f_mset = $set;
        }
    }

    /**
     * Set parent search object.
     *
     * @param \VuFind\Search\Minified $so Parent search object
     *
     * @return void
     */
    public function setParentSO($so)
    {
        $this->parentSO = $so;
    }

    /**
     * Get parent search object.
     *
     * @return \VuFind\Search\Minified
     */
    public function getParentSO()
    {
        return $this->parentSO;
    }

    /**
     * Serialize search object.
     *
     * @return string Serialized data
     */
    public function serialize()
    {
        $data = [];
        if ($this->f_dty) {
            $data['f_dty'] = $this->f_dty;
        }
        if ($this->f_mset) {
            $data['f_mset'] = $this->f_mset;
        }
        return serialize($data);
    }

    /**
     * Unserialize search object.
     *
     * @param string $data Serialized data
     *
     * @return \Finna\Search\Minified
     */
    public function unserialize($data)
    {
        $data = unserialize($data);
        if (isset($data['f_dty'])) {
            $this->f_dty = $data['f_dty'];
        }
        if (isset($data['f_mset'])) {
            $this->f_mset = $data['f_mset'];
        }
        return $this;
    }

    /**
     * Turn the current object into search results.
     *
     * @param \VuFind\Search\Results\PluginManager $manager Search manager
     *
     * @return \VuFind\Search\Base\Results
     */
    public function deminify(\VuFind\Search\Results\PluginManager $manager)
    {
        $results = $this->parentSO->deminify($manager);
        $results->getParams()->deminifyFinnaSearch($this);
        return $results;
    }
}
