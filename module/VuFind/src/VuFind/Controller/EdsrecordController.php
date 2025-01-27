<?php

/**
 * EDS Record Controller
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
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFindSearch\ParamBag;

/**
 * EDS Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsrecordController extends AbstractRecord
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        // Override some defaults:
        $this->sourceId = 'EDS';
        $this->fallbackDefaultTab = 'Description';

        // Call standard record controller initialization:
        parent::__construct($sm);
    }

    /**
     * Redirect to an eBook.
     *
     * @param string $format Format of eBook to request from API.
     * @param string $method Record driver method to use to obtain target URL.
     *
     * @return mixed
     */
    protected function redirectToEbook($format, $method)
    {
        $paramArray = $format === null ? [] : ['ebookpreferredformat' => $format];
        $params = new ParamBag($paramArray);
        $driver = $this->loadRecord($params, true);
        // If the user is a guest, redirect them to the login screen.
        $auth = $this->getAuthorizationService();
        if (!$auth->isGranted('access.EDSExtendedResults')) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw new ForbiddenException('Access denied.');
        }
        $url = $driver->tryMethod($method);
        if (!$url) {
            $this->flashMessenger()->addErrorMessage($this->translate('error_accessing_full_text'));
            return $this->redirect()->toRoute('edsrecord', ['id' => $this->params()->fromRoute('id')]);
        }
        return $this->redirect()->toUrl($url);
    }

    /**
     * Action to display ePub.
     *
     * @return mixed
     */
    public function epubAction()
    {
        return $this->redirectToEbook('ebook-epub', 'getEpubLink');
    }

    /**
     * Linked text display action.
     *
     * @return mixed
     */
    public function linkedtextAction()
    {
        return $this->redirectToEbook(null, 'getLinkedFullTextLink');
    }

    /**
     * PDF display action.
     *
     * @return mixed
     */
    public function pdfAction()
    {
        return $this->redirectToEbook('ebook-pdf', 'getPdfLink');
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getService(\VuFind\Config\PluginManager::class)->get('EDS');
        return $config->Record->next_prev_navigation ?? false;
    }
}
