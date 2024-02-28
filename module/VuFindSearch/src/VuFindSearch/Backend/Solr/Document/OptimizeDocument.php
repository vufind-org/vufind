<?php

/**
 * SOLR optimize document class.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Document;

use XMLWriter;

/**
 * SOLR optimize document class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class OptimizeDocument implements DocumentInterface
{
    /**
     * Value for waitFlush attribute
     *
     * @var bool
     */
    protected $waitFlush;

    /**
     * Value for waitSearch attribute
     *
     * @var bool
     */
    protected $waitSearcher;

    /**
     * Constructor.
     *
     * @param bool $waitFlush    waitFlush attribute value
     * @param bool $waitSearcher waitSearcher attribute value
     */
    public function __construct(bool $waitFlush = null, bool $waitSearcher = null)
    {
        $this->waitFlush    = $waitFlush;
        $this->waitSearcher = $waitSearcher;
    }

    /**
     * Return content MIME type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return 'text/xml; charset=UTF-8';
    }

    /**
     * Return serialized representation.
     *
     * @return string
     */
    public function getContent(): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument();
        $writer->startElement('optimize');
        if ($this->waitFlush !== null) {
            $writer->writeAttribute(
                'waitFlush',
                $this->waitFlush ? 'true' : 'false'
            );
        }
        if ($this->waitSearcher !== null) {
            $writer->writeAttribute(
                'waitSearcher',
                $this->waitSearcher ? 'true' : 'false'
            );
        }
        $writer->endElement();
        $writer->endDocument();
        return $writer->flush();
    }
}
