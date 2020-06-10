<?php
/**
 * Functions for reading MARC records.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Functions for reading MARC records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait MarcReaderTrait
{
    /**
     * Get selected subfields from a MARC field
     *
     * @param \File_MARC_Data_Field $field     Field
     * @param array                 $subfields Subfields
     * @param bool                  $concat    Concat subfields into a string?
     * If false, the subfields are returned as an associative array.
     *
     * @return string|array
     */
    protected function getFieldSubfields(
        \File_MARC_Data_Field $field, $subfields, $concat = true
    ) {
        $result = [];
        foreach ($field->getSubfields() as $code => $content) {
            if (in_array($code, $subfields)) {
                $data = $content->getData();
                if ($concat) {
                    $result[] = $data;
                } else {
                    $result[$code] = $data;
                }
            }
        }
        if ($concat) {
            $result = implode(' ', $result);
        }
        return $result;
    }

    /**
     * Strip trailing spaces and punctuation characters from a string
     *
     * @param string|array $input      String to strip
     * @param string       $additional Additional punctuation characters
     *
     * @return string|array
     */
    protected function stripTrailingPunctuation($input, $additional = '')
    {
        $array = is_array($input);
        if (!$array) {
            $input = [$input];
        }
        foreach ($input as &$str) {
            $str = mb_ereg_replace("[\s\/:;\,=\($additional]+\$", '', $str);
            // Don't replace an initial letter (e.g. string "Smith, A.") followed by
            // period
            $thirdLast = substr($str, -3, 1);
            if (substr($str, -1) == '.' && $thirdLast != ' ') {
                $role = in_array(
                    substr($str, -4),
                    ['nid.', 'sid.', 'kuv.', 'ill.', 'säv.', 'col.']
                );
                if (!$role) {
                    $str = substr($str, 0, -1);
                }
            }
        }
        return $array ? $input : $input[0];
    }
}
