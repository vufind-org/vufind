<?php

/**
 * SOLR optimize document class.
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
namespace VuFindSearch\Backend\Solr\Document;

use XMLWriter;

/**
 * SOLR optimize document class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class OptimizeDocument extends AbstractDocument
{
    /**
     * Value for waitFlush attribute
     *
     * @var boolean
     */
    protected $waitFlush;

    /**
     * Value for waitSearch attribute
     *
     * @var boolean
     */
    protected $waitSearcher;

    /**
     * Constructor.
     *
     * @param bool $waitFlush    waitFlush attribute value
     * @param bool $waitSearcher waitSearcher attribute value
     *
     * @return void
     */
    public function __construct($waitFlush = null, $waitSearcher = null)
    {
        $this->waitFlush    = $waitFlush;
        $this->waitSearcher = $waitSearcher;
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
        $writer->startElement('optimize');
        if ($this->waitFlush !== null) {
            $writer->writeAttribute(
                'waitFlush', $this->waitFlush ? 'true' : 'false'
            );
        }
        if ($this->waitSearcher !== null) {
            $writer->writeAttribute(
                'waitSearcher', $this->waitSearcher ? 'true' : 'false'
            );
        }
        $writer->endElement();
        $writer->endDocument();
        return $writer->flush();
    }

}