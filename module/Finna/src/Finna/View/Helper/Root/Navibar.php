<?php
/**
 * Navibar view helper
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

use Zend\Http\Request;

/**
 * Navibar view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Navibar extends \Zend\View\Helper\AbstractHelper
{
    /**
     * View helpers
     *
     * @var array
     */
    protected $viewHelpers = [];

    /**
     * Menu configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Organisation info
     *
     * @var Finna\OrganisationInfo\OrganisationInfo
     */
    protected $organisationInfo;

    /**
     * Menu items
     *
     * @var Array
     */
    protected $menuItems;

    /**
     * Current language
     *
     * @var string
     */
    protected $language;

    /**
     * Router object
     *
     * @var Zend\Router\Http\TreeRouteStack
     */
    protected $router;

    /**
     * Constructor
     *
     * @param Zend\Config\Config              $config           Menu configuration
     * @param OrganisationInfo                $organisationInfo Organisation info
     * @param Zend\Router\Http\TreeRouteStack $router           Route helper
     */
    public function __construct(\Zend\Config\Config $config,
        \Finna\OrganisationInfo\OrganisationInfo $organisationInfo,
        \Zend\Router\Http\TreeRouteStack $router
    ) {
        $this->config = $config;
        $this->organisationInfo = $organisationInfo;
        $this->router = $router;
    }

    /**
     * Returns Navibar view helper.
     *
     * @return FInna\View\Helper\Root\Navibar
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Returns rendered navibar layout.
     *
     * @return string
     */
    public function render()
    {
        return $this->getView()->render('navibar.phtml');
    }

    /**
     * Returns menu items as an associative array where each item consists of:
     *    string  $label       Label (untranslated)
     *    string  $url         Url
     *    boolean $route       True if url is a route name.
     *                         False if url is a literal link.
     *    array   $routeParams Route parameters as a key-value pairs.
     *
     * @param string $lng Language code
     *
     * @return Array
     */
    public function getMenuItems($lng)
    {
        if (!$this->menuItems || $lng != $this->language) {
            $this->language = $lng;
            $this->parseMenuConfig($lng);
        }
        return $this->menuItems;
    }

    /**
     * Constructs an url for a menu item that may be used in the template.
     *
     * @param array $data menu item configuration
     *
     * @return string
     */
    public function getMenuItemUrl(array $data)
    {
        $action = $data['action'];
        $target = $action['target'] ?? null;
        if (!$action || empty($action['url'])) {
            return null;
        }
        if (!$action['route']) {
            return ['url' => $action['url'], 'target' => $target];
        }

        try {
            if (isset($action['routeParams'])) {
                $url =  $this->getViewHelper('url')->__invoke(
                    $action['url'], $action['routeParams']
                );
            } else {
                $url = $this->getViewHelper('url')->__invoke($action['url']);
            }
            return ['url' => $url, 'target' => $target];
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * Returns a url for changing the site language.
     *
     * The url is constructed by appending 'lng' query parameter
     * to the current page url.
     * Note: the returned url does not include possible hash (anchor),
     * which is inserted on the client-side.
     * /themes/finna2/js/finna.js::initAnchorNavigationLinks
     *
     * @param string $lng Language code
     *
     * @return string
     */
    public function getLanguageUrl($lng)
    {
        $url = $this->view->serverUrl(true);
        $parts = parse_url($url);

        $params = [];
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
     * @param string $lng Language code
     *
     * @return void
     */
    protected function parseMenuConfig($lng)
    {
        $translator = $this->getView()->plugin('translate');
        $translationEmpty = $this->getView()->plugin('translationEmpty');

        $parseUrl = function ($url) {
            if (!$url) {
                return null;
            }
            $url = trim($url);

            $data = [];
            if (strpos($url, ',') !== false) {
                list($url, $target) = explode(',', $url, 2);
                $url = trim($url);
                $data['target'] = trim($target);
            }

            if (preg_match('/^(http|https):\/\//', $url)) {
                // external url
                $data['url'] = $url;
                $data['route'] = false;
                return $data;
            }

            $data['route'] = true;
            if (strncmp($url, '/', 1) === 0) {
                $url = $this->view->serverUrl() . $this->router->getBaseUrl() . $url;
                $request = new Request();
                $request->setUri($url);
                $routeMatch = $this->router->match($request);
                if ($routeMatch != null) {
                    $data['routeParams'] = $routeMatch->getParams();
                    $data['url'] = $routeMatch->getMatchedRouteName();
                    return $data;
                }
            }

            $needle = 'content-';
            if (($pos = strpos($url, $needle)) === 0) {
                // Content pages do not have static routes, so we
                // need to add required route parameters for url view helper.
                $page = substr($url, $pos + strlen($needle));
                $data['routeParams'] = [];
                $data['routeParams']['page'] = $page;
                $url = 'content-page';
            }

            $data['url'] = $url;
            return $data;
        };

        $result = [];
        $menuConfig = $this->getMenuData($this->config);
        $menuData = $menuConfig['menuData'];
        $sortData = $menuConfig['sortData'];

        foreach ($menuData as $menuKey => $items) {
            $item = [
                'id' => $menuKey, 'label' => "menu_$menuKey",
            ];

            $desc = 'menu_' . $menuKey . '_desc';
            if ($translator->translate($desc, null, false) !== false) {
                $item['desc'] = $desc;
            }

            $options = [];
            foreach ($items as $itemKey => $action) {
                if (!is_string($action)) {
                    $action = $action[$lng] ?? null;
                }

                if (strncmp($action, 'metalib-', 8) === 0) {
                    // Discard MetaLib menu items
                    continue;
                }

                $option = [
                    'id' => $itemKey, 'label' => "menu_$itemKey",
                    'action' => $parseUrl($action)
                ];

                $desc = 'menu_' . $itemKey . '_desc';
                if (!$translationEmpty($desc)) {
                    $option['desc'] = $desc;
                }
                $options[] = $option;
            }
            if (empty($options)) {
                continue;
            } else {
                $item['items'] = $options;
                $result[] = $item;
            }
        }

        $menuItems = $this->sortMenuItems($result, $sortData);

        foreach ($menuItems as $menuKey => $option) {
            foreach ($option['items'] as $itemKey => $item) {
                if (!$item['action'] || !$this->menuItemEnabled($item)) {
                    unset($menuItems[$menuKey]['items'][$itemKey]);
                }
            }
            $menuItems[$menuKey]['items']
                = array_values($menuItems[$menuKey]['items']);

            if (isset($menuItems[$menuKey]['items'])
                && empty($menuItems[$menuKey]['items'])
            ) {
                unset($menuItems[$menuKey]);
            }
        }

        $this->menuItems = $menuItems;
    }

    /**
     * Check if menu item may be enabled.
     *
     * @param array $item Menu item configuration
     *
     * @return boolean
     */
    protected function menuItemEnabled($item)
    {
        $action = $item['action'];
        if (!$action) {
            return false;
        }
        if (empty($action['route'])) {
            return true;
        }

        $url = $action['url'];

        if (strncmp($url, 'combined-', 9) === 0) {
            return $this->getViewHelper('combined')->isAvailable();
        }
        if (strncmp($url, 'metalib-', 8) === 0) {
            return false;
        }
        if (strncmp($url, 'primo-', 6) === 0) {
            return $this->getViewHelper('primo')->isAvailable();
        }
        if ($url === 'browse-database') {
            return $this->getViewHelper('browse')->isAvailable('Database');
        }
        if ($url === 'browse-journal') {
            return $this->getViewHelper('browse')->isAvailable('Journal');
        }
        if ($url === 'organisationinfo-home') {
            return $this->getViewHelper('organisationInfo')->isAvailable();
        }
        if ($url === 'authority-home') {
            return $this->getViewHelper('authority')->isAvailable();
        }
        return true;
    }

    /**
     * Separate menu data from menu order data (__[menu]_sort__ sections).
     *
     * Returns an associative array with keys:
     *  'menuData' Menu items
     *  'sortData' Order data
     *
     * @param array $config Menu configuration
     *
     * @return array
     */
    protected function getMenuData($config)
    {
        $menuData = $sortDataOrder = $sortData = [];

        foreach ($config as $menuKey => $items) {
            if ($menuKey === 'Parent_Config') {
                continue;
            }

            if (!count($items)) {
                continue;
            }

            if (preg_match('/^__(.*)_sort__$/', $menuKey, $matches)) {
                // Sort section
                $menuKey = $matches[1];
                $items = $items->toArray();
                // Re-order menu-level sort entries in descending order
                asort($items);
                $sortData[$menuKey] = $items;

                if (isset($items['__MENU__'])) {
                    // Top-level menu position
                    $sortDataOrder[$items['__MENU__']] = $menuKey;
                }
                continue;
            }
            // Menu section
            $menuData[$menuKey] = $items;
        }

        // Re-order top-level sort entries in descending order
        $sortDataProcessed = [];
        ksort($sortDataOrder);

        foreach ($sortDataOrder as $index => $menuKey) {
            $sortDataProcessed[$menuKey] = $sortData[$menuKey];
            unset($sortData[$menuKey]);
        }
        $sortData = array_merge($sortDataProcessed, $sortData);

        return ['menuData' => $menuData, 'sortData' => $sortData];
    }

    /**
     * Sort menu items
     *
     * @param array $items Menu items
     * @param array $order Ordering
     *
     * @return array Sorted items
     */
    protected function sortMenuItems($items, $order)
    {
        foreach ($order as $menuKey => $order) {
            $menuPosition
                = $this->getItemIndex($items, $menuKey);
            if ($menuPosition === null) {
                continue;
            }
            if (isset($order['__MENU__'])) {
                // Re-position top-level menu
                $position = $order['__MENU__'];
                $items = $this->moveItem(
                    $items, $menuPosition, $position
                );
                $menuPosition = $position;
                unset($order['__MENU__']);
            }
            foreach ($order as $item => $position) {
                // Re-position single menu item
                if ($menuPosition === null) {
                    continue;
                }
                if (!isset($items[$menuPosition])) {
                    continue;
                }
                $currentPosition = $this->getItemIndex(
                    $items[$menuPosition]['items'], $item
                );
                if ($currentPosition === null) {
                    continue;
                }
                $items[$menuPosition]['items']
                    = $this->moveItem(
                        $items[$menuPosition]['items'],
                        $currentPosition, $position
                    );
            }
        }
        return $items;
    }

    /**
     * Get menu item index
     *
     * @param array  $items Menu items
     * @param string $id    Menu item id
     *
     * @return mixed null|int
     */
    protected function getItemIndex($items, $id)
    {
        $cnt = 0;
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $cnt;
            }
            $cnt++;
        }
        return null;
    }

    /**
     * Move menu item
     *
     * @param array $items Menu items
     * @param int   $from  From (index)
     * @param int   $to    To (index)
     *
     * @return array Items
     */
    protected function moveItem($items, $from, $to)
    {
        if ($from < 0 || $to < 0) {
            return $items;
        }
        $move = array_splice($items, $from, 1);
        array_splice($items, $to, 0, $move);
        return $items;
    }

    /**
     * Return view helper
     *
     * @param string $id Helper id
     *
     * @return \Zend\View\Helper
     */
    protected function getViewHelper($id)
    {
        if (!isset($this->viewHelpers[$id])) {
            $this->viewHelpers[$id] = $this->getView()->plugin($id);
        }
        return $this->viewHelpers[$id];
    }
}
