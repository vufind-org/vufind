<?php

/**
 * Bulk action view helper.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Export;
use VuFind\Feature\BulkActionTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Bulk action view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BulkAction
{
    use BulkActionTrait;

    /**
     * CSS class for button.
     *
     * @var ?string
     */
    protected $buttonClass = null;

    /**
     * Constructor.
     *
     * @param Export                 $export        Export support class
     * @param ConfigManagerInterface $configManager Configuration manager
     * @param RendererInterface      $view          View renderer
     */
    #[Autowire]
    public function __construct(
        protected Export $export,
        protected ConfigManagerInterface $configManager,
        protected RendererInterface $view
    ) {
    }

    /**
     * Get a bulk action button.
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
        return $this->view->render(
            'Helpers/bulk-action-button.phtml',
            compact('action', 'icon', 'content', 'limit', 'attributes')
        );
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
