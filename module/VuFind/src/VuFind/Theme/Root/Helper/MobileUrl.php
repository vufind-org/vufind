<?php
/**
 * Mobile URL view helper
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;

/**
 * Mobile URL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class MobileUrl extends AbstractServiceLocator
{
    /**
     * Return the mobile version of the current URL if the user is on a mobile device
     * and might want to switch over.  Return false when not on a mobile device.
     *
     * @return string
     */
    public function __invoke()
    {
        $mobile = $this->getServiceLocator()->get('VuFindTheme\Mobile');

        // Do nothing special if we're not on a mobile device or no mobile theme is
        // enabled:
        if (!$mobile->enabled() || !$mobile->detect()) {
            return false;
        }

        $urlHelper = $this->getView()->plugin('serverurl');
        $currentUrl = rtrim($urlHelper(true), '?');
        $currentUrl .= strstr($currentUrl, '?') ? '&' : '?';
        return $currentUrl .= 'ui=mobile';
    }
}