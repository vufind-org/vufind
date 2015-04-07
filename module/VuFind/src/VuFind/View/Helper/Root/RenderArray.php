<?php
/**
 * View helper to render a portion of an array.
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
use Zend\View\Helper\AbstractHelper;

/**
 * View helper to render a portion of an array.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RenderArray extends AbstractHelper
{
    /**
     * Render a portion of an array.
     *
     * @param string $tpl  A template for displaying each row.  This should
     * include %%LABEL%% and %%VALUE%% placeholders
     * @param array  $arr  An associative array of possible values to display
     * @param array  $rows A label => profile key associative array specifying
     * which rows of $arr to display
     *
     * @return string
     */
    public function __invoke($tpl, $arr, $rows)
    {
        $html = '';
        foreach ($rows as $label => $key) {
            if (isset($arr[$key])) {
                $html .= str_replace(
                    ['%%LABEL%%', '%%VALUE%%'],
                    [$label, $this->view->escapeHtml($arr[$key])],
                    $tpl
                );
            }
        }
        return $html;
    }
}