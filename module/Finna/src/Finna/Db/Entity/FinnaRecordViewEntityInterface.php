<?php

/**
 * Interface for representing a Finna record view.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use Finna\Db\Type\FinnaStatisticsClientType;
use VuFind\Db\Entity\EntityInterface;

/**
 * Interface for representing a Finna record view.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface FinnaRecordViewEntityInterface extends EntityInterface
{
    /**
     * Get institution+view.
     *
     * @return string
     */
    public function getInstitutionView(): FinnaRecordViewInstitutionViewEntityInterface;

    /**
     * Set institution+view.
     *
     * @param FinnaRecordViewInstitutionViewEntityInterface $instView Institution+view
     *
     * @return static
     */
    public function setInstitutionView(FinnaRecordViewInstitutionViewEntityInterface $instView): static;

    /**
     * Get type.
     *
     * @return FinnaStatisticsClientType
     */
    public function getType(): FinnaStatisticsClientType;

    /**
     * Set type.
     *
     * @param FinnaStatisticsClientType $type Type
     *
     * @return static
     */
    public function setType(FinnaStatisticsClientType $type): static;

    /**
     * Get date.
     *
     * @return string
     */
    public function getDate(): string;

    /**
     * Set date.
     *
     * @param string $date Date
     *
     * @return static
     */
    public function setDate(string $date): static;

    /**
     * Get record.
     *
     * @return string
     */
    public function getRecord(): FinnaRecordViewRecordEntityInterface;

    /**
     * Set record.
     *
     * @param FinnaRecordViewRecordEntityInterface $record Record
     *
     * @return static
     */
    public function setRecord(FinnaRecordViewRecordEntityInterface $record): static;

    /**
     * Get count.
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * Set count.
     *
     * @param int $count Count
     *
     * @return static
     */
    public function setCount(int $count): static;
}
