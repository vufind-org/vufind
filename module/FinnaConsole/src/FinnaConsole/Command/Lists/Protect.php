<?php

/**
 * Console service for protecting lists.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Lists;

use Finna\Db\Entity\UserListEntityInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use VuFind\Db\Entity\EntityInterface;

use function assert;

/**
 * Console service for protecting lists
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'lists/protect'
)]
class Protect extends \FinnaConsole\Command\AbstractRecordUpdateCommand
{
    /**
     * Table display name
     *
     * @var string
     */
    protected $tableName = 'list';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Protect lists in the database';

    /**
     * Update a record
     *
     * @param EntityInterface $record Record
     *
     * @return bool Whether changes were made
     */
    protected function changeRecord(EntityInterface $record): bool
    {
        assert($record instanceof UserListEntityInterface);
        if ($record->getFinnaProtected()) {
            return false;
        }
        $record->setFinnaProtected(true);
        return true;
    }
}
