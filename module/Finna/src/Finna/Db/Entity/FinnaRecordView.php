<?php

/**
 * Entity model for finna_record_view table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use Doctrine\ORM\Mapping as ORM;
use Finna\Db\Type\FinnaStatisticsClientType;

/**
 * Entity model for finna_record_view table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_record_view')]
#[ORM\Entity]
class FinnaRecordView implements FinnaRecordViewEntityInterface
{
    /**
     * Institution+view ID
     *
     * @var FinnaRecordViewInstitutionViewEntityInterface
     */
    #[ORM\JoinColumn(name: 'inst_view_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: FinnaRecordViewInstitutionViewEntityInterface::class)]
    #[ORM\Id]
    protected FinnaRecordViewInstitutionViewEntityInterface $institutionView;

    /**
     * Client Type.
     *
     * @var int
     */
    #[ORM\Column(name: 'crawler', type: 'integer', nullable: false)]
    #[ORM\Id]
    protected int $clientType;

    /**
     * Date.
     *
     * Note: This is a string because only primitive types are allowed in a composite primary key.
     *
     * @var string
     */
    #[ORM\Column(name: 'date', type: 'string', nullable: false)]
    #[ORM\Id]
    protected string $date;

    /**
     * Record ID
     *
     * @var FinnaRecordViewRecordEntityInterface
     */
    #[ORM\JoinColumn(name: 'record_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: FinnaRecordViewRecordEntityInterface::class)]
    #[ORM\Id]
    protected FinnaRecordViewRecordEntityInterface $record;

    /**
     * Count.
     *
     * @var int
     */
    #[ORM\Column(name: 'count', type: 'integer', nullable: false)]
    protected int $count;

    /**
     * Get institution+view.
     *
     * @return string
     */
    public function getInstitutionView(): FinnaRecordViewInstitutionViewEntityInterface
    {
        return $this->institutionView;
    }

    /**
     * Set institution+view.
     *
     * @param FinnaRecordViewInstitutionViewEntityInterface $instView Institution+view
     *
     * @return static
     */
    public function setInstitutionView(FinnaRecordViewInstitutionViewEntityInterface $instView): static
    {
        $this->institutionView = $instView;
        return $this;
    }

    /**
     * Get type.
     *
     * @return FinnaStatisticsClientType
     */
    public function getType(): FinnaStatisticsClientType
    {
        return FinnaStatisticsClientType::from($this->clientType);
    }

    /**
     * Set type.
     *
     * @param FinnaStatisticsClientType $type Type
     *
     * @return static
     */
    public function setType(FinnaStatisticsClientType $type): static
    {
        $this->clientType = $type->value;
        return $this;
    }

    /**
     * Get date.
     *
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * Set date.
     *
     * @param string $date Date
     *
     * @return static
     */
    public function setDate(string $date): static
    {
        $this->date = $date;
        return $this;
    }

    /**
     * Get record.
     *
     * @return string
     */
    public function getRecord(): FinnaRecordViewRecordEntityInterface
    {
        return $this->record;
    }

    /**
     * Set record.
     *
     * @param FinnaRecordViewRecordEntityInterface $record Record
     *
     * @return static
     */
    public function setRecord(FinnaRecordViewRecordEntityInterface $record): static
    {
        $this->record = $record;
        return $this;
    }

    /**
     * Get count.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Set count.
     *
     * @param int $count Count
     *
     * @return static
     */
    public function setCount(int $count): static
    {
        $this->count = $count;
        return $this;
    }
}
