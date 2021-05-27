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
use Laminas\View\Helper\EscapeHtmlAttr;
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
        $this->config = $themeInfo->getMergedConfig('icons', true);
        $this->defaultSet = $this->config['defaultSet'] ?? 'FontAwesome';
        $this->iconMap = $this->config['aliases'] ?? [];
    }

    /**
     * Map icon to set. Add prefix, return with set and template
     * Broken out for easier customization
     *
     * @param string $name Which icon?
     *
     * @return array
     */
    protected function mapIcon(string $name): array
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
        $prefix = $setConfig['prefix'] ?? '';

        return [$prefix . $icon, $set, $template];
    }

    /**
     * Reduce extra parameters to one attribute string
     * Broken out for easier customization
     *
     * @param array          $extra   Just extra HTML attributes for now
     * @param EscapeHtmlAttr $escAttr EscapeHtmlAttr view helper
     *
     * @return string
     */
    protected function compileAttrs(array $extra, EscapeHtmlAttr $escAttr): string
    {
        $attrs = '';
        foreach ($extra as $key => $val) {
            $attrs .= ' ' . $key . '="' . $escAttr($val) . '"';
        }
        return $attrs;
    }

    /**
     * Returns inline HTML for icon
     *
     * @param string $name  Which icon?
     * @param array  $extra Just extra HTML attributes for now
     *
     * @return string
     */
    public function __invoke(string $name, $extra = []): string
    {
        [$icon, $set, $template] = $this->mapIcon($name);

        // Compile attitional HTML attributes
        $escAttr = $this->getView()->plugin('escapeHtmlAttr');
        $attrs = $this->compileAttrs($extra, $escAttr);

        // Surface set config and add icon and attrs
        return $this->getView()->render(
            'Helpers/icons/' . $template,
            array_merge(
                $this->config['sets'][$set] ?? [],
                [
                    'icon' => $escAttr($icon),
                    'attrs' => $attrs,
                    'extra' => $extra
                ]
            )
        );
    }
}
