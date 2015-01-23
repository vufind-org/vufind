<?php
/**
 * Header view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Header view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Header extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Url view helper
     *
     * @var Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * Menu items
     *
     * @var Array
     */    
    protected $menuItems;

    /**
     * Constructor
     *
     * @param Zend\Config\Config              $menuConfig Menu configuration
     * @param Zend\I18n\Translator\Translator $translator Translator
     * @param Zend\View\Helper\Url            $urlHelper  Url view helper
     * custom variables
     */
    public function __construct(
        \Zend\Config\Config $menuConfig, 
        \Zend\I18n\Translator\Translator $translator, 
        \Zend\View\Helper\Url $urlHelper
    ) {
        $this->urlHelper = $urlHelper;
        $this->parseMenuConfig($menuConfig, $translator);
    }

    /**
     * Returns header view helper.
     *
     * @return FInna\View\Helper\Root\Header
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Returns rendered header layout.
     *
     * @return string
     */
    public function render()
    {
        return $this->getView()->render('header.phtml');    
    }

    /**
     * Returns menu items as an associative array where each item consists of:
     *    string  $label       Label (untranslated)
     *    string  $url         Url
     *    boolean $route       True if url is a route name. 
     *                        False if url is a literal link.
     *    array   $routeParams Route parameters as a key-value pairs.
     *   
     * @return Array
     */
    public function getMenuItems()
    {
        return $this->menuItems;
    }

    /**
     * Constructs an url for a menu item that may be used in the template.
     *
     * @param array $data menu item configuration
     *   
     * @return string
     */    
    public function getMenuItemUrl(Array $data)
    {
        if (!$data['route']) {
            return $data['url'];
        }
        
        if (isset($data['routeParams'])) {
            return $this->urlHelper->__invoke($data['url'], $data['routeParams']);
        } else {
            return $this->urlHelper->__invoke($data['url']);
        }
    }

    /**
     * Returns a url for changing the site language.
     *
     * The url is constructed by inserting 'lng' query parameter to the current 
     * page url.
     * Note: the returned url does not include possible hash (anchor). Therefore 
     * url's that need to preserve the current hash have to append it to the 
     * returned url on the client side 
     * (see: /themes/finna/js/finna.js::initAnchorNavigationLinks)
     *
     * @param string $lng Language code
     *   
     * @return string
     */    
    public function getLanguageUrl($lng)
    {
        $url = $this->view->serverUrl(true);
        $parts = parse_url($url);

        $params = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $url = substr($url, 0, strpos($url, '?'));
        }
        $params['lng'] = $lng;
        $url .= '?' . http_build_query($params);
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }

    /**
     * Internal function for parsing menu configuration.
     *
     * @param Zend\Config\Config              $menuConfig Menu configuration
     * @param Zend\I18n\Translator\Translator $translator Translator
     *   
     * @return void
     */
    protected function parseMenuConfig(
        \Zend\Config\Config $menuConfig, \Zend\I18n\Translator\Translator $translator
    ) {
        $parseUrl = function ($url) {
            if (preg_match('/^(http|https):\/\//', $url)) {
                // external url
                return array('url' => $url, 'route' => false);
            }

            $data = array('route' => true);

            $needle = 'content-';
            if (($pos = strpos($url, $needle)) === 0) {
                // Content pages do not have static routes, so we 
                // need to add required route parameters for url view helper.
                $page = substr($url, $pos+strlen($needle));
                $data['routeParams'] = array();
                $data['routeParams']['page'] = $page;
                $url = 'content-page';
            }

            $data['url'] = $url;            
            return $data;
        };

        $this->menuItems = array();
        foreach ($menuConfig as $key => $val) {
            if (!count($val)) {
                continue;
            }
            $item = array(
                'label' => $key,
            );

            $desc = 'menu_' . $key . '_desc';
            $descTranslation = $translator->translate($desc);
            if ($desc !== $descTranslation) {                
                $item['desc'] = $key;
            }
            
            $menuItems = array();
            foreach ($val as $childKey => $childVal) {
                $link = $parseUrl($childVal);
                $childItem = array('label' => $childKey);
                $childItem = array_merge($childItem, $link);

                $desc = 'menu_' . $childKey . '_desc';
                $descTranslation = $translator->translate($desc);
                if ($desc !== $descTranslation) {
                    $childItem['desc'] = $childKey;
                }
                $menuItems[$childKey] = $childItem;
            }

            if (count($menuItems) > 1) {
                $item['items'] = $menuItems;
            } else {
                $item = reset($menuItems);
            }

            $this->menuItems[] = $item;
        }
    }
}
