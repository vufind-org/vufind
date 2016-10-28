<?php
/**
 * MobileMenu view helper
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\jQueryMobile;
use Zend\View\Helper\AbstractHelper;

/**
 * MobileMenu view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MobileMenu extends AbstractHelper
{
    /**
     * Display the top menu.
     *
     * @param array $extras Associative array of extra parameters to send to the
     * view template.
     *
     * @return string
     */
    public function header($extras = [])
    {
        $context = $this->getView()->plugin('context');
        return $context($this->getView())->renderInContext('header.phtml', $extras);
    }

    /**
     * Display the bottom menu.
     *
     * @param array $extras Associative array of extra parameters to send to the
     * view template.
     *
     * @return string
     */
    public function footer($extras = [])
    {
        $context = $this->getView()->plugin('context');
        return $context($this->getView())->renderInContext('footer.phtml', $extras);
    }
}
