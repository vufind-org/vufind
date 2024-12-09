<?php

/**
 * Metadata vocabulary implementation for BEPress
 *
 * PHP version 8
 *
 * Copyright (C) University of Tübingen 2019.
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
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\MetadataVocabulary;

/**
 * Metadata vocabulary implementation for BEPress
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BEPress extends AbstractBase
{
    /**
     * Mapping from BEPress to VuFind fields; see http://
     * div.div1.com.au/div-thoughts/div-commentaries/66-div-commentary-metadata
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = [
        'bepress_citation_author' => 'author',
        'bepress_citation_date' => 'date',
        'bepress_citation_doi' => 'doi',
        'bepress_citation_firstpage' => 'startpage',
        'bepress_citation_isbn' => 'isbn',
        'bepress_citation_issn' => 'issn',
        'bepress_citation_issue' => 'issue',
        'bepress_citation_journal_title' => 'container_title',
        'bepress_citation_lastpage' => 'endpage',
        'bepress_citation_publisher' => 'publisher',
        'bepress_citation_title' => 'title',
        'bepress_citation_volume' => 'volume',
    ];
}
