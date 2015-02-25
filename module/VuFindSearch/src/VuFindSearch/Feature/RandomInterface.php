<?php

/**
 * Random record retrieval feature interface definition.
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Feature;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;

/**
 * Random record retrieval feature interface definition.
 *
 * @category VuFind2
 * @package  Search
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
interface RandomInterface
{
    /**
     * Return random records.
     *
     * @param AbstractQuery $query  Search query
     * @param integer       $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function random(
        AbstractQuery $query, $limit, ParamBag $params = null
    );
}
