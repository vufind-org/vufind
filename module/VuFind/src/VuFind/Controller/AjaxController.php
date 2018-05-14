<?php
/**
 * Ajax Controller Module
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use VuFind\AjaxHandler\PluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AbstractActionController
    implements TranslatorAwareInterface
{
    use AjaxResponseTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param PluginManager $am AJAX Handler Plugin Manager
     */
    public function __construct(PluginManager $am)
    {
        // Add notices to a key in the output
        set_error_handler([static::class, 'storeError']);
        $this->ajaxManager = $am;
    }

    /**
     * Make an AJAX call with a JSON-formatted response.
     *
     * @return \Zend\Http\Response
     */
    public function jsonAction()
    {
        return $this->callAjaxMethod($this->params()->fromQuery('method'));
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return \Zend\Http\Response
     */
    public function recommendAction()
    {
        return $this->callAjaxMethod('recommend', 'text/html');
    }

    /**
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    public function systemStatusAction()
    {
        return $this->callAjaxMethod('systemStatus', 'text/plain');
    }

    /**
     * Get fines data
     *
     * @return \Zend\Http\Response
     */
    public function getUserFinesAjax()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->output('', self::STATUS_OK, 401);
        }
        if (!$this->getILS()->checkCapability('getMyFines')) {
            return $this->output('', self::STATUS_OK, 405);
        }
        $fines = $this->getILS()->getMyFines($this->getUser());
        if (count($fines) === 0) {
            return $this->output(0, self::STATUS_OK);
        }
        $sum = 0;
        foreach ($fines as $fine) {
            $sum += $fine['balance'];
        }
        return $this->output($sum, self::STATUS_OK);
    }

    /**
     * Get holds data
     *
     * @return \Zend\Http\Response
     */
    public function getUserHoldsAjax()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->output('', self::STATUS_OK, 401);
        }
        if (!$this->getILS()->checkCapability('getMyHolds')) {
            return $this->output('', self::STATUS_OK, 405);
        }
        $holds = $this->getILS()->getMyHolds($this->getUser());
        $foundValid = false;
        foreach ($holds as $hold) {
            error_log(print_r($hold, true));
            if (isset($hold['available']) && $hold['available']) {
                return $this->output('PICKUP', self::STATUS_OK);
            }
            if (isset($hold['in_transit']) && $hold['in_transit']) {
                return $this->output('IN TRANSIT', self::STATUS_OK);
            }
        }
        return $this->output('CLEAR', self::STATUS_OK);
    }

    /**
     * Get checkedout items data
     *
     * @return \Zend\Http\Response
     */
    public function getUserTransactionsAjax()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->output('', self::STATUS_OK, 401);
        }
        if (!$this->getILS()->checkCapability('getMyTransactions')) {
            return $this->output('', self::STATUS_OK, 405);
        }
        $items = $this->getILS()->getMyTransactions($this->getUser());
        $counts = [
            'ok' => 0,
            'warn' => 0,
            'overdue' => 0
        ];
        $foundValid = false;
        foreach ($items as $item) {
            if (isset($item['duedate'])) {
                $foundValid = true;
                // Overdue
                if (strtotime($item['duedate']) - time() <= 0) {
                    $counts['overdue'] ++;
                } else {
                    // Due soon (1 week)
                    if (strtotime($item['duedate']) - time() < 60 * 60 * 24 * 7) {
                        $counts['warn'] ++;
                    } else {
                        $counts['ok'] ++;
                    }
                }
            }
        }
        if (!$foundValid) {
            return $this->output('', self::STATUS_OK, 405);
        }
        return $this->output(json_encode($counts), self::STATUS_OK);
    }
}
