<?php
/**
 * Authentication view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\Url;

/**
 * Authentication view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AlphaBrowse extends \Zend\View\Helper\AbstractHelper
{
    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param Url $helper URL helper
     */
    public function __construct(Url $helper)
    {
        $this->url = $helper;
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

        // Linking using bib ids is generally more reliable than doing searches for
        // headings, but headings give shorter queries and don't look as strange.
        if ($item['count'] < 5) {
            $safeIds = array_map([$this, 'escapeForSolr'], $item['ids']);
            $query = ['type' => 'ids', 'lookfor' => implode(' ', $safeIds)];
            if ($item['count'] == 1) {
                $query['jumpto'] = 1;
            }
        } else {
            $query = [
                'type' => ucwords($source) . 'Browse',
                'lookfor' => $this->escapeForSolr($item['heading']),
            ];
        }
        return $this->url->__invoke('search-results', [], ['query' => $query]);
    }

    /**
     * Escape a string for inclusion in a Solr query.
     *
     * @param type $str String to escape
     *
     * @return string
     */
    protected function escapeForSolr($str)
    {
        return '"' . addcslashes($str, '"') . '"';
    }
}