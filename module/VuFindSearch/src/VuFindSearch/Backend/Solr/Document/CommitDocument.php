<?php

/**
 * SOLR commit document class.
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

use XMLWriter;

/**
 * SOLR commit document class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class CommitDocument extends AbstractDocument
{
    /**
     * Value for commitWithin attribute
     *
     * @var int
     */
    protected $commitWithin;

    /**
     * Constructor.
     *
     * @param int $commitWithin commitWithin attribute value
     *
     * @return void
     */
    public function __construct($commitWithin = null)
    {
        $this->commitWithin = $commitWithin;
    }

    /**
     * Return serialized JSON representation.
     *
     * @return string
     */
    public function asJSON()
    {
        // @todo Implement
    }

    /**
     * Return serialized XML representation.
     *
     * @return string
     */
    public function asXML()
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument();
        $writer->startElement('commit');
        if ($this->commitWithin > 0) {
            $writer->writeAttribute('commitWithin', $this->commitWithin);
        }
        $writer->endElement();
        $writer->endDocument();
        return $writer->flush();
    }

}
