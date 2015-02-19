<?php

/**
 * SOLR QueryBuilder interface definition.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\ParamBag;

/**
 * SOLR QueryBuilder interface definition.
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
interface QueryBuilderInterface
{
    /**
     * Build SOLR query based on VuFind query object.
     *
     * @param AbstractQuery $query Query object
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query);

    /**
     * Control whether or not the QueryBuilder should create an hl.q parameter
     * when the main query includes clauses that should not be factored into
     * highlighting. (Turned off by default).
     *
     * @param bool $enable Should highlighting query generation be enabled?
     *
     * @return void
     */
    public function setCreateHighlightingQuery($enable);

    /**
     * Control whether or not the QueryBuilder should create a spellcheck.q
     * parameter. (Turned off by default).
     *
     * @param bool $enable Should spelling query generation be enabled?
     *
     * @return void
     */
    public function setCreateSpellingQuery($enable);
}