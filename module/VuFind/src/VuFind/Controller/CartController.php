<?php
/**
 * Book Bag / Bulk Action Controller
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use VuFind\Exception\Mail as MailException,
    Zend\Session\Container as SessionContainer;

/**
 * Book Bag / Bulk Action Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CartController extends AbstractBase
{
    /**
     * Session container
     *
     * @var SessionContainer
     */
    protected $session;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->session = new SessionContainer('cart_followup');
    }

    /**
     * Get the cart object.
     *
     * @return \VuFind\Cart
     */
    protected function getCart()
    {
        return $this->getServiceLocator()->get('VuFind\Cart');
    }

    /**
     * Process requests for main cart.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // We came in from the cart -- let's remember this we can redirect there
        // when we're done:
        $this->session->url = $this->getLightboxAwareUrl('cart-home');

        // Now forward to the requested action:
        if (strlen($this->params()->fromPost('email', '')) > 0) {
            $action = 'Email';
        } else if (strlen($this->params()->fromPost('print', '')) > 0) {
            $action = 'PrintCart';
        } else if (strlen($this->params()->fromPost('saveCart', '')) > 0) {
            $action = 'Save';
        } else if (strlen($this->params()->fromPost('export', '')) > 0) {
            $action = 'Export';
        } else {
            // Check if the user is in the midst of a login process; if not,
            // default to cart home.
            $action = $this->followup()->retrieveAndClear('cartAction', 'Cart');
        }
        return $this->forwardTo('Cart', $action);
    }

    /**
     * Display cart contents.
     *
     * @return mixed
     */
    public function cartAction()
    {
        // Bail out if cart is disabled.
        if (!$this->getCart()->isActive()) {
            return $this->redirect()->toRoute('home');
        }

        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');

        // Add items if necessary:
        if (strlen($this->params()->fromPost('empty', '')) > 0) {
            $this->getCart()->emptyCart();
        } else if (strlen($this->params()->fromPost('delete', '')) > 0) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $this->getCart()->removeItems($ids);
            }
        } else if (strlen($this->params()->fromPost('add', '')) > 0) {
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
        return $this->createViewModel();
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
        } else if (strlen($this->params()->fromPost('print', '')) > 0) {
            $action = 'PrintCart';
        } else if (strlen($this->params()->fromPost('delete', '')) > 0) {
            $controller = 'MyResearch';
            $action = 'Delete';
        } else if (strlen($this->params()->fromPost('add', '')) > 0) {
            $action = 'Cart';
        } else if (strlen($this->params()->fromPost('export', '')) > 0) {
            $action = 'Export';
        } else {
            throw new \Exception('Unrecognized bulk action.');
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
        $ids = is_null($this->params()->fromPost('selectAll'))
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
                null, ['cartIds' => $ids, 'cartAction' => 'Email']
            );
        }

        $view = $this->createEmailViewModel(
            null, $this->translate('bulk_email_title')
        );
        $view->records = $this->getRecordLoader()->loadBatch($ids);
        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('email');

        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            // Build the URL to share:
            $params = [];
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
                $mailer->setMaxRecipients($view->maxRecipients);
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendLink(
                    $view->to, $view->from, $view->message,
                    $url, $this->getViewRenderer(), $view->subject, $cc
                );
                return $this->redirectToSource('success', 'email_success');
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
        $ids = is_null($this->params()->fromPost('selectAll'))
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
        return $this->getServiceLocator()->get('VuFind\Export');
    }

    /**
     * Set up export of a batch of records.
     *
     * @return mixed
     */
    public function exportAction()
    {
        // Get the desired ID list:
        $ids = is_null($this->params()->fromPost('selectAll'))
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
            $msg = [
                'translate' => false, 'html' => true,
                'msg' => $this->getViewRenderer()->render(
                    'cart/export-success.phtml', ['url' => $url]
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
            throw new \Exception('Lists disabled');
        }

        // Load record information first (no need to prompt for login if we just
        // need to display a "no records" error message):
        $ids = is_null($this->params()->fromPost('selectAll'))
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
                null, ['cartIds' => $ids, 'cartAction' => 'Save']
            );
        }

        // Process submission if necessary:
        if ($this->formWasSubmitted('submit')) {
            $this->favorites()
                ->saveBulk($this->getRequest()->getPost()->toArray(), $user);
            $this->flashMessenger()->addMessage('bulk_save_success', 'success');
            $list = $this->params()->fromPost('list');
            if (!empty($list)) {
                return $this->redirect()->toRoute('userList', ['id' => $list]);
            } else {
                return $this->redirectToSource();
            }
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