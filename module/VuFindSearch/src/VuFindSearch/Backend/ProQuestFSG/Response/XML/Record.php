<?php

/**
 * Simple ProQuest Federated Search Gateway record.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\ProQuestFSG\Response\XML;

use VuFind\Marc\MarcReader;
use VuFindSearch\Response\RecordInterface;

/**
 * Simple ProQuest Federated Search Gateway record.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Record implements RecordInterface
{
    use \VuFindSearch\Response\RecordTrait;

    /**
     * MARC record.
     *
     * @var MarcReader
     */
    protected $marc;

    /**
     * Constructor.
     *
     * @param MarcReader $marc MARC record
     *
     * @return void
     */
    public function __construct(MarcReader $marc)
    {
        $this->marc = $marc;
        $this->setSourceIdentifiers('ProQuestFSG');
    }

    /**
     * Get MARC record
     *
     * @return MarcReader
     */
    public function getMarc(): MarcReader
    {
        return $this->marc;
    }
}
