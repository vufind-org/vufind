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
use VuFind\Cart, VuFind\Export, VuFind\Record, VuFind\Translator\Translator,
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
     * Process requests for main cart.
     *
     * @return void
     */
    public function homeAction()
    {
        /* TODO
        // We came in from the cart -- let's remember this we can redirect there
        // when we're done:
        $this->session->url = '/Cart';

        // Now forward to the requested action:
        if (strlen($this->_request->getParam('email', '')) > 0) {
            return $this->_forward('Email');
        } else if (strlen($this->_request->getParam('print', '')) > 0) {
            return $this->_forward('PrintCart');
        } else if (strlen($this->_request->getParam('saveCart', '')) > 0) {
            return $this->_forward('Save');
        } else if (strlen($this->_request->getParam('export', '')) > 0) {
            return $this->_forward('Export');
        } else {
            return $this->_forward('Cart');
        }
         */
    }

    /**
     * Display cart contents.
     *
     * @return void
     */
    public function cartAction()
    {
        /* TODO
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');

        // Add items if necessary:
        if (strlen($this->_request->getParam('empty', '')) > 0) {
            Cart::getInstance()->emptyCart();
        } else if (strlen($this->_request->getParam('delete', '')) > 0) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                Cart::getInstance()->removeItems($ids);
            }
        } else if (strlen($this->_request->getParam('add', '')) > 0) {
            if (empty($ids)) {
                return $this->redirectToSource('error', 'bulk_noitems_advice');
            } else {
                $addItems = Cart::getInstance()->addItems($ids);
                if (!$addItems['success']) {
                    $msg = Translator::translate('bookbag_full_msg') . ". "
                        . $addItems['notAdded'] . " "
                        . Translator::translate('items_already_in_bookbag') . ".";
                    $this->_helper->flashMessenger->setNamespace('info')
                        ->addMessage($msg);
                }
            }
        }
         */
    }

    /**
     * Process bulk actions from the MyResearch area; most of this is only necessary
     * when Javascript is disabled.
     *
     * @return void
     */
    public function myresearchbulkAction()
    {
        /* TODO
        // We came in from the MyResearch section -- let's remember which list (if
        // any) we came from so we can redirect there when we're done:
        $listID = $this->_request->getParam('listID');
        $this->session->url = empty($listID)
            ? '/MyResearch/Favorites' : '/MyResearch/MyList/' . $listID;

        // Now forward to the requested action:
        if (strlen($this->_request->getParam('email', '')) > 0) {
            return $this->_forward('Email');
        } else if (strlen($this->_request->getParam('print', '')) > 0) {
            return $this->_forward('PrintCart');
        } else if (strlen($this->_request->getParam('delete', '')) > 0) {
            return $this->_forward('Delete', 'MyResearch');
        } else if (strlen($this->_request->getParam('add', '')) > 0) {
            return $this->_forward('Cart');
        } else if (strlen($this->_request->getParam('export', '')) > 0) {
            return $this->_forward('Export');
        } else {
            throw new Exception('Unrecognized bulk action.');
        }
         */
    }

    /**
     * Email a batch of records.
     *
     * @return void
     */
    public function emailAction()
    {
        /* TODO
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $this->view->records = Record::loadBatch($ids);

        // Process form submission:
        if ($this->_request->getParam('submit')) {
            // Send parameters back to view so form can be re-populated:
            $this->view->to = $this->_request->getParam('to');
            $this->view->from = $this->_request->getParam('from');
            $this->view->message = $this->_request->getParam('message');

            // Build the URL to share:
            $params = array();
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $router = Zend_Controller_Front::getInstance()->getRouter();
            $target = $router->assemble(
                array('controller' => 'Records', 'action' => 'Home'), 'default',
                true, false
            );
            $url = $this->view->fullUrl($target) . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = new VF_Mailer();
                $mailer->sendLink(
                    $this->view->to, $this->view->from, $this->view->message,
                    $url, $this->view, 'bulk_email_title'
                );
                return $this->redirectToSource('info', 'email_success');
            } catch (VF_Exception_Mail $e) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }
         */
    }

    /**
     * Print a batch of records.
     *
     * @return void
     */
    public function printcartAction()
    {
        /* TODO
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $this->_request->setParam('id', $ids);
        return $this->_forward('Home', 'Records');
         */
    }

    /**
     * Set up export of a batch of records.
     *
     * @return void
     */
    public function exportAction()
    {
        /* TODO
        // Get the desired ID list:
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Process form submission if necessary:
        if (!is_null($this->_request->getParam('submit'))) {
            $format = $this->_request->getParam('format');
            $url = Export::getBulkUrl($this->view, $format, $ids);
            if (Export::needsRedirect($format)) {
                return $this->_redirect($url);
            }
            $this->view->url = $url;
            $msg = array(
                'translate' => false, 'html' => true,
                'msg' => $this->view->render('cart/export-success.phtml')
            );
            return $this->redirectToSource('info', $msg);
        }

        // Load the records:
        $this->view->records = Record::loadBatch($ids);

        // Assign the list of legal export options.  We'll filter them down based
        // on what the selected records actually support.
        $this->view->exportOptions = Export::getBulkOptions();
        foreach ($this->view->records as $driver) {
            // Filter out unsupported export formats:
            $newFormats = array();
            foreach ($this->view->exportOptions as $current) {
                if ($driver->supportsExport($current)) {
                    $newFormats[] = $current;
                }
            }
            $this->view->exportOptions = $newFormats;
        }

        // No legal export options?  Display a warning:
        if (empty($this->view->exportOptions)) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('bulk_export_not_supported');
        }
         */
    }

    /**
     * Actually perform the export operation.
     *
     * @return void
     */
    public function doexportAction()
    {
        /* TODO
        // We use abbreviated parameters here to keep the URL short (there may
        // be a long list of IDs, and we don't want to run out of room):
        $ids = $this->_request->getParam('i', array());
        $format = $this->_request->getParam('f');

        // Make sure we have IDs to export:
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Send appropriate HTTP headers for requested format:
        Export::setHeaders($format, $this->getResponse());

        // Turn off layouts and rendering -- we only want to display export data!
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Actually export the records
        $records = Record::loadBatch($ids);
        $parts = array();
        foreach ($records as $record) {
            $parts[] = $this->view->record($record)->getExport($format);
        }

        // Process and display the exported records
        $this->getResponse()->appendBody(Export::processGroup($format, $parts));
         */
    }

    /**
     * Save a batch of records.
     *
     * @return void
     */
    public function saveAction()
    {
        /* TODO
        // Make sure user is logged in:
        $user = $this->getUser;
        if ($user == false) {
            return $this->forceLogin();
        }

        // Load record information:
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }

        // Process submission if necessary:
        if (!is_null($this->_request->getParam('submit'))) {
            $this->_helper->favorites->saveBulk($this->_request->getParams(), $user);
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('bulk_save_success');
            $list = $this->_request->getParam('list');
            if (!empty($list)) {
                return $this->_redirect('/MyResearch/MyList/' . $list);
            } else {
                return $this->redirectToSource();
            }
        }

        $this->view->records = Record::loadBatch($ids);

        // Load list information:
        $this->view->lists = $user->getLists();
         */
    }

    /**
     * Support method: redirect to the page we were on when the bulk action was
     * initiated.
     *
     * @param string $flashNamespace Namespace for flash message (null for none)
     * @param string $flashMsg       Flash message to set (ignored if namespace null)
     *
     * @return void
     */
    public function redirectToSource($flashNamespace = null, $flashMsg = null)
    {
        /* TODO
        // Set flash message if requested:
        if (!is_null($flashNamespace) && !empty($flashMsg)) {
            $this->_helper->flashMessenger->setNamespace($flashNamespace)
                ->addMessage($flashMsg);
        }

        // If we entered the controller in the expected way (i.e. via the
        // myresearchbulk action), we should have a source set in the followup
        // memory.  If that's missing for some reason, just forward to MyResearch.
        if (isset($this->session->url)) {
            $target = $this->session->url;
            unset($this->session->url);
        } else {
            $target = '/MyResearch';
        }
        return $this->_redirect($target);
         */
    }
}