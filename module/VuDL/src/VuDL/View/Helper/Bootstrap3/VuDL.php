<?php
/**
 * VuDL view helper
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
namespace VuDL\View\Helper\Bootstrap3;

/**
 * VuDL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class VuDL extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Format technical information.
     *
     * @param string $techInfo Input
     *
     * @return string
     */
    public function formatTechInfo($techInfo)
    {
        $old = [
            '/<(\/[^>]+)>/',
            '/<([^>]+)>/',
            '/\/&gt;/',
            '/&lt;\/div&gt;/',
            '/<div>\s*<\/div>/',
            '/(?<=<div>)([^<]+)<div>/',
            '/<div>/'
        ];
        $new = [
            '&lt;\1&gt;</div>',
            '<div>&lt;\1&gt;',
            '/&gt;</div>',
            '</div>',
            '</div>',
            '<a class="xmlt" onClick="'
                . 'var p=this.parentNode;'
                . "p.className=p.className.indexOf('collapsed')<0"
                . " ? 'xml collapsed'"
                . " : 'xml'"
                . '">\1</a><div>',
            '<div class="xml">'
        ];
        return preg_replace($old, $new, $techInfo);
    }
}