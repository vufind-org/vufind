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

    use Laminas\View\Helper\AbstractHelper;
    use League\CommonMark\Environment\Environment;
    use League\CommonMark\Extension\Autolink\AutolinkExtension;
    use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
    use League\CommonMark\MarkdownConverter;
    use ElGigi\CommonMarkEmoji\EmojiExtension;
    use VuFind\I18n\Translator\TranslatorAwareInterface;

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
         * Constructor
         *
         * @param PluginManager $database Database
         * @param mixed $config Notifications config
         */
        public function __construct(\VuFind\Db\Table\PluginManager $database, $config)
        {
            $this->database = $database;
            $this->config = $config;
        }

        /**
         * Get all pages in the current user language, sorted by priority and id
         */
        public function getPages () {
            $pagesTable = $this->database->get('notifications_pages');

            $environment = new Environment([]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new EmojiExtension());
            $converter = new MarkdownConverter($environment);

            $pages = [];

            foreach ($pagesTable->getPagesList(['visibility' => true, 'language' => $this->getTranslatorLocale()], 'priority ASC, page_id ASC') as $page) {
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
        public function getBroadcasts ($global = false) {
            $broadcastsTable = $this->database->get('notifications_broadcasts');

            $environment = new Environment([]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new EmojiExtension());
            $converter = new MarkdownConverter($environment);

            $broadcasts = [];

            $visibility = 'visibility';
            if ($global) {
                $visibility = 'visibility_global';
            }

            foreach ($broadcastsTable->getBroadcastsList([$visibility => true, 'language' => $this->getTranslatorLocale()],  'priority ASC, id ASC') as $broadcast) {
                if ($broadcast['content'] != '') {
                    $broadcast['content'] = $converter->convert($broadcast['content']);

                    $broadcast['color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['color'];
                    $broadcast['border_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['border_color'];

                    $broadcasts[] = $broadcast;
                }
            }

            return $broadcasts;
        }

        public function isBroadcastActive ($broadcast) {
            $today = new \DateTime();
            $startDate = new \DateTime($broadcast['startdate']);
            $endDate = new \DateTime($broadcast['enddate']);
            $endDate->setTime(23, 59, 59);
            return $startDate <= $today && $endDate >= $today;
        }
    }
