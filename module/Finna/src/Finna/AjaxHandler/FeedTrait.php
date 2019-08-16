<?php
/**
 * Feed support trait
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Zend\Config\Config;
use Zend\View\Renderer\RendererInterface;

/**
 * Feed support trait
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait FeedTrait
{
    /**
     * Utility function for formatting a RSS feed.
     *
     * @param array             $feed         Feed data
     * @param Config            $config       Feed configuration
     * @param RendererInterface $viewRenderer View renderer
     * @param string|bool       $feedUrl      Feed URL (needed for organisation page
     * RSS-feeds where the feed URL is passed to the FeedContentController as
     * a URL parameter.
     * @param bool              $touchDevice  Whether the feed is being rendered for
     * a touch-enabled device
     *
     * @return array Array with keys:
     *   html (string)    Rendered feed content
     *   settings (array) Feed settings
     */
    protected function formatFeed($feed, Config $config,
        RendererInterface $viewRenderer, $feedUrl = false, $touchDevice = false
    ) {
        $channel = $feed['channel'];
        $items = $feed['items'];
        $config = $feed['config'];
        $modal = $feed['modal'];

        $images
            = isset($config->content['image'])
            ? $config->content['image'] : true;

        $moreLink = !isset($config->moreLink) || $config->moreLink
             ? $channel->getLink() : null;

        $type = $config->type;
        $linkTo = isset($config->linkTo) ? $config->linkTo : null;

        $key = $touchDevice ? 'touch' : 'desktop';
        $linkText = null;
        if (isset($config->linkText[$key])) {
            $linkText = $config->linkText[$key];
        } elseif (isset($config->linkText) && is_string($config->linkText)) {
            $linkText = $config->linkText;
        }

        $feed = [
            'linkText' => $linkText,
            'moreLink' => $moreLink,
            'type' => $type,
            'items' => $items,
            'touchDevice' => $touchDevice,
            'images' => $images,
            'modal' => $modal,
            'feedUrl' => $feedUrl
        ];

        if (isset($config->title)) {
            if ($config->title == 'rss') {
                $feed['title'] = $channel->getTitle();
            } else {
                $feed['translateTitle'] = $config->title;
            }
        }

        if (isset($config->description)) {
            $feed['description'] = $config->description;
        }

        if (isset($config->linkTarget)) {
            $feed['linkTarget'] = $config->linkTarget;
        }

        if (isset($config->visualItems)) {
            $feed['visualItems'] = $config->visualItems;
        }

        $template = strpos($type, 'carousel') !== false ? 'carousel' : $type;
        $html = $viewRenderer->partial("ajax/feed-$template.phtml", $feed);

        $settings = [
            'type' => $type,
            'modal' => $modal
        ];
        if (isset($config->height)) {
            $settings['height'] = $config->height;
        }

        if ('carousel' === $type || 'carousel-vertical' === $type) {
            $settings['images'] = $images;
            $settings['autoplay']
                = isset($config->autoplay) ? $config->autoplay : false;
            $settings['dots']
                = isset($config->dots) ? $config->dots == true : true;
            $settings['scrollSpeed']
                = isset($config->scrollSpeed) ? $config->scrollSpeed : 750;
            $breakPoints = [
                'desktop' => 4, 'desktop-small' => 3, 'tablet' => 2, 'mobile' => 1
            ];

            foreach ($breakPoints as $breakPoint => $default) {
                $settings['slidesToShow'][$breakPoint]
                    = isset($config->itemsPerPage[$breakPoint])
                    ? (int)$config->itemsPerPage[$breakPoint] : $default;

                $settings['scrolledItems'][$breakPoint]
                    = isset($config->scrolledItems[$breakPoint])
                    ? (int)$config->scrolledItems[$breakPoint]
                    : $settings['slidesToShow'][$breakPoint];
            }

            if ('carousel' === $type) {
                $settings['titlePosition']
                    = isset($config->titlePosition) ? $config->titlePosition : null;
            }
        }

        return compact('html', 'settings');
    }
}
