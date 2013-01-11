<?php
/**
 * Truncate view helper
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
 * Truncate view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Truncate extends AbstractHelper
{
    /**
     * Truncate a string
     *
     * @param string $str    the string to be truncated
     * @param string $len    how long the truncated string will be
     * @param string $append what to add to the end of the string to
     * indicate it's been truncated
     *
     * @return string
     */
    public function __invoke($str, $len, $append = '...')
    {
        if ($len == 0) {
            return '';
        } else if (strlen($str) > $len) {
            if (function_exists('mb_substr')) {
                return trim(mb_substr($str, 0, $len, 'UTF-8')) . $append;
            } else {
                return trim(substr($str, 0, $len)) . $append;
            }
        }
        return $str;
    }
}