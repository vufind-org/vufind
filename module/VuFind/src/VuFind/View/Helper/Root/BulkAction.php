<?php

/**
 * Bulk action view helper
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Bulk action view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BulkAction extends \Laminas\View\Helper\AbstractHelper
{
    use \VuFind\Feature\BulkActionTrait;

    /**
     * CSS class for button
     *
     * @var ?string
     */
    protected $buttonClass = null;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Export support class
     *
     * @var \VuFind\Export
     */
    protected $export;

    /**
     * Constructor
     *
     * @param \VuFind\Export               $export       Export support class
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Export $export, \VuFind\Config\PluginManager $configLoader)
    {
        $this->export = $export;
        $this->configLoader = $configLoader;
    }

    /**
     * Get a bulk action button
     *
     * @param string $action     Action name
     * @param string $icon       Icon identifier
     * @param string $content    Content of the button
     * @param array  $attributes Button element attributes
     *
     * @return string
     */
    public function button($action, $icon, $content, $attributes = [])
    {
        $limit = $this->getBulkActionLimit($action);
        if ($limit == 0) {
            return '';
        }
        if (!empty($this->buttonClass)) {
            $attributes['class'] = $this->buttonClass;
        }
        $attributes['value'] = '1';
        $attributes['type'] = 'submit';
        $attributes['name'] = $action;
        $attributes['data-item-limit'] = $limit;
        return $this->getView()->render(
            'Helpers/bulk-action-button.phtml',
            compact('action', 'icon', 'content', 'limit', 'attributes')
        );
    }
}
