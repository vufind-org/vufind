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

use Laminas\Cache\Storage\StorageInterface;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\HeadLink;

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
     * HeadLink helper
     *
     * @var HeadLink
     */
    protected $headLink;

    /**
     * Prevent extra work by only appending the stylesheet once
     *
     * @var boolean
     */
    protected $styleAppended = false;

    /**
     * Constructor
     *
     * @param array            $config   Icon configuration
     * @param StorageInterface $cache    Cache instance
     * @param EscapeHtmlAttr   $escAttr  EscapeHtmlAttr view helper
     * @param HeadLink         $headLink HeadLink view helper
     */
    public function __construct(
        array $config,
        StorageInterface $cache,
        EscapeHtmlAttr $escAttr,
        HeadLink $headLink
    ) {
        $this->config = $config;
        $this->defaultSet = $this->config['defaultSet'] ?? 'FontAwesome';
        $this->defaultTemplate = $this->config['defaultTemplate'] ?? 'font';
        $this->iconMap = $this->config['aliases'] ?? [];
        $this->cache = $cache;
        $this->esc = $escAttr;
        $this->headLink = $headLink;
    }

    /**
     * Map icon to set. Add prefix, return with set and template.
     * Broken out for easier customization.
     *
     * @param string $name Icon name or key from theme.config.php
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
        $template = $setConfig['template'] ?? $this->defaultTemplate;
        $prefix = $setConfig['prefix'] ?? '';

        return [$prefix . $icon, $set, $template];
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
        if (!$this->styleAppended) {
            $this->headLink->appendStylesheet('icon-helper.css');
            $this->styleAppended = true;
        }

        // Class name shortcut
        if (is_string($attrs)) {
            $attrs = ['class' => $attrs];
        }

        $cacheKey = $this->cacheKey($name, $attrs);
        $cached = $this->cache->getItem($cacheKey);

        if ($cached == null) {
            [$icon, $set, $template] = $this->mapIcon($name);

            // Surface set config and add icon and attrs
            $cached = $this->getView()->render(
                'Helpers/icons/' . $template,
                array_merge(
                    $this->config['sets'][$set] ?? [],
                    [
                        'icon' => ($this->esc)($icon),
                        'attrs' => $this->compileAttrs($attrs),
                        'extra' => $attrs
                    ]
                )
            );

            $this->cache->setItem($cacheKey, $cached);
        }

        return $cached;
    }
}
