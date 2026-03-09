<?php

/**
 * AlphaBrowse view helper
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\Url;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * AlphaBrowse view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AlphaBrowse
{
    /**
     * Constructor
     *
     * @param Url   $url     URL helper
     * @param array $options Additional configuration options
     */
    public function __construct(
        #[Autowire(container: 'ViewHelperManager')]
        protected Url $url,
        #[Autowire(config: 'config', path: 'AlphaBrowse', default: [])]
        protected array $options = []
    ) {
    }

    /**
     * Get link to browse results (or null if no valid URL available)
     *
     * @param string $source AlphaBrowse index currently being used
     * @param array  $item   Item to link to
     *
     * @return string
     */
    public function getUrl($source, $item)
    {
        if ($item['count'] <= 0) {
            return null;
        }

        $query = [
            'type' => ucwords($source) . 'Browse',
            'lookfor' => $this->escapeForSolr($item['sort_key']),
        ];
        if ($this->options['bypass_default_filters'] ?? true) {
            $query['dfApplied'] = 1;
        }
        if ($item['count'] == 1) {
            $query['jumpto'] = 1;
        }
        return ($this->url)('search-results', [], ['query' => $query]);
    }

    /**
     * Escape a string for inclusion in a Solr query.
     *
     * @param string $str String to escape
     *
     * @return string
     */
    protected function escapeForSolr($str)
    {
        return '"' . addcslashes($str, '"') . '"';
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
