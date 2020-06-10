<?php
/**
 * AJAX handler for fetching record info by authority id.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Record\Loader;
use VuFind\RecordTab\TabManager;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Record as RecordHelper;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for fetching record info by authority id.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordInfoByAuthority extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Tab manager
     *
     * @var TabManager
     */
    protected $recordHelper;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Tab manager
     *
     * @var TabManager
     */
    protected $tabManager;

    /**
     * Constructor
     *
     * @param SessionSettings $ss           Session settings
     * @param Loader          $loader       Record loader
     * @param RecordHelper    $recordHelper Record view helper
     * @param TabManager      $tm           Recordtab manager
     */
    public function __construct(
        SessionSettings $ss, Loader $loader, RecordHelper $recordHelper, $tm
    ) {
        $this->sessionSettings = $ss;
        $this->recordLoader = $loader;
        $this->recordHelper = $recordHelper;
        $this->tabManager = $tm;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites(); // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));
        $driver = $this->recordLoader->load($id, 'SolrAuth');
        $html = $this->recordHelper->__invoke($driver)->getAuthoritySummary();

        return $this->formatResponse(compact('html'));
    }
}
