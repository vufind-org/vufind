<?php

/**
 * Trait for creating INI readers
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  Config
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config\Feature;

use Laminas\Config\Reader\Ini as IniReader;

use function chr;

/**
 * Trait for creating INI readers
 *
 * @category VuFind
 * @package  Config
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait IniReaderTrait
{
    /**
     * INI reader
     *
     * @var IniReader
     */
    protected $iniReader = null;

    /**
     * Creates INI reader if it does not exist and returns it.
     *
     * @return IniReader
     */
    protected function getIniReader()
    {
        if (null == $this->iniReader) {
            // Use ASCII 0 as a nest separator; otherwise some of our unusual key names
            // (e.g. strings containing . characters) will get parsed in unexpected ways.
            $this->iniReader = new IniReader();
            $this->iniReader->setNestSeparator(chr(0));
        }
        return $this->iniReader;
    }
}
