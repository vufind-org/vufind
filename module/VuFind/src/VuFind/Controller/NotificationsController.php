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

use ElGigi\CommonMarkEmoji\EmojiExtension;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayObject;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use VuFind\Db\Service\NotificationsBroadcastsServiceInterface;
use VuFind\Db\Service\NotificationsPagesServiceInterface;
use VuFind\Form\NotificationsBroadcastsForm;
use VuFind\Form\NotificationsPagesForm;

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
    protected $config;

    /**
     * Default language
     *
     * @var mixed
     */
    private $defaultLanguage;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(ServiceLocatorInterface $sm,
                                protected NotificationsBroadcastsServiceInterface $broadcastsService,
                                protected NotificationsPagesServiceInterface $pagesService)
    {
        parent::__construct($sm);
        $this->config = $sm->get(\VuFind\Config\YamlReader::class)->get('Notifications.yaml');
        if (!empty($this->config['Notifications']['languages'])) {
            $this->defaultLanguage = $this->config['Notifications']['languages'][0];
        }
    }

    /**
     * Display a list of the exiting pages
     *
     * @return mixed
     */
    public function pagesAction()
    {
        if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }

        $view = $this->createViewModel();

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        // Retrieve all pages from the database in the current language
        $pagesSelection = $this->pagesService->getPagesList(['language' => $this->getTranslatorLocale()], 'priority ASC, id ASC');

        // Retrieve pages in all configured languages as fallback
        $pagesSelectionByLanguage = [];
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale()) {
                $pagesInLanguage = $this->pagesService->getPagesList(['language' => $language], 'priority ASC, id ASC');
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

        $pagesList = [];
        foreach ($pagesSelection as $page) {
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
    public function broadcastsAction()
    {
        if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }

        $view = $this->createViewModel();

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        // Retrieve all broadcasts from the database in the current language
        $broadcastsSelection = $this->broadcastsService->getBroadcastsList(['language' => $this->getTranslatorLocale()], 'priority ASC, id ASC', false);

        // Retrieve broadcasts in all configured languages as fallback
        $broadcastsSelectionByLanguage = [];
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($language !== $this->getTranslatorLocale()) {
                $broadcastsInLanguage = $this->broadcastsService->getBroadcastsList(['language' => $language], 'priority ASC, id ASC', false);
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

        $broadcastsList = [];
        foreach ($broadcastsSelection as $broadcast) {
            if ($broadcast['content'] != '') {
                $broadcast['content'] = $converter->convert($broadcast['content']);
            }

            $broadcast['background_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['background_color'];
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
    public function editPageAction()
    {
        if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'EditPage']);
        }
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        if ($this->getRequest()->getPost('cancel')) {
            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }

        $formElementManager = $this->serviceLocator->get('FormElementManager');
        $pagesForm = $formElementManager->get(NotificationsPagesForm::class);

        $page_id = $this->params()->fromPost('id', $this->params()->fromQuery('page_id', []));
        if (!$page_id) {
            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }

        $page = [];

        $view = $this->createViewModel();
        $view->languages = $this->config['Notifications']['languages'];

        if ($page_id != 'NEW') {
            $page = $this->pagesService->getPagesDataByPageId($page_id);
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
            $data['author_id'] = $this->getUser()->id;
        }

        $this->pagesService->insertOrUpdatePage($data, $page, $page_id);

        return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
    }

    /**
     * Edit an exiting or a new broadcast
     *
     * @return mixed
     */
    public function editBroadcastAction()
    {
        if (!stristr($this->getRequest()->getRequestUri(), '/Admin')) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'EditBroadcast']);
        }
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        if ($this->getRequest()->getPost('cancel')) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        $formElementManager = $this->serviceLocator->get('FormElementManager');
        $broadcastsForm = $formElementManager->get(NotificationsBroadcastsForm::class);

        $broadcast_id = $this->params()->fromPost('broadcast_id', $this->params()->fromQuery('broadcast_id', []));
        if (!$broadcast_id) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        $broadcast = [];

        $view = $this->createViewModel();
        $view->languages = $this->config['Notifications']['languages'];

        if ($broadcast_id != 'NEW') {
            $broadcast = $this->broadcastsService->getBroadcastsDataByBroadcastId($broadcast_id);
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
            $broadcastsForm->get('enddate')->setValue(date('Y-m-d', strtotime('+1 day')));
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
            $data['author_id'] = $this->getUser()->id;
        }

        $this->broadcastsService->insertOrUpdateBroadcast($data, $broadcast, $broadcast_id);

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
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }

        $page_id = $this->params()->fromPost('page_id', $this->params()->fromQuery('page_id', []));
        if (!$page_id) {
            return $this->redirect()->toRoute('admin/notifications-pages', ['action' => 'Pages']);
        }

        $page = $this->pagesService->getPageByPageIdAndLanguage($page_id, $this->getTranslatorLocale());

        $view = $this->createViewModel();
        $view->page = $page;

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $a = $this->getRequest()->getPost('page_id');
        $b = $this->translator->translate('Delete');
        $c = $this->getRequest()->getPost('confirm', 'no');

        if (
            $page_id != $this->getRequest()->getPost('page_id')
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
        if (!$this->getAuthManager()->isLoggedIn() || !$this->permission()->isAuthorized('notifications.Admin')) {
            return $this->redirect()->toRoute('myresearch-home');
        }

        $broadcast_id = $this->params()->fromPost('broadcast_id', $this->params()->fromQuery('broadcast_id', []));
        if (!$broadcast_id) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        $broadcast = $this->broadcastsService->getBroadcastByBroadcastIdAndLanguage($broadcast_id, $this->getTranslatorLocale());

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new EmojiExtension());
        $converter = new MarkdownConverter($environment);

        if ($broadcast['content'] != '') {
            $broadcast['content'] = $converter->convert($broadcast['content']);
        }

        $broadcast['background_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['background_color'];
        $broadcast['border_color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['border_color'];
        $broadcast['color_value'] = $this->config['Notifications']['broadcast_types'][$broadcast['color']]['color'];

        $view = $this->createViewModel();
        $view->broadcast = $broadcast;

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        if (
            $broadcast_id != $this->getRequest()->getPost('broadcast_id')
            || $this->translator->translate('Delete') !== $this->getRequest()->getPost('confirm', 'no')
        ) {
            return $this->redirect()->toRoute('admin/notifications-broadcasts', ['action' => 'Broadcasts']);
        }

        foreach ($broadcast = $this->broadcastsService->getBroadcastsByBroadcastId($broadcast_id) as $broadcast) {
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

        // Get the page in the current language
        $page = $this->pagesService->getPageByPageIdAndLanguage($page_id, $this->getTranslatorLocale());

        // If content is empty, check other languages in order of configuration
        if (empty($page['content']) || $page['content'] == '') {
            foreach ($this->config['Notifications']['languages'] as $language) {
                if ($language !== $this->getTranslatorLocale()) {
                    $fallbackPage = $this->pagesService->getPageByPageIdAndLanguage($page_id, $language);
                    if (!empty($fallbackPage['content'])) {
                        $page = $fallbackPage;
                        break;
                    }
                }
            }
        }

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
