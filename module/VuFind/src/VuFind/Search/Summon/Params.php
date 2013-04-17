<?php
/**
 * Summon Search Parameters
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Summon;

/**
 * Summon Search Parameters
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Settings for all the facets
     */
    protected $fullFacetSettings = array();
    /**
     * Settings for the date facet only
     */
    protected $dateFacetSettings = array();

    /**
     * Add a field to facet on.
     *
     * @param string $newField Field name
     * @param string $newAlias Optional on-screen display label
     *
     * @return void
     */
    public function addFacet($newField, $newAlias = null)
    {
        // Save the full field name (which may include extra parameters);
        // we'll need these to do the proper search using the Summon class:
        if (strstr($newField, 'PublicationDate')) {
            // Special case -- we don't need to send this to the Summon API,
            // but we do need to set a flag so VuFind knows to display the
            // date facet control.
            $this->dateFacetSettings[] = 'PublicationDate';
        } else {
            $this->fullFacetSettings[] = $newField;
        }

        // Field name may have parameters attached -- remove them:
        $parts = explode(',', $newField);
        return parent::addFacet($parts[0], $newAlias);
    }

    /**
     * Get the full facet settings stored by addFacet -- these may include extra
     * parameters needed by the search results class.
     *
     * @return array
     */
    public function getFullFacetSettings()
    {
        return $this->fullFacetSettings;
    }

    /**
     * Get the date facet settings stored by addFacet.
     *
     * @return array
     */
    public function getDateFacetSettings()
    {
        return $this->dateFacetSettings;
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field Facet field name.
     *
     * @return string       Human-readable description of field.
     */
    public function getFacetLabel($field)
    {
        // The default use of "Other" for undefined facets doesn't work well with
        // checkbox facets -- we'll use field names as the default within the Summon
        // search object.
        return isset($this->facetConfig[$field])
            ? $this->facetConfig[$field] : $field;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @return array
     */
    public function getCheckboxFacets()
    {
        // Grab checkbox facet details using the standard method:
        $facets = parent::getCheckboxFacets();

        // Special case -- if we have a "holdings only" facet, we want this to
        // always appear, even on the "no results" screen, since setting this
        // facet actually EXPANDS the result set, rather than reducing it:
        if (isset($facets['holdingsOnly'])) {
            $facets['holdingsOnly']['alwaysVisible'] = true;
        }

        // Return modified list:
        return $facets;
    }
}