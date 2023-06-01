<?php

/**
 * Metadata vocabulary implementation for Eprints
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
 * Metadata vocabulary implementation for Eprints
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Eprints extends AbstractBase
{
    /**
     * Mapping from Eprints to VuFind fields
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = [
        'eprints.creators_name' => 'author',
        'eprints.date' => 'date',
        'eprints.issn' => 'issn',
        'eprints.number' => 'volume',
        'eprints.publication' => 'container_title',
        'eprints.publisher' => 'publisher',
        'eprints.title' => 'title',
    ];

    /**
     * Special implementation to combine start / end page in eprints.pagerange
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getMappedData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $mappedData = parent::getMappedData($driver);

        // special handling for pagerange
        $startpage = $driver->tryMethod('getContainerStartPage');

        if ($startpage) {
            $pagerange = $startpage;
            $endpage = $driver->tryMethod('getContainerEndPage');
            if ($endpage != '' && $endpage != $startpage) {
                $pagerange = $startpage . '-' . $endpage;
            }
            $mappedData['eprints.pagerange'] = [$pagerange];
        }

        return $mappedData;
    }
}
