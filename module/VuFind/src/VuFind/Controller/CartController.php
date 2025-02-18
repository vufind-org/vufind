<?php

/**
 * Book Bag / Bulk Action Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container;
use VuFind\Controller\Feature\ListItemSelectionTrait;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Favorites\FavoritesService;

use function count;
use function is_array;
use function strlen;

/**
 * Book Bag / Bulk Action Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CartController extends AbstractBase
{
    use Feature\BulkActionControllerTrait;
    use ListItemSelectionTrait;

    /**
     * Session container
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

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
     * @param ServiceLocatorInterface      $sm           Service manager
     * @param Container                    $container    Session container
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param \VuFind\Export               $export       Export support class
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        Container $container,
        \VuFind\Config\PluginManager $configLoader,
        \VuFind\Export $export
    ) {
        parent::__construct($sm);
        $this->session = $container;
        $this->configLoader = $configLoader;
        $this->export = $export;
    }

    /**
     * Get the cart object.
     *
     * @return \VuFind\Cart
     */
    protected function getCart()
    {
        return $this->getService(\VuFind\Cart::class);
    }

    /**
     * Figure out an action from the request....
     *
     * @param string $default Default action if none can be determined.
     *
     * @return string
     */
    protected function getCartActionFromRequest($default = 'Home')
    {
        if (strlen($this->params()->fromPost('email', '')) > 0) {
            return 'Email';
        } elseif (strlen($this->params()->fromPost('print', '')) > 0) {
            return 'PrintCart';
        } elseif (strlen($this->params()->fromPost('saveCart', '')) > 0) {
            return 'Save';
        } elseif (strlen($this->params()->fromPost('export', '')) > 0) {
            return 'Export';
        }
        // Check if the user is in the midst of a login process; if not,
        // use the provided default.
        return $this->followup()->retrieveAndClear('cartAction', $default);
    }

    /**
     * Process requests for bulk actions from search results.
     *
     * @return mixed
     */
    public function searchresultsbulkAction()
    {
        // We came in from a search, so let's remember that context so we can
        // return to it later. However, if we came in from a previous instance
        // of this action (for example, because of a login screen), or if we
        // have an external site in the referer, we should ignore that!
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
        $bulk = $this->url()->fromRoute('cart-searchresultsbulk');
        if (!empty($referer) && $this->isLocalUrl($referer) && !str_ends_with($referer, $bulk)) {
            $this->session->url = $referer;
        }

        // Now forward to the requested action:
        return $this->forwardTo('Cart', $this->getCartActionFromRequest());
    }

    /**
     * Process requests for main cart.
     *
     * @return mixed
     */
    public function processorAction()
    {
        // We came in from the cart -- let's remember this so we can redirect there
        // when we're done:
        $this->session->url = $this->url()->fromRoute('cart-home');

        // Now forward to the requested action:
        return $this->forwardTo('Cart', $this->getCartActionFromRequest());
    }

    /**
     * Display cart contents.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Bail out if cart is disabled.
        if (!$this->getCart()->isActive()) {
            return $this->redirect()->toRoute('home');
        }

        // If a user is coming directly to the cart, we should clear out any
        // existing context information to prevent weird, unexpected workflows
        // caused by unusual user behavior.
        $this->followup()->retrieveAndClear('cartAction');
        $this->followup()->retrieveAndClear('cartIds');

        $ids = $this->getSelectedIds();

        // Add items if necessary:
        if (strlen($this->params()->fromPost('empty', '')) > 0) {
            $this->getCart()->emptyCart();
        } elseif (strlen($this->params()->fromPost('delete', '')) > 0) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $this->getCart()->removeItems($ids);
            }
        } elseif (strlen($this->params()->fromPost('add', '')) > 0) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $addItems = $this->getCart()->addItems($ids);
                if (!$addItems['success']) {
                    $msg = $this->translate('bookbag_full_msg') . '. '
                        . $addItems['notAdded'] . ' '
                        . $this->translate('items_already_in_bookbag') . '.';
                    $this->flashMessenger()->addMessage($msg, 'info');
                }
            }
        }
        // Using the cart/cart template for the cart/home action is a legacy of
        // an earlier controller design; we may want to rename the template for
        // clarity, but right now we are retaining the old template name for
        // backward compatibility.
        $view = $this->createViewModel();
        $view->setTemplate('cart/cart');
        return $view;
    }

    /**
     * Process bulk actions from the MyResearch area; most of this is only necessary
     * when Javascript is disabled.
     *
     * @return mixed
     */
    public function myresearchbulkAction()
    {
        // We came in from the MyResearch section -- let's remember which list (if
        // any) we came from so we can redirect there when we're done:
        $listID = $this->params()->fromPost('listID');
        $this->session->url = empty($listID)
            ? $this->url()->fromRoute('myresearch-favorites')
            : $this->url()->fromRoute('userList', ['id' => $listID]);

        // Now forward to the requested controller/action:
        $controller = 'Cart';   // assume Cart unless overridden below.
        if (strlen($this->params()->fromPost('email', '')) > 0) {
            $action = 'Email';
        } elseif (strlen($this->params()->fromPost('print', '')) > 0) {
            $action = 'PrintCart';
        } elseif (strlen($this->params()->fromPost('delete', '')) > 0) {
            $controller = 'MyResearch';
            $action = 'Delete';
        } elseif (strlen($this->params()->fromPost('add', '')) > 0) {
            $action = 'Home';
        } elseif (strlen($this->params()->fromPost('export', '')) > 0) {
            $action = 'Export';
        } else {
            $action = $this->followup()->retrieveAndClear('cartAction', null);
            if (empty($action)) {
                throw new \Exception('Unrecognized bulk action.');
            }
        }
        return $this->forwardTo($controller, $action);
    }

    /**
     * Email a batch of records.
     *
     * @return mixed
     */
    public function emailAction()
    {
        // Retrieve ID list:
        $ids = $this->getSelectedIds();

        // Retrieve follow-up information if necessary:
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds') ?? [];
        }
        $actionLimit = $this->getBulkActionLimit('email');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $this->redirectToSource('error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $this->redirectToSource('error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        $emailActionSettings = $this->getService(\VuFind\Config\AccountCapabilities::class)->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->getUser()
        ) {
            return $this->forceLogin(
                null,
                ['cartIds' => $ids, 'cartAction' => 'Email']
            );
        }

        $view = $this->createEmailViewModel(
            null,
            $this->translate('bulk_email_title')
        );
        $view->records = $this->getRecordLoader()->loadBatch($ids);
        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('email');

        // Process form submission:
        if (!($submitDisabled ?? false) && $this->formWasSubmitted(useCaptcha: $view->useCaptcha)) {
            // Build the URL to share:
            $params = [];
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = $this->getService(\VuFind\Mailer\Mailer::class);
                $mailer->setMaxRecipients($view->maxRecipients);
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendLink(
                    $view->to,
                    $view->from,
                    $view->message,
                    $url,
                    $this->getViewRenderer(),
                    $view->subject,
                    $cc
                );
                return $this->redirectToSource('success', 'bulk_email_success', true);
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getDisplayMessage(), 'error');
            }
        }

        return $view;
    }

    /**
     * Print a batch of records.
     *
     * @return mixed
     */
    public function printcartAction()
    {
        $ids = $this->getSelectedIds();
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Check if id limit is exceeded
        $actionLimit = $this->getBulkActionLimit('print');
        if (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            return $this->redirectToSource('error', $errorMsg);
        }

        $callback = function ($i) {
            return 'id[]=' . urlencode($i);
        };
        $query = '?print=true&' . implode('&', array_map($callback, $ids));
        $url = $this->url()->fromRoute('records-home') . $query;
        return $this->redirect()->toUrl($url);
    }

    /**
     * Set up export of a batch of records.
     *
     * @return mixed
     */
    public function exportAction()
    {
        // Get the desired ID list:
        $ids = $this->getSelectedIds();

        // Get export tools:
        $export = $this->export;

        // Get id limit
        $format = $this->params()->fromPost('format');
        $actionLimit = $format ? $this->getExportActionLimit($format) : $this->getBulkActionLimit('export');

        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $this->redirectToSource('error', 'bulk_noitems_advice')) {
                return $redirect;
            }
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $this->redirectToSource('error', $errorMsg)) {
                return $redirect;
            }
        } elseif ($this->formWasSubmitted()) {
            $url = $export->getBulkUrl($this->getViewRenderer(), $format, $ids);
            if ($export->needsRedirect($format)) {
                return $this->redirect()->toUrl($url);
            }
            $exportType = $export->getBulkExportType($format);
            $params = [
                'exportType' => $exportType,
                'format' => $format,
            ];
            if ('post' === $exportType) {
                $records = $this->getRecordLoader()->loadBatch($ids);
                $recordHelper = $this->getViewRenderer()->plugin('record');
                $parts = [];
                foreach ($records as $record) {
                    $parts[] = $recordHelper($record)->getExport($format);
                }

                $params['postField'] = $export->getPostField($format);
                $params['postData'] = $export->processGroup($format, $parts);
                $params['targetWindow'] = $export->getTargetWindow($format);
                $params['url'] = $export->getRedirectUrl($format, '');
            } else {
                $params['url'] = $url;
            }
            $msg = [
                'translate' => false, 'html' => true,
                'msg' => $this->getViewRenderer()->render(
                    'cart/export-success.phtml',
                    $params
                ),
            ];
            return $this->redirectToSource('success', $msg, true);
        }

        // Load the records:
        $view = $this->createViewModel();
        $view->records = $this->getRecordLoader()->loadBatch($ids);

        // Assign the list of legal export options. We'll filter them down based
        // on what the selected records actually support.
        $view->exportOptions = $export->getFormatsForRecords($view->records);

        // No legal export options?  Display a warning:
        if (empty($view->exportOptions)) {
            $this->flashMessenger()
                ->addMessage('bulk_export_not_supported', 'error');
        }
        return $view;
    }

    /**
     * Actually perform the export operation.
     *
     * @return mixed
     */
    public function doexportAction()
    {
        // We use abbreviated parameters here to keep the URL short (there may
        // be a long list of IDs, and we don't want to run out of room):
        $ids = $this->params()->fromQuery('i', []);
        $format = $this->params()->fromQuery('f');

        // Make sure we have IDs to export:
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Check if id limit is exceeded
        $actionLimit = $this->getExportActionLimit($format);
        if (count($ids) > $actionLimit) {
            return $this->redirectToSource('error', 'bulk_limit_exceeded');
        }

        // Send appropriate HTTP headers for requested format:
        $response = $this->getResponse();
        $response->getHeaders()->addHeaders($this->export->getHeaders($format));

        // Actually export the records
        $records = $this->getRecordLoader()->loadBatch($ids);
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $parts = [];
        foreach ($records as $record) {
            $parts[] = $recordHelper($record)->getExport($format);
        }

        // Process and display the exported records
        $response->setContent($this->export->processGroup($format, $parts));
        return $response;
    }

    /**
     * Save a batch of records.
     *
     * @return mixed
     */
    public function saveAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Load record information first (no need to prompt for login if we just
        // need to display a "no records" error message):
        $ids = $this->getSelectedIds();
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds') ?? [];
        }
        $actionLimit = $this->getBulkActionLimit('saveCart');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $this->redirectToSource('error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $this->redirectToSource('error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        // Make sure user is logged in:
        if (!($user = $this->getUser())) {
            return $this->forceLogin(
                null,
                ['cartIds' => $ids, 'cartAction' => 'Save']
            );
        }
        $viewModel = $this->createViewModel(
            [
                'records' => $this->getRecordLoader()->loadBatch($ids),
                'lists' => $this->getDbService(UserListServiceInterface::class)->getUserListsByUser($user),
            ]
        );
        if ($submitDisabled ?? false) {
            return $viewModel;
        }
        if ($this->formWasSubmitted('newList')) {
            // Remove submit now from parameters
            $this->getRequest()->getPost()->set('newList', null)->set('submitButton', null);
            return $this->forwardTo('MyResearch', 'editlist', ['id' => 'NEW']);
        }
        // Process submission if necessary:
        if ($this->formWasSubmitted()) {
            $results = $this->getService(FavoritesService::class)
                ->saveRecordsToFavorites($this->getRequest()->getPost()->toArray(), $user);
            $listUrl = $this->url()->fromRoute(
                'userList',
                ['id' => $results['listId']]
            );
            $message = [
                'html' => true,
                'msg' => $this->translate('bulk_save_success') . '. '
                . '<a href="' . $listUrl . '" class="gotolist">'
                . $this->translate('go_to_list') . '</a>.',
            ];
            $this->flashMessenger()->addMessage($message, 'success');
            return $this->redirect()->toUrl($listUrl);
        }

        // Pass record and list information to view:
        return $viewModel;
    }
}
