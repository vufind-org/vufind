<?php
/**
 * MetadataUtils Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2011-2020.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/KDK-Alli/RecordManager
 */
namespace Finna\Util;

/**
 * MetadataUtils Class
 *
 * This class contains a collection of static helper functions for metadata
 * processing
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/KDK-Alli/RecordManager
 */
class MetadataUtils
{
    /**
     * Convert first character of string to upper case (mb aware)
     *
     * @param string|string[] $str String to be converted
     *
     * @return string|string[] Converted string
     */
    public static function ucFirst($str)
    {
        if (is_array($str)) {
            foreach ($str as &$s) {
                $s = mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
            }
            return $str;
        }
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }
}
