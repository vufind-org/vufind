<?php

/**
 * Export support class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Export
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna;

use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Export support class
 *
 * @category VuFind
 * @package  Export
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Export extends \VuFind\Export
{
    /**
     * Does the specified record support the specified export format?
     *
     * @param RecordDriver $driver Record driver
     * @param string       $format Format to check
     *
     * @return bool
     */
    public function recordSupportsFormat(RecordDriver $driver, string $format): bool
    {
        if ('Finna' === $format) {
            return true;
        }
        return parent::recordSupportsFormat($driver, $format);
    }

    /**
     * Get headers for the requested format.
     *
     * @param string $format Selected export format
     *
     * @return array
     */
    public function getHeaders(string $format): array
    {
        if ('Finna' === $format) {
            return ['Content-type: application/json'];
        }
        return parent::getHeaders($format);
    }
}
