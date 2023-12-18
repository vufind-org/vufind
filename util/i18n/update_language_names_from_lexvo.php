<?php

/**
 * Command-line tool to parse language code/name mappings from Lexvo.org data.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Utilities
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/automation Wiki
 */

$VUFIND_HOME = __DIR__ . '/../..';
include_once "$VUFIND_HOME/vendor/autoload.php";

use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\Util;

// You may wish to adjust these variables to control the behavior of the script:
$tmpDir = "/tmp";
$source = "http://lexvo.org/resources/lexvo_2013-02-09.nt.gz";
$targetFile = "$tmpDir/lekvo.nt";
$filteredOutput = "$tmpDir/lekvo-filtered.nt";

// Set up parser to extract useful details from the RDF:
$map = [];
$parser = new TriGParser(['format' => 'n-triples'], function ($error, $triple) use (& $map) {
    if (!($error ?? false) && isset($triple)) {
        if (str_starts_with($triple['subject'], 'http://lexvo.org/id/iso639-3/')
            && $triple['predicate'] === 'http://www.w3.org/2000/01/rdf-schema#label'
        ) {
            $parts = explode('/', $triple['subject']);
            $key = array_pop($parts);
            $lang = Util::getLiteralLanguage($triple['object']);
            $val = Util::getLiteralValue($triple['object']);
            if (!isset($map[$lang][$key])
                || str_contains($map[$lang][$key], ' language')
            ) {
                $map[$lang][$key] = $val;
            }
        }
    } elseif ($error) {
        fwrite(STDERR, $error->getMessage()."\n");
    }
});

// Download and unzip the source data, if necessary:
if (!file_exists($targetFile)) {
    passthru("wget $source -O $targetFile.gz");
    passthru("gzip -d $targetFile.gz");
}

// Improve processing time by filtering RDF to only include subjects/predicates we need:
if (!file_exists($filteredOutput)) {
    passthru("cat $targetFile | grep \"http://lexvo.org/id/iso639-3/\" | grep \"http://www.w3.org/2000/01/rdf-schema#label\" > $filteredOutput");
}

// Create the output directory:
passthru("mkdir -p $tmpDir/lang");

// Process the input data:
$handle = fopen($filteredOutput, 'r');
while ($line = fgets($handle)) {
    $parser->parseChunk($line);
}
fclose($handle);
$parser->end();

// Write all the parsed data:
foreach ($map as $lang => $vals) {
    $handle = fopen($tmpDir . '/lang/' . $lang . '.ini', 'w');
    foreach ($vals as $key => $val) {
        fputs($handle, "$key = $val\n");
    }
    fclose($handle);
}

// Merge the parsed data with the existing data (favoring existing data over new data):
$langDir = "$VUFIND_HOME/languages/ISO639-3";
$dir = opendir($langDir);
while ($file = readdir($dir)) {
    if (str_ends_with($file, '.ini') && file_exists("$tmpDir/lang/$file")) {
        passthru("cat $langDir/$file >> $tmpDir/lang/$file");
        passthru("mv $tmpDir/lang/$file $langDir/$file");
    }
}
closedir($dir);

// Normalize everything:
passthru("php $VUFIND_HOME/public/index.php language normalize $VUFIND_HOME/languages");
