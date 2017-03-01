<?php

/**
 * SOLR "raw XML" document class for manual overrides.
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
 * SOLR "raw XML" document class for manual overrides.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawXMLDocument extends AbstractDocument
{
    /**
     * Raw XML
     *
     * @var string
     */
    protected $xml;

    /**
     * Constructor.
     *
     * @param string $xml XML document to pass to Solr
     *
     * @return void
     */
    public function __construct($xml)
    {
        $this->xml = $xml;
    }

    /**
     * Return serialized JSON representation.
     *
     * @return string
     */
    public function asJSON()
    {
        // @todo Implement XML to JSON conversion
        throw new \Exception('JSON not supported here.');
    }

    /**
     * Return serialized XML representation.
     *
     * @return string
     */
    public function asXML()
    {
        return $this->xml;
    }
}
