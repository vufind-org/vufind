<?php

/**
 * SOLR "raw CSV" document class for submitting bulk data.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindSearch\Backend\Solr\Document;

/**
 * SOLR "raw CSV" document class for submitting bulk data.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawCSVDocument extends AbstractDocument
{
    /**
     * Raw CSV
     *
     * @var string
     */
    protected $csv;

    /**
     * Constructor.
     *
     * @param string $csv CSV document to pass to Solr
     *
     * @return void
     */
    public function __construct($csv)
    {
        $this->csv = $csv;
    }

    /**
     * Return CSV representation.
     *
     * @return string
     */
    public function asCSV()
    {
        return $this->csv;
    }

    /**
     * Return serialized JSON representation.
     *
     * @return string
     */
    public function asJSON()
    {
        throw new \Exception('JSON not supported here.');
    }

    /**
     * Return serialized XML representation.
     *
     * @return string
     */
    public function asXML()
    {
        throw new \Exception('XML not supported here.');
    }
}
