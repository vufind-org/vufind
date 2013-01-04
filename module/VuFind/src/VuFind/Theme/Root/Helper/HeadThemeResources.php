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
namespace VuFind\Theme\Root\Helper;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper for loading theme-related resources in the header.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class HeadThemeResources extends AbstractServiceLocator
{
    /**
     * Get the theme tools.
     *
     * @return \VuFind\Theme\Tools
     */
    public function getThemeTools()
    {
        return $this->getServiceLocator()->get('VuFindTheme\Tools');
    }

    /**
     * Set up header items based on contents of theme resource container.
     *
     * @return void
     */
    public function __invoke()
    {
        $resourceContainer = $this->getThemeTools()->getResourceContainer();

        // Set up encoding:
        $headMeta = $this->getView()->plugin('headmeta');
        $headMeta()->appendHttpEquiv(
            'Content-Type', 'text/html; charset=' . $resourceContainer->getEncoding()
        );

        // Load CSS (make sure we prepend them in the appropriate order; theme
        // resources should load before extras added by individual templates):
        $headLink = $this->getView()->plugin('headlink');
        foreach (array_reverse($resourceContainer->getCss()) as $current) {
            $parts = explode(':', $current);
            $headLink()->prependStylesheet(
                trim($parts[0]),
                isset($parts[1]) ? trim($parts[1]) : 'all',
                isset($parts[2]) ? trim($parts[2]) : false
            );
        }

        // Load Javascript (same ordering considerations as CSS, above):
        $headScript = $this->getView()->plugin('headscript');
        foreach (array_reverse($resourceContainer->getJs()) as $current) {
            $parts =  explode(':', $current);
            $headScript()->prependFile(
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