<?php

/**
 * Common base class for Finna statistics entities.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Entity;

use Doctrine\ORM\Mapping as ORM;
use Finna\Db\Type\FinnaStatisticsClientType;

/**
 * Common base class for Finna statistics entities.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
abstract class FinnaBaseStats
{
    /**
     * Institution.
     *
     * @var string
     */
    #[ORM\Column(name: 'institution', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected string $institution;

    /**
     * View.
     *
     * @var string
     */
    #[ORM\Column(name: 'view', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected string $view;

    /**
     * Client Type.
     *
     * @var int
     */
    #[ORM\Column(name: 'crawler', type: 'integer', nullable: false)]
    #[ORM\Id]
    protected int $type;

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
     * Count.
     *
     * @var int
     */
    #[ORM\Column(name: 'count', type: 'integer', nullable: false, options: ['default' => 1])]
    protected int $count = 1;

    /**
     * Get institution.
     *
     * @return string
     */
    public function getInstitution(): string
    {
        return $this->institution;
    }

    /**
     * Set institution.
     *
     * @param string $institution Institution
     *
     * @return static
     */
    public function setInstitution(string $institution): static
    {
        $this->institution = mb_substr($institution, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get view.
     *
     * @return string
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Set view.
     *
     * @param string $view View
     *
     * @return static
     */
    public function setView(string $view): static
    {
        $this->view = mb_substr($view, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get type.
     *
     * @return FinnaStatisticsClientType
     */
    public function getType(): FinnaStatisticsClientType
    {
        return FinnaStatisticsClientType::from($this->type);
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
        $this->type = $type->value;
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
