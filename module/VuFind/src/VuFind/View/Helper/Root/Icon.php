<?php
/**
 * Icon view helper
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

use Laminas\View\Helper\AbstractHelper;
use VuFindTheme\ThemeInfo;

/**
 * Icon view helper
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
     * Icon config from theme.config.php
     *
     * @var array
     */
    protected $config;

    /**
     * Default icon set
     *
     * @var string
     */
    protected $defaultSet;

    /**
     * Transforming map
     *
     * @var array
     */
    protected $iconMap;

    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo Theme info helper
     */
    public function __construct(ThemeInfo $themeInfo)
    {
        $this->config = $themeInfo->getMergedConfig('icons');
        $this->defaultSet = $this->config['defaultSet'] ?? 'FontAwesome';
        $this->iconMap = $this->config['aliases'] ?? [];
    }

    /**
     * Returns inline HTML for icon
     *
     * @param string $name  Which icon?
     * @param array  $extra Just extra HTML attributes for now
     */
    public function __invoke($name, $extra = []): string
    {
        $icon = $this->iconMap[$name] ?? $name;
        $set = $this->defaultSet;

        // Override set from config (ie. FontAwesome:icon)
        if (strpos($icon, ':') !== false) {
            [$set, $icon] = explode(':', $icon, 2);
        }

        // Find set in theme.config.php
        $setConfig = $this->config['sets'][$set] ?? [];
        $template = $setConfig['template'] ?? $set;

        // Compile attitional HTML attributes
        $attrs = '';
        $escAttr = $this->getView()->plugin('escapeHtmlAttr');
        foreach ($extra as $key => $val) {
            $attrs .= ' ' . $key . '="' . $escAttr($val) . '"';
        }

        // Surface set config and add icon and attrs
        return $this->getView()->render(
            'Helpers/icons/' . $template,
            array_merge($setConfig, ['icon' => $escAttr($icon), 'attrs' => $attrs])
        );
    }
}
