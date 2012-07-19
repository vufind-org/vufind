<?php
/**
 * Remove duplicate lines from a file -- needed for the Windows version of
 * the alphabetical browse database generator, since Windows sort does not
 * support deduplication. Assumed presorted
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind2
 * @package  Utilities
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
if(count($argv) < 2 || $argv[1] == "") {
    echo "\nPlease specify an input file: ";
    $argv[1] = chop(fgets(STDIN)); // Read the input
}
$in = fopen($argv[1], 'r');
if (!$in) {
    die('Could not open input file: '.$argv[1]."\n");
}

if(count($argv) < 3 || $argv[2] == "") {
    echo "\nPlease specify an output file: ";
    $argv[2] = chop(fgets(STDIN)); // Read the input
}
$out = fopen($argv[2], 'w');
if (!$out) {
    die('Could not open output file: '.$argv[2]."\n");
}

$last = '';
while ($tmp = fgets($in)) {
    if ($tmp != $last) {
        fputs($out, $tmp);
    }
    $last = $tmp;
}

fclose($in);
fclose($out);