<?php
/**
 * VuFind Configuration Glob Provider
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 5.6
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
 * along with this program; if not, write to the Free Software Foundation,
 * Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config\Provider;

use Webmozart\Glob\Glob as Globber;
use Zend\Config\Factory;

/**
 * VuFind Configuration Glob Provider
 *
 * Provides configuration data whose structure reflects the path of loaded files
 * relative to an optionally specified base path.
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Glob
{
    /**
     * Base length to be stripped off file paths
     *
     * @var int
     */
    protected $baseLen;

    /**
     * Glob pattern for files to be loaded
     *
     * @var string
     */
    protected $pattern;

    /**
     * Glob constructor.
     *
     * @param        $pattern
     * @param string $base
     */
    public function __construct($pattern, $base = '')
    {
        $this->baseLen = strlen($base);
        $this->pattern = $base . $pattern;
    }

    public function __invoke()
    {
        $glob = Globber::glob($this->pattern);
        $data = array_map([$this, 'load'], $glob);
        $list = array_map([$this, 'nest'], $glob, $data);
        return array_merge([], ...$list);
    }

    protected function load($path)
    {
        return Factory::fromFile($path);
    }

    protected function nest($path, $data)
    {
        foreach ($this->getKeys($path) as $key) {
            $data = [$key => $data];
        }
        return $data;
    }

    /**
     * Strips base path and extension and returns the remaining segments
     * in reversed order to be used for nesting the loaded configuration.
     *
     * @param string $path
     *
     * @return array
     */
    protected function getKeys($path)
    {
        $path = substr_replace($path, "", 0, $this->baseLen);
        $offset = strlen(pathinfo($path, PATHINFO_EXTENSION)) + 1;
        $path = trim(substr_replace($path, '', -$offset), '/');
        return array_reverse(explode('/', $path));
    }
}
