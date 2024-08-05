<?php

/**
 * AJAX handler for fetching versions link
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Record\Loader;
use VuFind\RecordTab\TabManager;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Record;

use function count;
use function is_array;

/**
 * AJAX handler for fetching versions link
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordVersions extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Record plugin
     *
     * @var Record
     */
    protected $recordPlugin;

    /**
     * Tab manager
     *
     * @var TabManager
     */
    protected $tabManager;

    /**
     * Constructor
     *
     * @param SessionSettings $ss     Session settings
     * @param Loader          $loader Record loader
     * @param Record          $rp     Record plugin
     * @param TabManager      $tm     Tab manager
     */
    public function __construct(
        SessionSettings $ss,
        Loader $loader,
        Record $rp,
        TabManager $tm
    ) {
        $this->sessionSettings = $ss;
        $this->recordLoader = $loader;
        $this->recordPlugin = $rp;
        $this->tabManager = $tm;
    }

    /**
     * Load a single record and render the link template
     *
     * @param string $id       Record id
     * @param string $source   Record source
     * @param string $searchId Search ID
     *
     * @return string
     */
    protected function getVersionsLinkForRecord($id, $source, $searchId)
    {
        $driver = $this->recordLoader->load($id, $source, $searchId);
        $tabs = $this->tabManager->getTabsForRecord($driver);
        $full = true;

        return ($this->recordPlugin)($driver)->renderTemplate(
            'versions-link.phtml',
            compact('driver', 'tabs', 'full', 'searchId')
        );
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

        $id = $params->fromPost('id') ?: $params->fromQuery('id');
        $source = $params->fromPost('source') ?: $params->fromQuery('source');
        $searchId = $params->fromPost('sid') ?: $params->fromQuery('sid');

        if (!is_array($id)) {
            return $this->formatResponse(
                $this->getVersionsLinkForRecord($id, $source, $searchId)
            );
        }

        $htmlByRecord = [];
        for ($i = 0; $i < count($id); $i++) {
            $key = $source[$i] . '|' . $id[$i];

            $htmlByRecord[$key] = $this->getVersionsLinkForRecord(
                $id[$i],
                $source[$i],
                $searchId
            );
        }

        return $this->formatResponse(['records' => $htmlByRecord]);
    }
}
