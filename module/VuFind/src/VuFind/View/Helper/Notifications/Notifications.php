<?php

/**
 * Notifications view helper
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
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
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Notifications;

use ElGigi\CommonMarkEmoji\EmojiExtension;
use Laminas\View\Helper\AbstractHelper;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use VuFind\Db\Service\NotificationsBroadcastsServiceInterface;
use VuFind\Db\Service\NotificationsPagesServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function in_array;
use function is_array;

/**
 * Notifications view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Notifications extends AbstractHelper implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Session containing Notification status information
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

    /**
     * SessionManager
     *
     * @var \Laminas\Session\SessionManager
     */
    protected $sessionManager;

    /**
     * Database
     *
     * @var mixed
     */
    private $database;

    /**
     * Notifications config
     *
     * @var mixed
     */
    private $config;

    /**
     * Default language
     *
     * @var mixed
     */
    private $defaultLanguage;

    /**
     * Constructor
     *
     * @param PluginManager $database Database
     * @param mixed         $config   Notifications config
     */
    public function __construct(\VuFind\Db\Table\PluginManager $database,
                                $config,
                                protected NotificationsBroadcastsServiceInterface $broadcastsService,
                                protected NotificationsPagesServiceInterface $pagesService)
    {
        $this->database = $database;
        $this->config = $config;
        if (!empty($this->config['Notifications']['languages'])) {
            $this->defaultLanguage = $this->config['Notifications']['languages'][0];
        }
    }

    /**
     * Get all pages in the current user language, sorted by priority and id
     */
    public function getPages()
    {
        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        // Retrieve all pages from the database in the current language
        $pagesSelection = $this->pagesService->getPagesList(['visibility' => true, 'language' => $this->getTranslatorLocale()], 'priority ASC, page_id ASC');

        // Retrieve pages in all configured languages as fallback
        $pagesSelectionByLanguage = [];
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale()) {
                $pagesInLanguage = $this->pagesService->getPagesList(['visibility' => true, 'language' => $language], 'priority ASC, page_id ASC');
                $pagesSelectionByLanguage[$language] = array_column($pagesInLanguage, null, 'page_id');
            }
        }

        // If the nav_title in the selected language is empty, check other languages in order of configuration
        foreach ($pagesSelection as &$pageSelection) {
            if (empty($pageSelection['nav_title']) || $pageSelection['nav_title'] == '') {
                foreach ($this->config['Notifications']['languages'] as $language) {
                    if ($language !== $this->getTranslatorLocale() && isset($pagesSelectionByLanguage[$language][$pageSelection['page_id']])) {
                        $fallbackPage = $pagesSelectionByLanguage[$language][$pageSelection['page_id']];
                        if (!empty($fallbackPage['nav_title'])) {
                            $pageSelection['nav_title'] = $fallbackPage['nav_title'];
                            if (!empty($fallbackPage['content'])) {
                                $pageSelection['content'] = $fallbackPage['content'];
                            }
                            if (!empty($fallbackPage['headline'])) {
                                $pageSelection['headline'] = $fallbackPage['headline'];
                            }
                            break;
                        }
                    }
                }
            }
        }

        // Check if pages are missing, and include versions from other languages if that is the case
        $pagesSelectionIds = array_column($pagesSelection, 'page_id');

        // Check each language in order of configuration
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale() && isset($pagesSelectionByLanguage[$language])) {
                foreach ($pagesSelectionByLanguage[$language] as $pageId => $page) {
                    if (!in_array($pageId, $pagesSelectionIds)) {
                        if (!empty($page['nav_title'])) {
                            $pagesSelection[] = $page;
                            $pagesSelectionIds[] = $pageId;
                        }
                    }
                }
            }
        }

        $pages = [];
        foreach ($pagesSelection as $page) {
            if ($page['nav_title'] != '') {
                $page['nav_title'] = $converter->convert($page['nav_title']);
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Get all broadcasts in the current user language, sorted by priority and id
     */
    public function getBroadcasts($global = false)
    {
        $session = $this->getSession();
        $closedBroadcasts = $session->closedBrodcasts;
        if (!$closedBroadcasts || !is_array($closedBroadcasts)) {
            $closedBroadcasts = [];
        }

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        $visibility = 'visibility';
        if ($global) {
            $visibility = 'visibility_global';
        }

        // Retrieve all broadcasts from the database in the current language
        $broadcastsSelection = $this->broadcastsService->getBroadcastsList([$visibility => true, 'language' => $this->getTranslatorLocale()], 'priority ASC, id ASC');

        // Retrieve broadcasts in all configured languages as fallback
        $broadcastsSelectionByLanguage = [];
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale()) {
                $broadcastsInLanguage = $this->broadcastsService->getBroadcastsList([$visibility => true, 'language' => $language], 'priority ASC, id ASC');
                $broadcastsSelectionByLanguage[$language] = array_column($broadcastsInLanguage, null, 'broadcast_id');
            }
        }

        // If the content in the selected language is empty, check other languages in order of configuration
        foreach ($broadcastsSelection as &$broadcastSelection) {
            if (empty($broadcastSelection['content'])) {
                foreach ($this->config['Notifications']['languages'] as $language) {
                    if ($language !== $this->getTranslatorLocale() &&
                        isset($broadcastsSelectionByLanguage[$language][$broadcastSelection['broadcast_id']])) {

                        $fallbackBroadcast = $broadcastsSelectionByLanguage[$language][$broadcastSelection['broadcast_id']];
                        if (!empty($fallbackBroadcast['content'])) {
                            $broadcastSelection['content'] = $fallbackBroadcast['content'];
                            break;
                        }
                    }
                }
            }
        }

        // Check if broadcasts are missing, and include versions from other languages if that is the case
        $broadcastsSelectionIds = array_column($broadcastsSelection, 'broadcast_id');

        // Check each language in order of configuration
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale() && isset($broadcastsSelectionByLanguage[$language])) {
                foreach ($broadcastsSelectionByLanguage[$language] as $broadcastId => $broadcast) {
                    if (!in_array($broadcastId, $broadcastsSelectionIds)) {
                        if (!empty($broadcast['content'])) {
                            $broadcastsSelection[] = $broadcast;
                            $broadcastsSelectionIds[] = $broadcastId;
                        }
                    }
                }
            }
        }

        $broadcasts = [];
        foreach ($broadcastsSelection as $broadcast) {
            if ($broadcast['content'] != '' && !in_array($broadcast['broadcast_id'], $closedBroadcasts)) {
                $broadcast['content'] = $converter->convert($broadcast['content']);

                $broadcast['background_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['background_color'];
                $broadcast['border_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['border_color'];

                $broadcasts[$broadcast['broadcast_id']] = $broadcast;
            }
        }

        return $broadcasts;
    }

    public function isBroadcastActive($broadcast)
    {
        $today = new \DateTime();
        $startDate = new \DateTime($broadcast['startdate']);
        $endDate = new \DateTime($broadcast['enddate']);
        $endDate->setTime(23, 59, 59);
        return $startDate <= $today && $endDate >= $today;
    }

    /**
     * Get the session container (constructing it on demand if not already present)
     *
     * @return SessionContainer
     */
    protected function getSession()
    {
        // SessionContainer not defined yet? Build it now:
        if (null === $this->session) {
            $this->session = new \Laminas\Session\Container(
                'Notifications',
                $this->sessionManager
            );
        }
        return $this->session;
    }
}
