<?php

/**
 * Common methods that must be shared by all query objects.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Query;

/**
 * Common methods that must be shared by all query objects.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
interface QueryInterface
{
    /**
     * Does the query contain the specified term? An optional normalizer can be
     * provided to allow for fuzzier matching.
     *
     * @param string   $needle     Term to check
     * @param callable $normalizer Function to normalize text strings (null for
     * no normalization)
     *
     * @return bool
     */
    public function containsTerm($needle, $normalizer = null);

    /**
     * Get a concatenated list of all query strings within the object.
     *
     * @return string
     */
    public function getAllTerms();

    /**
     * Replace a term.
     *
     * @param string   $from       Search term to find
     * @param string   $to         Search term to insert
     * @param callable $normalizer Function to normalize text strings (null for
     * no normalization)
     *
     * @return void
     */
    public function replaceTerm($from, $to, $normalizer = null);
}
