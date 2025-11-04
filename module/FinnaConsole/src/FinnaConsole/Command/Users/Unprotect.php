<?php

/**
 * Console service for unprotecting users.
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

namespace FinnaConsole\Command\Users;

use Finna\Db\Entity\FinnaUserEntityInterface;
use VuFind\Db\Entity\EntityInterface;

use function assert;

/**
 * Console service for unprotecting users
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Unprotect extends \FinnaConsole\Command\AbstractRecordUpdateCommand
{
    /**
     * Table display name
     *
     * @var string
     */
    protected $tableName = 'user';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Unprotect users in the database';

    /**
     * Update a record
     *
     * @param EntityInterface $record Record
     *
     * @return bool Whether changes were made
     */
    protected function changeRecord(EntityInterface $record): bool
    {
        assert($record instanceof FinnaUserEntityInterface);
        if (!$record->getFinnaProtected()) {
            return false;
        }
        $record->setFinnaProtected(false);
        return true;
    }
}
