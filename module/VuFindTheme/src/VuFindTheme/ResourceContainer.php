<?php

/**
 * VuFind Theme Public Resource Handler (for CSS, JS, etc.)
 *
 * PHP version 8
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use function count;
use function is_array;

/**
 * VuFind Theme Public Resource Handler (for CSS, JS, etc.)
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResourceContainer
{
    use \VuFind\Log\VarDumperTrait;

    /**
     * CSS files
     *
     * @var array
     */
    protected $css = [];

    /**
     * Javascript files
     *
     * @var array
     */
    protected $js = [];

    /**
     * Favicon
     *
     * @var string|array|null
     */
    protected $favicon = null;

    /**
     * Encoding type
     *
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * Generator value for <meta> tag
     *
     * @var string
     */
    protected $generator = '';

    /**
     * Add a CSS file.
     *
     * @param array|string $css CSS file (or array of CSS files) to add (possibly
     * with extra settings from theme config appended to each filename string).
     *
     * @return void
     */
    public function addCss($css)
    {
        if ((!is_array($css) && !is_a($css, 'Traversable')) || isset($css['file'])) {
            $this->addCssEntry($css);
        } elseif (isset($css[0])) {
            foreach ($css as $current) {
                $this->addCssEntry($current);
            }
        } elseif ($css === []) {
            return;
        } else {
            throw new \Exception('Invalid CSS entry format: ' . $this->varDump($css));
        }
    }

    /**
     * Add a Javascript file.
     *
     * @param array|string $js Javascript file (or array of files) to add (possibly
     * with extra settings from theme config appended to each filename string).
     *
     * @return void
     */
    public function addJs($js)
    {
        if ((!is_array($js) && !is_a($js, 'Traversable')) || isset($js['file'])) {
            $this->addJsEntry($js);
        } elseif (isset($js[0])) {
            foreach ($js as $current) {
                $this->addJsEntry($current);
            }
        } elseif ($js === []) {
            return;
        } else {
            throw new \Exception('Invalid JS entry format: ' . $this->varDump($js));
        }
    }

    /**
     * Helper function for adding a CSS file.
     *
     * @param string|array $cssEntry Entry to add, either as string with path
     * or array with additional properties.
     *
     * @return void
     */
    protected function addCssEntry($cssEntry)
    {
        if (!is_array($cssEntry)) {
            $this->addCssStringEntry($cssEntry);
        } else {
            $this->addCssArrayEntry($cssEntry);
        }
    }

    /**
     * Helper function for adding a CSS file which is described as string.
     *
     * @param string $cssEntry Entry to add as string.
     *
     * @return void
     */
    protected function addCssStringEntry($cssEntry)
    {
        $parts = $this->parseSetting($cssEntry);
        // Special case for media with parentheses
        // ie. (min-width: 768px)
        if (count($parts) > 1 && str_starts_with($parts[1], '(')) {
            $parts[1] .= ':' . $parts[2];
            array_splice($parts, 2, 1);
        }
        $cssArray = [
            'file' => trim($parts[0]),
        ];
        if (isset($parts[1])) {
            $cssArray['media'] = trim($parts[1]);
        }
        if (isset($parts[2])) {
            $cssArray['conditional'] = trim($parts[2]);
        }
        $this->addCssArrayEntry($cssArray);
    }

    /**
     * Helper function for adding a CSS file which is described as array.
     *
     * @param array $cssEntry Entry to add as array.
     *
     * @return void
     */
    protected function addCssArrayEntry($cssEntry)
    {
        if (isset($cssEntry['priority']) && isset($cssEntry['load_after'])) {
            throw new \Exception(
                'Using "priority" as well as "load_after" in the same entry '
                . 'is not supported: "' . $cssEntry['file'] . '"'
            );
        }

        // If we are disabling the dependency, remove it now.
        if ($cssEntry['disabled'] ?? false) {
            $this->removeEntry($cssEntry, $this->css);
            return;
        }

        foreach ($this->css as $existingEntry) {
            if ($existingEntry['file'] == $cssEntry['file']) {
                // If we have the same settings as before, just skip this entry.
                if ($existingEntry == $cssEntry) {
                    return;
                }

                throw new \Exception(
                    'Overriding an existing dependency is not supported: '
                    . '"' . $cssEntry['file'] . '"'
                );
            }
        }

        $this->insertEntry($cssEntry, $this->css);
    }

    /**
     * Helper function for adding a Javascript file.
     *
     * @param string|array $jsEntry Entry to add, either as string with path
     * or array with additional properties.
     *
     * @return void
     */
    protected function addJsEntry($jsEntry)
    {
        if (!is_array($jsEntry)) {
            $this->addJsStringEntry($jsEntry);
        } else {
            $this->addJsArrayEntry($jsEntry);
        }
    }

    /**
     * Helper function for adding a Javascript file which is described as string.
     *
     * @param string $jsEntry Entry to add as string.
     *
     * @return void
     */
    protected function addJsStringEntry($jsEntry)
    {
        $parts = $this->parseSetting($jsEntry);
        if (count($parts) == 1) {
            $jsEntry = ['file' => $jsEntry];
        } else {
            $jsEntry = [
                'file' => $parts[0],
                'attributes' => ['conditional' => trim($parts[1])],
            ];
        }
        $this->addJsArrayEntry($jsEntry);
    }

    /**
     * Helper function for adding a Javascript file which is described as array.
     *
     * @param array $jsEntry Entry to add as array.
     *
     * @return void
     */
    protected function addJsArrayEntry($jsEntry)
    {
        if (!isset($jsEntry['position'])) {
            $jsEntry['position'] = 'header';
        }

        if (isset($jsEntry['priority']) && isset($jsEntry['load_after'])) {
            throw new \Exception(
                'Using "priority" as well as "load_after" in the same entry '
                . 'is not supported: "' . $jsEntry['file'] . '"'
            );
        }

        // If we are disabling the dependency, remove it now.
        if ($jsEntry['disabled'] ?? false) {
            $this->removeEntry($jsEntry, $this->js);
            return;
        }

        foreach ($this->js as $existingEntry) {
            if ($existingEntry['file'] == $jsEntry['file']) {
                // If we have the same settings as before, just skip this entry.
                if ($existingEntry == $jsEntry) {
                    return;
                }

                throw new \Exception(
                    'Overriding an existing dependency is not supported: '
                    . '"' . $jsEntry['file'] . '"'
                );
            }
        }

        $this->insertEntry($jsEntry, $this->js);
    }

    /**
     * Helper function to remove an entry from an array based on filename.
     *
     * @param array $entry The entry to remove.
     * @param array $array The array from which the entry shall be removed.
     *
     * @return void
     */
    protected function removeEntry($entry, &$array)
    {
        foreach (array_keys($array) as $i) {
            if (($array[$i]['file'] ?? '') === ($entry['file'] ?? null)) {
                unset($array[$i]);
                return;
            }
        }
    }

    /**
     * Helper function to insert an entry to an array,
     * also considering priority and dependency, if existing.
     *
     * @param array $entry The entry to insert.
     * @param array $array The array into which the entry shall be inserted.
     *
     * @return void
     */
    protected function insertEntry($entry, &$array)
    {
        if (isset($entry['priority']) || isset($entry['load_after'])) {
            foreach (array_keys($array) as $i) {
                if (isset($entry['priority'])) {
                    $currentPriority = $array[$i]['priority'] ?? null;
                    if (
                        !isset($currentPriority)
                        || $currentPriority > $entry['priority']
                    ) {
                        array_splice($array, $i, 0, [$entry]);
                        return;
                    }
                } elseif (isset($entry['load_after'])) {
                    if ($entry['load_after'] === $array[$i]['file']) {
                        array_splice($array, $i + 1, 0, [$entry]);
                        return;
                    }
                }
            }

            if (isset($entry['load_after'])) {
                throw new \Exception(
                    'Dependency not found: ' . $entry['load_after']
                );
            }
        }

        // Insert at end if either no priority/dependency is given
        // or no other element has been found
        $array[] = $entry;
    }

    /**
     * Get CSS files.
     *
     * @return array
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Get Javascript files.
     *
     * @param string $position Position where the files should be inserted
     * (allowed values are 'header' or 'footer').
     *
     * @return array
     */
    public function getJs(string $position = null)
    {
        if (!isset($position)) {
            return $this->js;
        } else {
            return array_filter(
                $this->js,
                function ($jsFile) use ($position) {
                    return $jsFile['position'] == $position;
                }
            );
        }
    }

    /**
     * Given a colon-delimited configuration string, break it apart, making sure
     * that URLs in the first position are not inappropriately split.
     *
     * @param string $current Setting to parse
     *
     * @return array
     */
    public function parseSetting($current)
    {
        // TODO: replace this method with a deprecation warning when all configs
        // have been converted to arrays
        $parts = explode(':', $current);
        // Special case: don't explode URLs:
        if (
            ($parts[0] === 'http' || $parts[0] === 'https')
            && str_starts_with($parts[1], '//')
        ) {
            $protocol = array_shift($parts);
            $parts[0] = $protocol . ':' . $parts[0];
        }
        return $parts;
    }

    /**
     * Set the encoding.
     *
     * @param string $e New encoding
     *
     * @return void
     */
    public function setEncoding($e)
    {
        $this->encoding = $e;
    }

    /**
     * Get the encoding.
     *
     * @return void
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Set the favicon.
     *
     * @param string|array $favicon New favicon path.
     *
     * @return void
     */
    public function setFavicon($favicon)
    {
        $this->favicon = $favicon;
    }

    /**
     * Get the favicon (null for none).
     *
     * @return string|array|null
     */
    public function getFavicon()
    {
        return $this->favicon;
    }

    /**
     * Set the generator.
     *
     * @param string $generator New generator.
     *
     * @return void
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get the generator.
     *
     * @return string
     */
    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * Remove a CSS file if it matches another file's name
     *
     * @param string $file Filename to remove
     *
     * @return void
     */
    protected function removeCSS($file)
    {
        [$name, ] = explode('.', $file);
        $name .= '.css';
        $index = array_search($name, $this->css);
        if (false !== $index) {
            unset($this->css[$index]);
        }
    }
}
