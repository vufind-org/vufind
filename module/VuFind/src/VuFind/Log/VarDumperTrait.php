<?php

/**
 * Helper for dumping variables into string.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2024.
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
 * @package  Error_Logging
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Helper for dumping variables into string.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait VarDumperTrait
{
    /**
     * Dump variable into string
     *
     * @param mixed $var Variable to export
     *
     * @return string
     */
    protected function varDump(mixed $var): string
    {
        $cloner = new VarCloner();
        $dumper = new CliDumper();
        $output = '';
        $callback = function (string $line, int $depth) use (&$output): void {
            // A negative depth means "end of dump"
            if ($depth >= 0) {
                // Adds a two spaces indentation to the line
                $output .= str_repeat('  ', $depth) . $line . "\n";
            }
        };
        $dumper->dump($cloner->cloneVar($var), $callback);
        return $output;
    }
}
