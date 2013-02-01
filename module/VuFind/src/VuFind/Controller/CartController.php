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
        $this->session->url = $this->url()->fromRoute('cart-home');

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
            $action = 'Cart';
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
                    $this->flashMessenger()->setNamespace('info')
                        ->addMessage($msg);
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
            : $this->url()->fromRoute('userList', array('id' => $listID));

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
        // Force login if necessary:
        $config = \VuFind\Config\Reader::getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
            && !$this->getUser()
        ) {
            return $this->forceLogin();
        }

        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $view = $this->createViewModel();
        $view->records = $this->getRecordLoader()->loadBatch($ids);

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            // Send parameters back to view so form can be re-populated:
            $view->to = $this->params()->fromPost('to');
            $view->from = $this->params()->fromPost('from');
            $view->message = $this->params()->fromPost('message');

            // Build the URL to share:
            $params = array();
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $this->getServiceLocator()->get('VuFind\Mailer')->sendLink(
                    $view->to, $view->from, $view->message,
                    $url, $this->getViewRenderer(), 'bulk_email_title'
                );
                return $this->redirectToSource('info', 'email_success');
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
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
        $this->getRequest()->getQuery()->set('id', $ids);
        return $this->forwardTo('Records', 'Home');
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
        if (!is_null($this->params()->fromPost('submit'))) {
            $format = $this->params()->fromPost('format');
            $url = $export->getBulkUrl($this->getViewRenderer(), $format, $ids);
            if ($export->needsRedirect($format)) {
                return $this->redirect()->toUrl($url);
            }
            $msg = array(
                'translate' => false, 'html' => true,
                'msg' => $this->getViewRenderer()->render(
                    'cart/export-success.phtml', array('url' => $url)
                )
            );
            return $this->redirectToSource('info', $msg);
        }

        // Load the records:
        $view = $this->createViewModel();
        $view->records = $this->getRecordLoader()->loadBatch($ids);

        // Assign the list of legal export options.  We'll filter them down based
        // on what the selected records actually support.
        $view->exportOptions = $export->getBulkOptions();
        foreach ($view->records as $driver) {
            // Filter out unsupported export formats:
            $newFormats = array();
            foreach ($view->exportOptions as $current) {
                if ($driver->supportsExport($current)) {
                    $newFormats[] = $current;
                }
            }
            $view->exportOptions = $newFormats;
        }

        // No legal export options?  Display a warning:
        if (empty($view->exportOptions)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('bulk_export_not_supported');
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
        $ids = $this->params()->fromQuery('i', array());
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
        $parts = array();
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
        // Load record information first (no need to prompt for login if we just
        // need to display a "no records" error message):
        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Make sure user is logged in:
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Process submission if necessary:
        if (!is_null($this->params()->fromPost('submit'))) {
            $this->favorites()
                ->saveBulk($this->getRequest()->getPost()->toArray(), $user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('bulk_save_success');
            $list = $this->params()->fromPost('list');
            if (!empty($list)) {
                return $this->redirect()->toRoute('userList', array('id' => $list));
            } else {
                return $this->redirectToSource();
            }
        }

        // Pass record and list information to view:
        return $this->createViewModel(
            array(
                'records' => $this->getRecordLoader()->loadBatch($ids),
                'lists' => $user->getLists()
            )
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
        if (!is_null($flashNamespace) && !empty($flashMsg)) {
            $this->flashMessenger()->setNamespace($flashNamespace)
                ->addMessage($flashMsg);
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