<?php

/**
 * SOLR "raw document" class for submitting any type of data.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Document;

/**
 * SOLR "raw document" class for submitting any type of data.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawDocument implements DocumentInterface
{
    /**
     * Raw document text
     *
     * @var string
     */
    protected $content;

    /**
     * MIME type
     *
     * @var string
     */
    protected $mime;

    /**
     * Text encoding
     *
     * @var string
     */
    protected $encoding;

    /**
     * Constructor.
     *
     * @param string  $content  Raw document text
     * @param string  $mime     MIME type
     * @param ?string $encoding Text encoding (null for unspecified)
     */
    public function __construct(
        string $content,
        string $mime,
        ?string $encoding = 'UTF-8'
    ) {
        $this->content = $content;
        $this->mime = $mime;
        $this->encoding = $encoding;
    }

    /**
     * Return content MIME type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->mime
            . (empty($this->encoding) ? '' : '; charset=' . $this->encoding);
    }

    /**
     * Return serialized representation.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
