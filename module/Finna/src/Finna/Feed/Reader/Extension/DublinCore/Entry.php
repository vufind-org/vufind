<?php

/**
 * Laminas\Feed\Entry extension for Dublin Core
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Pasi Tiisanoja <pasi.tiisanoja@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Feed\Reader\Extension\DublinCore;

use Laminas\Feed\Reader\Extension\DublinCore\Entry as ParentEntry;

use function array_key_exists;

/**
 * Laminas\Feed\Entry extension for Dublin Core
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Pasi Tiisanoja <pasi.tiisanoja@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

class Entry extends ParentEntry
{
    /**
     * Get a Dublin Core format.
     *
     * @return string
     */
    public function getFormat()
    {
        if (array_key_exists('format', $this->data)) {
            return $this->data['format'];
        }

        $format = (string)$this->getXpath()->evaluate('string(' . $this->getXpathPrefix() . '/dc11:format)')
        ?: (string)$this->getXpath()->evaluate('string(' . $this->getXpathPrefix() . '/dc10:format)');

        return $this->data['format'] = $format ?: null;
    }
}
