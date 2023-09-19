<?php
    /**
     * Notifications Controller
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
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License
     * along with this program; if not, write to the Free Software
     * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
     *
     * @category VuFind
     * @package  Controller
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Page
     */
    namespace VuFind\Controller;

    use Laminas\ServiceManager\ServiceLocatorInterface;
    use Laminas\Stdlib\ArrayObject;
    use League\CommonMark\Environment\Environment;
    use League\CommonMark\Extension\Autolink\AutolinkExtension;
    use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
    use League\CommonMark\MarkdownConverter;
    use ElGigi\CommonMarkEmoji\EmojiExtension;
    use VuFind\Form\BroadcastsForm;
    use VuFind\Form\PagesForm;


    /**
     * Controls the configuration and display of notifications
     *
     * @category VuFind
     * @package  Controller
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Page
     */
    class NotificationsController extends \VuFind\Controller\AbstractBase
    {
        /**
         * Notifications config
         *
         * @var array
         */
        private $config;

        /**
         * Constructor
         *
         * @param ServiceLocatorInterface $sm Service locator
         *
         * @throws \Psr\Container\ContainerExceptionInterface
         * @throws \Psr\Container\NotFoundExceptionInterface
         */
        public function __construct(ServiceLocatorInterface $sm)
        {
            parent::__construct($sm);
            $this->config = $sm->get(\VuFind\Config\YamlReader::class)->get('Notifications.yaml');
        }

        /**
         * Display a list of the exiting pages
         *
         * @return mixed
         */
        public function pagesAction () {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
            }
            if (!$this->getAuthManager()->isLoggedIn()) {
                return $this->redirect()->toRoute('myresearch-home');
            }

            $pagesTable = $this->getTable('notifications_pages');

            $view = $this->createViewModel();

            $environment = new Environment([]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new EmojiExtension());
            $converter = new MarkdownConverter($environment);

            $pagesList = [];
            foreach ($pagesTable->getPagesList(['language' => 'de'], 'priority ASC, id ASC') as $page) {
                if ($page['headline'] != '') {
                    $page['headline'] = $converter->convert($page['headline']);
                }
                if ($page['nav_title'] != '') {
                    $page['nav_title'] = $converter->convert($page['nav_title']);
                }
                $pagesList[] = $page;
            }

            $view->pagesList = $pagesList;

            return $view;
        }

        /**
         * Display a list of the exiting broadcasts
         *
         * @return mixed
         */
        public function broadcastsAction () {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
            }
            if (!$this->getAuthManager()->isLoggedIn()) {
                return $this->redirect()->toRoute('myresearch-home');
            }

            $broadcastsTable = $this->getTable('notifications_broadcasts');

            $view = $this->createViewModel();

            $environment = new Environment([]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new EmojiExtension());
            $converter = new MarkdownConverter($environment);

            $broadcastsList = [];
            foreach ($broadcastsTable->getBroadcastsList(['language' => $this->getTranslatorLocale()],  'priority ASC, id ASC', false) as $broadcast) {
                if ($broadcast['content'] != '') {
                    $broadcast['content'] = $converter->convert($broadcast['content']);
                }

                $broadcast['color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['color'];
                $broadcast['border_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['border_color'];

                $broadcastsList[] = $broadcast;
            }

            $view->broadcastsList = $broadcastsList;

            return $view;
        }

        /**
         * Edit an exiting or a new page
         *
         * @return mixed
         */
        public function editPageAction () {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'EditPage']);
            }
            $user = $this->getAuthManager()->isLoggedIn();
            if (!$user) {
                return $this->redirect()->toRoute('myresearch-home');
            }
            if ($this->getRequest()->getPost('cancel')) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
            }

            $pagesTable = $this->getTable('notifications_pages');
            $formElementManager = $this->serviceLocator->get('FormElementManager');
            $pagesForm = $formElementManager->get(PagesForm::class);

            $page_id = $this->params()->fromPost('id', $this->params()->fromQuery('page_id', []));
            if (!$page_id) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
            }

            $page = [];

            $view = $this->createViewModel();
            $view->languages = $this->config['Notifications']['languages'];

            if ($page_id != 'NEW') {
                $page = $pagesTable->getPagesDataByPageId($page_id);
                $pagesForm->setAttribute(
                    'action',
                    $this->url()->fromRoute('admin/notifications-pages', ['action' => 'EditPage','page_id' => $page_id])
                );
                $pagesForm->bind(new ArrayObject($page));
                $view->page_id = $page_id;
            } else {
                $pagesForm->setAttribute(
                    'action',
                    $this->url()->fromRoute('admin/notifications-pages', ['action' => 'EditPage','page_id' => 'NEW'])
                );
                $view->addNew = true;
            }

            $view->form = $pagesForm;

            if (!$this->getRequest()->isPost()) {
                return $view;
            }

            $pagesForm->setData($this->getRequest()->getPost());
            if (!$pagesForm->isValid()) {
                return $view;
            }

            $data = $pagesForm->getData();
            if (!isset($data['author_id']) || $data['author_id'] == '') {
                $data['author_id'] = $user->id;
            }

            $pagesTable->insertOrUpdatePage($data, $page, $page_id);

            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }

        /**
         * Edit an exiting or a new broadcast
         *
         * @return mixed
         */
        public function editBroadcastAction () {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'EditBroadcast']);
            }
            $user = $this->getAuthManager()->isLoggedIn();
            if (!$user) {
                return $this->redirect()->toRoute('myresearch-home');
            }
            if ($this->getRequest()->getPost('cancel')) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
            }

            $broadcastsTable = $this->getTable('notifications_broadcasts');
            $formElementManager = $this->serviceLocator->get('FormElementManager');
            $broadcastsForm = $formElementManager->get(BroadcastsForm::class);

            $broadcast_id = $this->params()->fromPost('broadcast_id', $this->params()->fromQuery('broadcast_id', []));
            if (!$broadcast_id) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
            }

            $broadcast = [];

            $view = $this->createViewModel();
            $view->languages = $this->config['Notifications']['languages'];

            if ($broadcast_id != 'NEW') {
                $broadcast = $broadcastsTable->getBroadcastsDataByBroadcastId($broadcast_id);
                $broadcastsForm->setAttribute(
                    'action',
                    $this->url()->fromRoute('admin/notifications-broadcasts', ['action' => 'EditBroadcast','broadcast_id' => $broadcast_id])
                );
                $broadcastsForm->bind(new ArrayObject($broadcast));
                $view->broadcast_id = $broadcast_id;
            } else {
                $broadcastsForm->setAttribute(
                    'action',
                    $this->url()->fromRoute('admin/notifications-broadcasts', ['action' => 'EditBroadcast','broadcast_id' => 'NEW'])
                );
                $broadcastsForm->get('color')->setValue('0');
                $broadcastsForm->get('startdate')->setValue(date('Y-m-d'));
                $broadcastsForm->get('enddate')->setValue(date('Y-m-d', strtotime("+1 day")));
                $view->addNew = true;
            }

            $view->form = $broadcastsForm;

            if (!$this->getRequest()->isPost()) {
                return $view;
            }

            $broadcastsForm->setData($this->getRequest()->getPost());
            if (!$broadcastsForm->isValid()) {
                return $view;
            }

            $data = $broadcastsForm->getData();
            if (!isset($data['author_id']) || $data['author_id'] == '') {
                $data['author_id'] = $user->id;
            }

            $broadcastsTable->insertOrUpdateBroadcast($data, $broadcast, $broadcast_id);

            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        /**
         * Delete an exiting page
         *
         * @return mixed
         */
        public function deletePageAction()
        {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'DeletePage']);
            }
            if (!$this->getAuthManager()->isLoggedIn()) {
                return $this->redirect()->toRoute('myresearch-home');
            }

            $page_id = $this->params()->fromPost('page_id', $this->params()->fromQuery('page_id', []));
            if (!$page_id) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
            }

            $pagesTable = $this->getTable('notifications_pages');
            $page = $pagesTable->getPageByPageIdAndLanguage($page_id, $this->getTranslatorLocale());

            $view = $this->createViewModel();
            $view->page = $page;

            if (!$this->getRequest()->isPost()) {
                return $view;
            }

            $a = $this->getRequest()->getPost('page_id');
            $b = $this->translator->translate('Delete');
            $c = $this->getRequest()->getPost('confirm', 'no');

            if ($page_id != $this->getRequest()->getPost('page_id')
                || $this->translator->translate('Delete') !== $this->getRequest()->getPost('confirm', 'no')
            ) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
            }

            $page->delete();

            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }

        /**
         * Delete an exiting broadcast
         *
         * @return mixed
         */
        public function deleteBroadcastAction()
        {
            if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
                return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'DeleteBroadcast']);
            }
            if (!$this->getAuthManager()->isLoggedIn()) {
                return $this->redirect()->toRoute('myresearch-home');
            }

            $broadcast_id = $this->params()->fromPost('broadcast_id', $this->params()->fromQuery('broadcast_id', []));
            if (!$broadcast_id) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
            }

            $broadcastsTable = $this->getTable('notifications_broadcasts');
            $broadcast = $broadcastsTable->getBroadcastByBroadcastIdAndLanguage($broadcast_id, $this->getTranslatorLocale());

            $environment = new Environment([]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new EmojiExtension());
            $converter = new MarkdownConverter($environment);

            if ($broadcast['content'] != '') {
                $broadcast['content'] = $converter->convert($broadcast['content']);
            }

            $broadcast['color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['color'];
            $broadcast['border_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['border_color'];

            $view = $this->createViewModel();
            $view->broadcast = $broadcast;

            if (!$this->getRequest()->isPost()) {
                return $view;
            }

            if ($broadcast_id != $this->getRequest()->getPost('broadcast_id')
                || $this->translator->translate('Delete') !== $this->getRequest()->getPost('confirm', 'no')
            ) {
                return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
            }

            foreach ($broadcast = $broadcastsTable->getBroadcastsByBroadcastId($broadcast_id) as $broadcast) {
                $broadcast->delete();
            }

            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        /**
         * Display a single page
         *
         * @return mixed
         */
        public function pageAction()
        {
            $page_id = $this->params()->fromPost('id', $this->params()->fromQuery('page_id', []));
            if (!$page_id) {
                return $this->redirect()->toRoute('search-home');
            }

            $pagesTable = $this->getTable('notifications_pages');
            $page = $pagesTable->getPageByPageIdAndLanguage($page_id, $this->getTranslatorLocale());

            if ($page) {
                $environment = new Environment([]);
                $environment->addExtension(new CommonMarkCoreExtension());
                $environment->addExtension(new AutolinkExtension());
                $environment->addExtension(new EmojiExtension());
                $converter = new MarkdownConverter($environment);

                if ($page['content'] != '') {
                    $page['content'] = $converter->convert($page['content']);
                }

                $view = $this->createViewModel();
            }
            $view->page = $page;

            return $view;
        }
    }
