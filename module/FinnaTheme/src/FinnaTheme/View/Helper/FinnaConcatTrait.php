<?php
/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017-2018.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace FinnaTheme\View\Helper;

/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait FinnaConcatTrait
{
    /**
     * Create a concatenated file from the given group of files
     *
     * @param string $concatPath Resulting file path
     * @param array  $group      Object containing 'key' and stdobj file 'items'
     *
     * @throws \Exception
     * @return void
     */
    protected function createConcatenatedFile($concatPath, $group)
    {
        // Check for minified version in the database
        $filename = basename($concatPath);
        $row = $this->finnaCache->getByResourceId($filename);
        if (false !== $row) {
            if (false === file_put_contents($concatPath, $row->data)) {
                throw new \Exception("Could not write to file $concatPath");
            }
            if (false === touch($concatPath, $row->mtime)) {
                throw new \Exception("Could not touch timestamp of $concatPath");
            }
            return;
        }

        parent::createConcatenatedFile($concatPath, $group);

        // Store the result in the database
        $content = file_get_contents($concatPath);
        if (false === $content) {
            throw new \Exception("Could not load contents of $concatPath");
        }
        $row = $this->finnaCache->createRow();
        $row->resource_id = $filename;
        $row->mtime = time();
        $row->data = $content;
        try {
            $row->save();
        } catch (\Exception $e) {
            // Recheck the database to be sure that no-one else has created the
            // resource in the meantime
            $row = $this->finnaCache->getByResourceId($filename);
            if (false === $row) {
                // No, something else failed
                throw $e;
            }
        }
    }
}
