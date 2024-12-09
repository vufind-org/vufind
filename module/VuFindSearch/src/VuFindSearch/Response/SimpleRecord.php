<?php

/**
 * A minimal record class for wrapping an array of fields
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland
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
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindSearch\Response;

/**
 * A minimal record class for wrapping an array of fields
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SimpleRecord implements RecordInterface
{
    use \VuFindSearch\Response\RecordTrait;

    /**
     * Field data
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Constructor
     *
     * @param array $fields Raw data
     */
    public function __construct($fields)
    {
        $this->fields = $fields;
        $this->setSourceIdentifiers(DEFAULT_SEARCH_BACKEND);
    }

    /**
     * Get field contents.
     *
     * @param string $field Field to get
     *
     * @return mixed
     */
    public function get($field)
    {
        return $this->fields[$field] ?? null;
    }
}
