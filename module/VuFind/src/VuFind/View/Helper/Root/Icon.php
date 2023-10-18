<?php

/**
 * Icon view helper
 *
 * PHP version 8
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

use Laminas\Cache\Storage\StorageInterface;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\EscapeHtmlAttr;

use function in_array;
use function is_string;

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
     * Default icon template
     *
     * @var string
     */
    protected $defaultTemplate;

    /**
     * Transforming map
     *
     * @var array
     */
    protected $iconMap;

    /**
     * Cache for icons
     *
     * @var StorageInterface
     */
    protected $cache;

    /**
     * Escape helper
     *
     * @var EscapeHtmlAttr
     */
    protected $esc;

    /**
     * Are we in right to left text mode?
     *
     * @var boolean
     */
    protected $rtl;

    /**
     * Prevent extra work by only appending the stylesheet once
     *
     * @var boolean
     */
    protected $styleAppended = false;

    /**
     * Constructor
     *
     * @param array            $config  Icon configuration
     * @param StorageInterface $cache   Cache instance
     * @param EscapeHtmlAttr   $escAttr EscapeHtmlAttr view helper
     * @param bool             $rtl     Are we in right to left text mode?
     */
    public function __construct(
        array $config,
        StorageInterface $cache,
        EscapeHtmlAttr $escAttr,
        bool $rtl = false
    ) {
        $this->config = $config;
        $this->defaultSet = $this->config['defaultSet'] ?? 'FontAwesome';
        $this->defaultTemplate = $this->config['defaultTemplate'] ?? 'font';
        $this->iconMap = $this->config['aliases'] ?? [];
        $this->cache = $cache;
        $this->esc = $escAttr;
        $this->rtl = $rtl;
    }

    /**
     * Map icon to set. Add prefix, return with set and template.
     * Broken out for easier customization.
     *
     * @param string $name       Icon name or key from theme.config.php
     * @param array  $aliasTrail Safety mechanism to prevent circular aliases
     *
     * @return array
     */
    protected function mapIcon(string $name, $aliasTrail = []): array
    {
        $rtl = $this->rtl ? '-rtl' : '';
        $icon = $this->iconMap[$name . $rtl] ?? $this->iconMap[$name] ?? $name;
        $set = $this->defaultSet;
        $class = null;

        // Override set from config (ie. FontAwesome:icon)
        if (str_contains($icon, ':')) {
            $parts = explode(':', $icon, 3);
            $set = $parts[0];
            $icon = $parts[1];
            $class = $parts[2] ?? null;
        }

        // Special case: aliases:
        if ($set === 'Alias') {
            $aliasTrail[] = $name;
            if (in_array($icon, $aliasTrail)) {
                throw new \Exception("Circular icon alias detected: $icon!");
            }
            return $this->mapIcon($icon, $aliasTrail);
        }

        // Find set in theme.config.php
        $setConfig = $this->config['sets'][$set] ?? [];
        $template = $setConfig['template'] ?? $this->defaultTemplate;
        $prefix = $setConfig['prefix'] ?? '';

        return [$prefix . $icon, $set, $template, $class];
    }

    /**
     * Reduce extra parameters to one attribute string.
     * Broken out for easier customization.
     *
     * @param array $attrs Additional HTML attributes for the HTML tag
     *
     * @return string
     */
    protected function compileAttrs(array $attrs): string
    {
        $attrStr = '';
        foreach ($attrs as $key => $val) {
            // class gets special handling in the template; don't use it now:
            if ($key == 'class') {
                continue;
            }
            $attrStr .= ' ' . $key . '="' . ($this->esc)($val) . '"';
        }
        return $attrStr;
    }

    /**
     * Create a unique key for icon names and extra attributes
     *
     * @param string $name  Icon name or key from theme.config.php
     * @param array  $attrs Additional HTML attributes for the HTML tag
     *
     * @return string
     */
    protected function cacheKey(string $name, $attrs = []): string
    {
        if (empty($attrs)) {
            return $name;
        }
        ksort($attrs);
        return $name . '+' . md5(json_encode($attrs));
    }

    /**
     * Returns inline HTML for icon
     *
     * @param string       $name  Which icon?
     * @param array|string $attrs Additional HTML attributes
     *
     * @return string
     */
    public function __invoke(string $name, $attrs = []): string
    {
        // Class name shortcut
        if (is_string($attrs)) {
            $attrs = ['class' => $attrs];
        }

        $cacheKey = $this->cacheKey($name, $attrs);
        $cached = $this->cache->getItem($cacheKey);

        if ($cached == null) {
            [$icon, $set, $template, $class] = $this->mapIcon($name);
            $attrs['class'] = trim(($attrs['class'] ?? '') . ' ' . $class);

            // Surface set config and add icon and attrs
            $cached = trim(
                $this->getView()->render(
                    'Helpers/icons/' . $template,
                    array_merge(
                        $this->config['sets'][$set] ?? [],
                        [
                            'icon' => $icon,
                            'attrs' => $this->compileAttrs($attrs),
                            'extra' => $attrs,
                        ]
                    )
                )
            );

            $this->cache->setItem($cacheKey, $cached);
        }

        return $cached;
    }
}
