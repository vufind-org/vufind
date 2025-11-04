<?php

/**
 * API record formatter view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use FinnaApi\Formatter\RecordFormatter;

/**
 * API record formatter view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ApiRecordFormatter extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Record formatter.
     *
     * @var RecordFormatter
     */
    protected RecordFormatter $formatter;

    /**
     * Default API record fields.
     *
     * @var array
     */
    protected $defaultRecordFields = [];

    /**
     * ApiRecordFormatter constructor.
     *
     * @param RecordFormatter $formatter API record formatter
     */
    public function __construct(RecordFormatter $formatter)
    {
        $this->formatter = $formatter;
        foreach ($formatter->getRecordFields() as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['vufind.default'])) {
                $this->defaultRecordFields[] = $fieldName;
            }
        }
    }

    /**
     * Returns default API record fields.
     *
     * @return array
     */
    public function getDefaultFields()
    {
        return $this->defaultRecordFields;
    }

    /**
     * Format the results.
     *
     * @param array $results         Results to process (array of record drivers)
     * @param array $requestedFields Fields to include in response
     *
     * @return array
     */
    public function format(array $results, array $requestedFields): array
    {
        return $this->formatter->format($results, $requestedFields);
    }
}
