<?php

/**
 * View helper to render a portion of an array.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to render a portion of an array.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RenderArray extends AbstractHelper
{
    /**
     * Render a portion of an array.
     *
     * @param string $tpl  A template for displaying each row. This should
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
        $translate = $this->view->plugin('translate');
        foreach ($rows as $label => $key) {
            if (isset($arr[$key])) {
                $value = $arr[$key] instanceof \VuFind\I18n\TranslatableString
                    ? $translate($arr[$key]) : $arr[$key];
                $html .= str_replace(
                    ['%%LABEL%%', '%%VALUE%%'],
                    [$label, $this->view->escapeHtml($value)],
                    $tpl
                );
            }
        }
        return $html;
    }
}
