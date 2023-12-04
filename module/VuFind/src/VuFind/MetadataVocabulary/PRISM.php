<?php

/**
 * Metadata vocabulary implementation for PRISM
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
 * Metadata vocabulary implementation for PRISM
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PRISM extends AbstractBase
{
    /**
     * Mapping from Highwire Press to VuFind fields
     * see https://www.idealliance.org/prism-metadata
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = ['prism.doi' => 'doi',
                                               'prism.endingPage' => 'endpage',
                                               'prism.isbn' => 'isbn',
                                               'prism.issn' => 'issn',
                                               'prism.startingPage' => 'startpage',
                                               'prism.title' => 'title',
                                               'prism.volume' => 'volume',
                                            ];
}
