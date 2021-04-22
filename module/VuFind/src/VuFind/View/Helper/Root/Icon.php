<?php
/**
 * Make link view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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

use Laminas\Config\Config;
use Laminas\View\Helper\AbstractHelper;
use VuFind\Config\PluginManager as ConfigManager;

/**
 * Make link view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Icon extends AbstractHelper
{
    /**
     * Icons config (icons.ini)
     *
     * @var Config
     */
    protected $config;

    /**
     * Transforming map
     *
     * @var array
     */
    protected $nameMap;

    /**
     * Constructor
     *
     * @param Config $config Icons config (icons.ini)
     */
    public function __construct(Config $config)
    {
        $this->config = $config->Config ?? new Config([]);
        $this->iconMap
            = $config[$config->Config->use ?? 'FontAwesome'] ?? new Config([]);
    }

    /**
     * Returns inline HTML for icon
     *
     * @param string $name  Which icon?
     * @param array  $extra Just extra HTML attributes for now
     *
     * @return string|bool
     */
    public function __invoke($name, $extra = [])
    {
        $icon = $this->iconMap[$name] ?? $name;
        $template = $this->config->use;

        // Override template from config (ie. FontAwesome:icon)
        if (strpos($icon, ':') !== false) {
            list($template, $icon) = explode(':', $icon, 2);
        }

        $attrs = '';
        $escAttr = $this->getView()->plugin('escapeHtmlAttr');
        foreach ($extra as $key => $val) {
            $attrs .= ' ' . $key . '="' . $escAttr($val) . '"';
        }

        return $this->getView()->render(
            'Helpers/icons/' . $template,
            ['icon' => $icon, 'attrs' => $attrs]
        );
    }
}
