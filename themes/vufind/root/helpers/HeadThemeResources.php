<?php
/**
 * View helper for loading theme-related resources in the header.
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
namespace VuFindThemes\Root\Helpers;
use VuFind\Theme\Tools as ThemeTools,
    Zend\View\Helper\AbstractHelper;

/**
 * View helper for loading theme-related resources in the header.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class HeadThemeResources extends AbstractHelper
{
    /**
     * Set up header items based on contents of theme resource container.
     *
     * @return void
     */
    public function __invoke()
    {
        $resourceContainer = ThemeTools::getResourceContainer();

        // Load CSS:
        $headLink = $this->getView()->plugin('headlink');
        foreach ($resourceContainer->getCss() as $current) {
            $parts = explode(':', $current);
            $headLink()->appendStylesheet(
                trim($parts[0]),
                isset($parts[1]) ? trim($parts[1]) : 'all',
                isset($parts[2]) ? trim($parts[2]) : false
            );
        }

        // Load Javascript:
        $headScript = $this->getView()->plugin('headscript');
        foreach ($resourceContainer->getJs() as $current) {
            $parts =  explode(':', $current);
            $headScript()->appendFile(
                trim($parts[0]),
                'text/javascript',
                isset($parts[1])
                ? array('conditional' => trim($parts[1])) : array()
            );
        }

        // If we have a favicon, load it now:
        $favicon = $resourceContainer->getFavicon();
        if (!empty($favicon)) {
            $imageLink = $this->getView()->plugin('imagelink');
            $headLink(array(
                'href' => $imageLink($favicon),
                'type' => 'image/x-icon', 'rel' => 'shortcut icon'
            ));
        }
    }
}