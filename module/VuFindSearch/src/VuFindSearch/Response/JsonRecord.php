<?php

/**
 * Simple, schema-less JSON record.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Response;

/**
 * Simple, schema-less JSON record.
 *
 * This record primarily serves as an example or blueprint for a schema-less
 * record. All fields are exposed via object properties.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class JsonRecord implements RecordInterface
{
    use RecordTrait;

    /**
     * Constructor.
     *
     * @param array   $fields   Document fields
     * @param ?string $sourceId Record source identifier (optional)
     *
     * @return void
     */
    public function __construct(protected array $fields, ?string $sourceId = null)
    {
        if ($sourceId) {
            $this->setSourceIdentifiers($sourceId);
        }
    }

    /**
     * __get()
     *
     * @param string $name Field name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->fields[$name] ?? null;
    }
}
