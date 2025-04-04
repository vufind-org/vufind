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
    public function __construct(\VuFind\Db\Table\PluginManager $database, $config)
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
        $pagesTable = $this->database->get('notifications_pages');

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        // Retrieve all broadcasts from the database in both the selected and default languages
        $pagesSelection = $pagesTable->getPagesList(['visibility' => true, 'language' => $this->getTranslatorLocale()], 'priority ASC, page_id ASC');
        $pagesSelectionDefaultLanguage = $pagesTable->getPagesList(['visibility' => true, 'language' => $this->defaultLanguage], 'priority ASC, page_id ASC');
        $lookupPagesSelectionDefaultLanguage = array_column($pagesSelectionDefaultLanguage, null, 'page_id');

        // If the content in the selected language is empty, use the content from the default language instead
        foreach ($pagesSelection as &$pageSelection) {
            if (empty($pageSelection['content']) && isset($lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']])) {
                $pageSelection['content'] = $lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']]['content'];
            }
            if (empty($pageSelection['headline']) && isset($lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']])) {
                $pageSelection['headline'] = $lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']]['content'];
            }
            if (empty($pageSelection['nav_title']) && isset($lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']])) {
                $pageSelection['nav_title'] = $lookupPagesSelectionDefaultLanguage[$pageSelection['page_id']]['content'];
            }
        }

        // Check if pages are missing, and include the default language version if that is the case
        $pagesSelectionIds = array_column($pagesSelection, 'page_id');
        foreach ($pagesSelectionDefaultLanguage as $pageSelectionDefaultLanguage) {
            if (!in_array($pageSelectionDefaultLanguage['page_id'], $pagesSelectionIds)) {
                $pagesSelection[] = $pageSelectionDefaultLanguage;
            }
        }

        $pages = [];
        foreach ($pagesSelection as $page) {
            if ($page['headline'] != '') {
                $page['headline'] = $converter->convert($page['headline']);
            }
            if ($page['nav_title'] != '') {
                $page['nav_title'] = $converter->convert($page['nav_title']);
            }
            $pages[] = $page;
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

        $broadcastsTable = $this->database->get('notifications_broadcasts');

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        $visibility = 'visibility';
        if ($global) {
            $visibility = 'visibility_global';
        }

        // Retrieve all broadcasts from the database in both the selected and default languages
        $broadcastsSelection = $broadcastsTable->getBroadcastsList([$visibility => true, 'language' => $this->getTranslatorLocale()], 'priority ASC, id ASC');
        $broadcastsSelectionDefaultLanguage = $broadcastsTable->getBroadcastsList([$visibility => true, 'language' => $this->defaultLanguage], 'priority ASC, id ASC');
        $lookupBroadcastsSelectionDefaultLanguage = array_column($broadcastsSelectionDefaultLanguage, null, 'broadcast_id');

        // If the content in the selected language is empty, use the content from the default language instead
        foreach ($broadcastsSelection as &$broadcastSelection) {
            if (empty($broadcastSelection['content']) && isset($lookupBroadcastsSelectionDefaultLanguage[$broadcastSelection['broadcast_id']])) {
                $broadcastSelection['content'] = $lookupBroadcastsSelectionDefaultLanguage[$broadcastSelection['broadcast_id']]['content'];
            }
        }

        // Check if broadcasts are missing, and include the default language version if that is the case
        $broadcastsSelectionIds = array_column($broadcastsSelection, 'broadcast_id');
        foreach ($broadcastsSelectionDefaultLanguage as $broadcastSelectionDefaultLanguage) {
            if (!in_array($broadcastSelectionDefaultLanguage['broadcast_id'], $broadcastsSelectionIds)) {
                $broadcastsSelection[] = $broadcastSelectionDefaultLanguage;
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
