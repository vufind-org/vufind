<?php

/**
 * Metadata vocabulary implementation for Dublin Core
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
 * Metadata vocabulary implementation for Dublin Core
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DublinCore extends AbstractBase
{
    /**
     * Mapping from Dublin Core to VuFind fields
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = [
        'DC.citation.epage' => 'endpage',
        'DC.citation.issue' => 'issue',
        'DC.citation.spage' => 'startpage',
        'DC.citation.volume' => 'volume',
        'DC.creator' => 'author',
        'DC.identifier' => ['doi', 'isbn', 'issn'],
        'DC.issued' => 'date',
        'DC.language' => 'language',
        'DC.publisher' => 'publisher',
        'DC.relation.ispartof' => 'container_title',
        'DC.title' => 'title',
    ];
}
