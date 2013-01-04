<?php
/**
 * Head link view helper (extended for VuFind's theme system)
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
use Zend\ServiceManager\ServiceLocatorInterface,
    Zend\ServiceManager\ServiceLocatorAwareInterface;

/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class HeadLink extends \Zend\View\Helper\HeadLink
    implements ServiceLocatorAwareInterface
{
    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return AbstractServiceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        // The service locator passed in here is a Zend\View\HelperPluginManager;
        // we want to pull out the main Zend\ServiceManager\ServiceManager.
        $this->serviceLocator = $serviceLocator->getServiceLocator();
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Get the theme tools.
     *
     * @return \VuFind\Theme\ThemeInfo
     */
    public function getThemeInfo()
    {
        return $this->getServiceLocator()->get('VuFindTheme\ThemeInfo');
    }

    /**
     * Create HTML link element from data item
     *
     * @param stdClass $item data item
     *
     * @return string
     */
    public function itemToString(\stdClass $item)
    {
        // Normalize href to account for themes, then call the parent class:
        $relPath = 'css/' . $item->href;
        $currentTheme = $this->getThemeInfo()->findContainingTheme($relPath);

        if (!empty($currentTheme)) {
            $urlHelper = $this->getView()->plugin('url');
            $item->href = $urlHelper('home') . "themes/$currentTheme/" . $relPath;
        }

        return parent::itemToString($item);
    }
}