<?php
/**
 * VuFind Configuration Glob Provider
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 7
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

use VuFind\Config\Filter\Load;
use VuFind\Config\Filter\Nest;
use Webmozart\Glob\Glob as Globber;
use Zend\EventManager\FilterChain;

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
class Base
{
    /**
     * @var FilterChain
     */
    protected $filterChain;

    /**
     * Glob pattern for files to be loaded
     *
     * @var string
     */
    protected $pattern;

    /**
     * Glob constructor.
     *
     * @param string $base    Absolute base path prepended to pattern
     * @param string $pattern Relative glob pattern
     */
    public function __construct(string $base, string $pattern)
    {
        $base = realpath($base);
        $baseLen = strlen($base) + 1;
        $this->pattern = "$base/$pattern";
        $this->filterChain = new FilterChain;
        $this->filterChain->attach(new Load, 10000);
        $this->filterChain->attach(new Nest($baseLen), -10000);
    }

    /**
     * Provides the merged configuration data of all loaded files.
     *
     * @return array
     */
    public function __invoke(): array
    {
        $glob = Globber::glob($this->pattern);
        $list = array_map([$this, 'loadFile'], $glob);
        return array_replace_recursive([], ...$list);
    }

    public function loadFile(string $path): array
    {
        return $this->filterChain->run($this, [$path]);
    }

    /**
     * @return FilterChain
     */
    public function getFilterChain(): FilterChain
    {
        return $this->filterChain;
    }

}
