<?php

/**
 * SOLR facet widget interface definition.
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
 * @package  Controller
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller\Widget\Solr;

use VuFindSearch\Backend\Solr\Response\Json\Facets;
use VuFind\Controller\Widget\WidgetInterface;

/**
 * SOLR facet widget interface definition.
 *
 * @category VuFind2
 * @package  Controller
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
interface FacetInterface extends WidgetInterface
{
    /**
     * Update facet state based on SOLR facet information.
     *
     * @param Facets $facets SOLR facet information
     *
     * @return void
     */
    public function updateState(Facets $facets);
}