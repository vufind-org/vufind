<?php
/**
 * Trait to add facet limiting settings to a Params object.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\Search\Params;

use Zend\Config\Config;

/**
 * Trait to add facet limiting settings to a Params object.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait FacetLimitTrait
{
    /**
     * Default facet result limit
     *
     * @var int
     */
    protected $facetLimit = 30;

    /**
     * Per-field facet result limit
     *
     * @var array
     */
    protected $facetLimitByField = [];

    /**
     * Initialize facet limit from a Config object.
     *
     * @param Config $config Configuration
     *
     * @return void
     */
    protected function initFacetLimitsFromConfig(Config $config = null)
    {
        if (is_numeric($config->facet_limit ?? null)) {
            $this->setFacetLimit($config->facet_limit);
        }
        foreach ($config->facet_limit_by_field ?? [] as $k => $v) {
            $this->facetLimitByField[$k] = $v;
        }
    }

    /**
     * Set Facet Limit
     *
     * @param int $l the new limit value
     *
     * @return void
     */
    public function setFacetLimit($l)
    {
        $this->facetLimit = $l;
    }

    /**
     * Set Facet Limit by Field
     *
     * @param array $new Associative array of $field name => $limit
     *
     * @return void
     */
    public function setFacetLimitByField(array $new)
    {
        $this->facetLimitByField = $new;
    }

    /**
     * Get the facet limit for the specified field.
     *
     * @param string $field Field to look up
     *
     * @return int
     */
    protected function getFacetLimitForField($field)
    {
        return $this->facetLimitByField[$field] ?? $this->facetLimit;
    }
}
