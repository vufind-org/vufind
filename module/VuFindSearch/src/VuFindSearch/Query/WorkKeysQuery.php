<?php

/**
 * A work keys query.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Query;

/**
 * A work keys query.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WorkKeysQuery extends AbstractQuery
{
    /**
     * Record ID
     *
     * @var ?string
     */
    protected $id;

    /**
     * Whether to include the record to compare with in the results
     *
     * @var bool
     */
    protected $includeSelf;

    /**
     * Work keys
     *
     * @var array
     */
    protected $workKeys;

    /**
     * Constructor.
     *
     * @param ?string $id          Record ID
     * @param bool    $includeSelf Whether to include the record to compare with in the results
     * @param array   $workKeys    Work keys to use
     */
    public function __construct(?string $id, bool $includeSelf, array $workKeys = [])
    {
        $this->id = $id;
        $this->includeSelf = $includeSelf;
        $this->workKeys = $workKeys;
    }

    /**
     * Return record id
     *
     * @return ?string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set record id
     *
     * @param ?string $id Record id
     *
     * @return void
     */
    public function setId(?string $id)
    {
        $this->id = $id;
    }

    /**
     * Return "include self" setting
     *
     * @return bool
     */
    public function getIncludeSelf(): bool
    {
        return $this->includeSelf;
    }

    /**
     * Set "include self" setting
     *
     * @param bool $includeSelf New value
     *
     * @return void
     */
    public function setIncludeSelf(bool $includeSelf): void
    {
        $this->includeSelf = $includeSelf;
    }

    /**
     * Return work keys
     *
     * @return array
     */
    public function getWorkKeys(): array
    {
        return $this->workKeys;
    }

    /**
     * Set work keys
     *
     * @param array $workKeys Work keys
     *
     * @return void
     */
    public function setWorkKeys(array $workKeys): void
    {
        $this->workKeys = $workKeys;
    }

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
    public function containsTerm($needle, $normalizer = null)
    {
        return false;
    }

    /**
     * Get a concatenated list of all query strings within the object.
     *
     * @return string
     */
    public function getAllTerms()
    {
        return $this->id;
    }

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
    public function replaceTerm($from, $to, $normalizer = null)
    {
        // Not applicable
    }
}
