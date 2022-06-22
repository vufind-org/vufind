<?php
/**
 * Book Bag / Bulk Action Controller
 *
 * PHP version 7
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
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;

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
    /**
     * Session container
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm        Service manager
     * @param Container               $container Session container
     */
    public function __construct(ServiceLocatorInterface $sm, Container $container)
    {
        parent::__construct($sm);
        $this->session = $container;
    }

    /**
     * Get the cart object.
     *
     * @return \VuFind\Cart
     */
    protected function getCart()
    {
        return $this->serviceLocator->get(\VuFind\Cart::class);
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
        // of this action (for example, because of a login screen), we should
        // ignore that!
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
        $bulk = $this->url()->fromRoute('cart-searchresultsbulk');
        if (substr($referer, -strlen($bulk)) != $bulk) {
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

        $params = $this->params();
        $ids = null === $params->fromPost('selectAll')
            ? ($params->fromPost('ids') ?? $params->fromQuery('ids'))
            : $params->fromPost('idsAll');

        // Add items if necessary:
        if ($params->fromPost('empty')) {
            $this->getCart()->emptyCart();
        } elseif ($params->fromPost('delete') || $params->fromQuery('delete')) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $this->getCart()->removeItems($ids);
            }
        } elseif ($params->fromPost('add') || $params->fromQuery('add')) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $addItems = $this->getCart()->addItems($ids);
                if (!$addItems['success']) {
                    $msg = $this->translate('bookbag_full_msg') . ". "
                        . $addItems['notAdded'] . " "
                        . $this->translate('items_already_in_bookbag') . ".";
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
        $ids = null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');

        // Retrieve follow-up information if necessary:
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds');
        }
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Force login if necessary:
        $config = $this->getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
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
        if ($this->formWasSubmitted('submit', $view->useCaptcha)) {
            // Build the URL to share:
            $params = [];
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = $this->serviceLocator->get(\VuFind\Mailer\Mailer::class);
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
                return $this->redirectToSource('success', 'bulk_email_success');
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
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
        $ids = null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $callback = function ($i) {
            return 'id[]=' . urlencode($i);
        };
        $query = '?print=true&' . implode('&', array_map($callback, $ids));
        $url = $this->url()->fromRoute('records-home') . $query;
        return $this->redirect()->toUrl($url);
    }

    /**
     * Access export tools.
     *
     * @return \VuFind\Export
     */
    protected function getExport()
    {
        return $this->serviceLocator->get(\VuFind\Export::class);
    }

    /**
     * Set up export of a batch of records.
     *
     * @return mixed
     */
    public function exportAction()
    {
        // Get the desired ID list:
        $ids = null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Get export tools:
        $export = $this->getExport();

        // Process form submission if necessary:
        if ($this->formWasSubmitted('submit')) {
            $format = $this->params()->fromPost('format');
            $url = $export->getBulkUrl($this->getViewRenderer(), $format, $ids);
            if ($export->needsRedirect($format)) {
                return $this->redirect()->toUrl($url);
            }
            $exportType = $export->getBulkExportType($format);
            $params = [
                'exportType' => $exportType,
                'format' => $format
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
                )
            ];
            return $this->redirectToSource('success', $msg);
        }

        // Load the records:
        $view = $this->createViewModel();
        $view->records = $this->getRecordLoader()->loadBatch($ids);

        // Assign the list of legal export options.  We'll filter them down based
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

        // Send appropriate HTTP headers for requested format:
        $response = $this->getResponse();
        $response->getHeaders()->addHeaders($this->getExport()->getHeaders($format));

        // Actually export the records
        $records = $this->getRecordLoader()->loadBatch($ids);
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $parts = [];
        foreach ($records as $record) {
            $parts[] = $recordHelper($record)->getExport($format);
        }

        // Process and display the exported records
        $response->setContent($this->getExport()->processGroup($format, $parts));
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
        $ids = null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids', $this->params()->fromQuery('ids'))
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds');
        }
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Make sure user is logged in:
        if (!($user = $this->getUser())) {
            return $this->forceLogin(
                null,
                ['cartIds' => $ids, 'cartAction' => 'Save']
            );
        }

        // Process submission if necessary:
        if ($this->formWasSubmitted('submit')) {
            $results = $this->favorites()
                ->saveBulk($this->getRequest()->getPost()->toArray(), $user);
            $listUrl = $this->url()->fromRoute(
                'userList',
                ['id' => $results['listId']]
            );
            $message = [
                'html' => true,
                'msg' => $this->translate('bulk_save_success') . '. '
                . '<a href="' . $listUrl . '" class="gotolist">'
                . $this->translate('go_to_list') . '</a>.'
            ];
            $this->flashMessenger()->addMessage($message, 'success');
            return $this->redirect()->toUrl($listUrl);
        }

        // Pass record and list information to view:
        return $this->createViewModel(
            [
                'records' => $this->getRecordLoader()->loadBatch($ids),
                'lists' => $user->getLists()
            ]
        );
    }

    /**
     * Support method: redirect to the page we were on when the bulk action was
     * initiated.
     *
     * @param string $flashNamespace Namespace for flash message (null for none)
     * @param string $flashMsg       Flash message to set (ignored if namespace null)
     *
     * @return mixed
     */
    public function redirectToSource($flashNamespace = null, $flashMsg = null)
    {
        // Set flash message if requested:
        if (null !== $flashNamespace && !empty($flashMsg)) {
            $this->flashMessenger()->addMessage($flashMsg, $flashNamespace);
        }

        // If we entered the controller in the expected way (i.e. via the
        // myresearchbulk action), we should have a source set in the followup
        // memory.  If that's missing for some reason, just forward to MyResearch.
        if (isset($this->session->url)) {
            $target = $this->session->url;
            unset($this->session->url);
        } else {
            $target = $this->url()->fromRoute('myresearch-home');
        }
        return $this->redirect()->toUrl($target);
    }
}
