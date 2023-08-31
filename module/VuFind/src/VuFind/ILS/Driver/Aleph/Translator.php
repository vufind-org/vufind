<?php

/**
 * Translator support class for Aleph ILS driver
 *
 * PHP version 8
 *
 * Copyright (C) UB/FU Berlin
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
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver\Aleph;

use function call_user_func_array;
use function get_class;

/**
 * Aleph Translator Class
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Translator
{
    /**
     * Character set
     *
     * @var string
     */
    protected $charset;

    /**
     * Table 15 configuration
     *
     * @var array
     */
    protected $table15;

    /**
     * Table 40 configuration
     *
     * @var array
     */
    protected $table40;

    /**
     * Sub library configuration table
     *
     * @var array
     */
    protected $table_sub_library;

    /**
     * Constructor
     *
     * @param array $configArray Aleph configuration
     */
    public function __construct($configArray)
    {
        $this->charset = $configArray['util']['charset'];
        $this->table15 = $this->parsetable(
            $configArray['util']['tab15'],
            get_class($this) . '::tab15Callback'
        );
        $this->table40 = $this->parsetable(
            $configArray['util']['tab40'],
            get_class($this) . '::tab40Callback'
        );
        $this->table_sub_library = $this->parsetable(
            $configArray['util']['tab_sub_library'],
            get_class($this) . '::tabSubLibraryCallback'
        );
    }

    /**
     * Parse a table
     *
     * @param string $file     Input file
     * @param string $callback Callback routine for parsing
     *
     * @return array
     */
    public function parsetable($file, $callback)
    {
        $result = [];
        $file_handle = fopen($file, 'r, ccs=UTF-8');
        $rgxp = '';
        while (!feof($file_handle)) {
            $line = fgets($file_handle);
            $line = rtrim($line);
            if (preg_match('/!!/', $line)) {
                $line = rtrim($line);
                $rgxp = static::regexp($line);
            }
            if (preg_match('/!.*/', $line) || $rgxp == '' || $line == '') {
            } else {
                $line = str_pad($line, 80);
                $matches = '';
                if (preg_match($rgxp, $line, $matches)) {
                    call_user_func_array(
                        $callback,
                        [$matches, &$result, $this->charset]
                    );
                }
            }
        }
        fclose($file_handle);
        return $result;
    }

    /**
     * Get a tab40 collection description
     *
     * @param string $collection Collection
     * @param string $sublib     Sub-library
     *
     * @return string
     */
    public function tab40Translate($collection, $sublib)
    {
        $findme = $collection . '|' . $sublib;
        $desc = $this->table40[$findme];
        if ($desc == null) {
            $findme = $collection . '|';
            $desc = $this->table40[$findme];
        }
        return $desc;
    }

    /**
     * Support method for tab15Translate -- translate a sub-library name
     *
     * @param string $sl Text to translate
     *
     * @return string
     */
    public function tabSubLibraryTranslate($sl)
    {
        return $this->table_sub_library[$sl];
    }

    /**
     * Get a tab15 item status
     *
     * @param string $slc  Sub-library
     * @param string $isc  Item status code
     * @param string $ipsc Item process status code
     *
     * @return string
     */
    public function tab15Translate($slc, $isc, $ipsc)
    {
        $tab15 = $this->tabSubLibraryTranslate($slc);
        if ($tab15 == null) {
            echo 'tab15 is null!<br>';
        }
        $findme = $tab15['tab15'] . '|' . $isc . '|' . $ipsc;
        $result = $this->table15[$findme] ?? null;
        if ($result == null) {
            $findme = $tab15['tab15'] . '||' . $ipsc;
            $result = $this->table15[$findme];
        }
        $result['sub_lib_desc'] = $tab15['desc'];
        return $result;
    }

    /**
     * Callback for tab15 (modify $tab15 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  $tab15   result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab15Callback($matches, &$tab15, $charset)
    {
        $lib = $matches[1];
        $no1 = $matches[2];
        if ($no1 == '##') {
            $no1 = '';
        }
        $no2 = $matches[3];
        if ($no2 == '##') {
            $no2 = '';
        }
        $desc = iconv($charset, 'UTF-8', $matches[5]);
        $key = trim($lib) . '|' . trim($no1) . '|' . trim($no2);
        $tab15[trim($key)] = [
            'desc' => trim($desc), 'loan' => $matches[6], 'request' => $matches[8],
            'opac' => $matches[10],
        ];
    }

    /**
     * Callback for tab40 (modify $tab40 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  $tab40   result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab40Callback($matches, &$tab40, $charset)
    {
        $code = trim($matches[1]);
        $sub = trim($matches[2]);
        $sub = trim(preg_replace('/#/', '', $sub));
        $desc = trim(iconv($charset, 'UTF-8', $matches[4]));
        $key = $code . '|' . $sub;
        $tab40[trim($key)] = [ 'desc' => $desc ];
    }

    /**
     * Sub-library callback (modify $tab_sub_library by reference)
     *
     * @param array  $matches         preg_match() return array
     * @param array  $tab_sub_library result array to generate
     * @param string $charset         character set
     *
     * @return void
     */
    public static function tabSubLibraryCallback(
        $matches,
        &$tab_sub_library,
        $charset
    ) {
        $sublib = trim($matches[1]);
        $desc = trim(iconv($charset, 'UTF-8', $matches[5]));
        $tab = trim($matches[6]);
        $tab_sub_library[$sublib] = [ 'desc' => $desc, 'tab15' => $tab ];
    }

    /**
     * Apply standard regular expression cleanup to a string.
     *
     * @param string $string String to clean up.
     *
     * @return string
     */
    public static function regexp($string)
    {
        $string = preg_replace('/\\-/', ')\\s(', $string);
        $string = preg_replace('/!/', '.', $string);
        $string = preg_replace('/[<>]/', '', $string);
        $string = '/(' . $string . ')/';
        return $string;
    }
}
