<?php

/**
 * SOLR facet information.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Response\Json;

use ArrayObject;

/**
 * SOLR facet information.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Facets
{
    /**
     * Facet fields.
     *
     * @var ArrayObject
     */
    protected $fields;

    /**
     * Constructor.
     *
     * @param array $facets SOLR facet information
     *
     * @return void
     *
     * @todo Implement facet_queries et al.
     */
    public function __construct (array $facets)
    {
        $this->fields = new ArrayObject();
        foreach ($facets['facet_fields'] as $name => $info) {
            $this->fields->offsetSet($name, new NamedList($info));
        }
    }

    /**
     * Return facet fields.
     *
     * @return ArrayObject
     */
    public function getFields ()
    {
        return $this->fields;
    }

}