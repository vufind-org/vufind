<?php
/**
 * CLI Controller Module (language tools)
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use Laminas\Console\Console;
use VuFind\I18n\ExtendedIniNormalizer;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;

/**
 * This controller handles various command-line tools for dealing with language files
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class LanguageController extends AbstractBase
{
    /**
     * Delete a language string to another
     *
     * @return \Laminas\Console\Response
     */
    public function deleteAction()
    {
        // Display help message if parameters missing:
        $request = $this->getRequest();
        $target = $request->getParam('target');
        if (empty($target)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' language delete [target]'
            );
            Console::writeLine(
                "\ttarget - the target key to remove "
                . "(may include 'textdomain::' prefix)"
            );
            return $this->getFailureResponse();
        }

        $normalizer = new ExtendedIniNormalizer();
        list($domain, $key) = $this->extractTextDomain($target);
        $target = $key . ' = "';

        if (!($dir = $this->getLangDir($output, $domain))) {
            return $this->getFailureResponse();
        }
        $callback = function ($full) use ($target, $normalizer) {
            $lines = file($full);
            $out = '';
            $found = false;
            foreach ($lines as $line) {
                if (substr($line, 0, strlen($target)) !== $target) {
                    $out .= $line;
                } else {
                    $found = true;
                }
            }
            if ($found) {
                file_put_contents($full, $out);
                $normalizer->normalizeFile($full);
            } else {
                Console::writeLine("Source key not found.");
            }
        };
        $this->processDirectory($dir, $callback);

        return $this->getSuccessResponse();
    }

    /**
     * Normalizer
     *
     * @return \Laminas\Console\Response
     */
    public function normalizeAction()
    {
        // Display help message if parameters missing:
        $request = $this->getRequest();
        $target = $request->getParam('target');
        if (empty($target)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName()
                . ' language normalize [target]'
            );
            Console::writeLine("\ttarget - a file or directory to normalize");
            return $this->getFailureResponse();
        }

        $normalizer = new ExtendedIniNormalizer();
        if (is_dir($target)) {
            $normalizer->normalizeDirectory($target);
        } elseif (is_file($target)) {
            $normalizer->normalizeFile($target);
        } else {
            Console::writeLine("{$target} does not exist.");
            return $this->getFailureResponse();
        }
        return $this->getSuccessResponse();
    }
}
