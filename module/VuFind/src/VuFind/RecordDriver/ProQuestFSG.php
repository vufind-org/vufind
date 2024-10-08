<?php

/**
 * Model for MARC records in ProQuest Federated Search Gateway.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use VuFind\RecordDriver\Feature\MarcAdvancedTrait;
use VuFind\RecordDriver\Feature\MarcBasicTrait;
use VuFind\RecordDriver\Feature\MarcReaderTrait;

/**
 * Model for MARC records in ProQuest Federated Search Gateway.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class ProQuestFSG extends DefaultRecord
{
    use MarcReaderTrait, MarcAdvancedTrait, MarcBasicTrait {
        MarcBasicTrait::getNewerTitles insteadof MarcAdvancedTrait;
        MarcBasicTrait::getPreviousTitles insteadof MarcAdvancedTrait;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object. In this case, $data is a MARCXML
     * document.
     *
     * @return void
     */
    public function setRawData($data)
    {
        // Ensure that $driver->setRawData($driver->getRawData()) doesn't blow up:
        if (isset($data['fullrecord'])) {
            $data = $data['fullrecord'];
        }

        // Map the WorldCat response into a format that the parent Solr-based
        // record driver can understand.
        parent::setRawData(['fullrecord' => $data]);
    }
}
