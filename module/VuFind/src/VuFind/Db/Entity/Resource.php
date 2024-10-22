<?php

/**
 * Entity model for resource table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Resource
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="resource",
 * indexes={@ORM\Index(name="record_id", columns={"record_id"})}
 * )
 * @ORM\Entity
 */
class Resource implements ResourceEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Record ID.
     *
     * @var string
     *
     * @ORM\Column(name="record_id", type="string", length=255, nullable=false)
     */
    protected $recordId = '';

    /**
     * Record title.
     *
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    protected $title = '';

    /**
     * Primary author.
     *
     * @var ?string
     *
     * @ORM\Column(name="author", type="string", length=255, nullable=true)
     */
    protected $author;

    /**
     * Published year.
     *
     * @var ?int
     *
     * @ORM\Column(name="year", type="integer", nullable=true)
     */
    protected $year;

    /**
     * Record source.
     *
     * @var string
     *
     * @ORM\Column(name="source",
     *          type="string",
     *          length=50,
     *          nullable=false,
     *          options={"default"="Solr"}
     * )
     */
    protected $source = 'Solr';

    /**
     * Record Metadata
     *
     * @var ?string
     *
     * @ORM\Column(name="extra_metadata",
     *          type="text",
     *          length=16777215,
     *          nullable=true
     * )
     */
    protected $extraMetadata;

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Record Id setter
     *
     * @param string $recordId recordId
     *
     * @return static
     */
    public function setRecordId(string $recordId): static
    {
        $this->recordId = $recordId;
        return $this;
    }

    /**
     * Record Id getter
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Title setter
     *
     * @param string $title Title of the record.
     *
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Title getter
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Author setter
     *
     * @param ?string $author Author of the title.
     *
     * @return static
     */
    public function setAuthor(?string $author): static
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Year setter
     *
     * @param ?int $year Year title is published.
     *
     * @return static
     */
    public function setYear(?int $year): static
    {
        $this->year = $year;
        return $this;
    }

    /**
     * Source setter
     *
     * @param string $source Source (a search backend ID).
     *
     * @return static
     */
    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Source getter
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Extra Metadata setter
     *
     * @param ?string $extraMetadata ExtraMetadata.
     *
     * @return static
     */
    public function setExtraMetadata(?string $extraMetadata): static
    {
        $this->extraMetadata = $extraMetadata;
        return $this;
    }

    /**
     * Extra Metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string
    {
        return $this->extraMetadata;
    }
}
