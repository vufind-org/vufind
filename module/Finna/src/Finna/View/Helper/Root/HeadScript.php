<?php
/**
 * Head script view helper (extended for VuFind's theme system)
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root;
use VuFindTheme\ThemeInfo;
use Zend\Http\Request;

/**
 * Head script view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadScript extends \VuFindTheme\View\Helper\HeadScript
{
    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo    Theme information service
     * @param Request   $request      Request
     */
    public function __construct(ThemeInfo $themeInfo, Request $request) {
        parent::__construct($themeInfo);
        $this->request = $request;
    }

    /**
     * Create script HTML
     *
     * @param mixed  $item        Item to convert
     * @param string $indent      String to add before the item
     * @param string $escapeStart Starting sequence
     * @param string $escapeEnd   Ending sequence
     *
     * @return string
     */
    public function itemToString($item, $indent, $escapeStart, $escapeEnd)
    {
        if (!empty($item->attributes['src'])) {
            $agent = $this->request->getHeader('User-Agent')->toString();
            if (strstr($agent, 'MSIE 8.0') || strstr($agent, 'MSIE 7.0')) {
                if ($item->attributes['src'] == 'vendor/jquery.min.js') {
                    $item->attributes['src'] = 'vendor/jquery-1.12.1.min.js';
                }
            }
        }
        return parent::itemToString($item, $indent, $escapeStart, $escapeEnd);
    }
}
