<?php

/**
 * OAI Module Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use VuFindApi\Formatter\RecordFormatter;

/**
 * OAIController Class
 *
 * Controls the OAI server
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class OaiController extends AbstractBase
{
    /**
     * Display OAI server form.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        // no action needed
        return $this->createViewModel();
    }

    /**
     * Standard OAI server.
     *
     * @return \Laminas\Http\Response
     */
    public function authserverAction()
    {
        return $this->handleOAI(\VuFind\OAI\Server\Auth::class);
    }

    /**
     * Standard OAI server.
     *
     * @return \Laminas\Http\Response
     */
    public function serverAction()
    {
        return $this->handleOAI(\VuFind\OAI\Server::class);
    }

    /**
     * Shared OAI logic.
     *
     * @param string $serverClass Class to load for handling OAI requests.
     *
     * @return \Laminas\Http\Response
     */
    protected function handleOAI($serverClass)
    {
        // Check if the OAI Server is enabled before continuing
        $config = $this->getConfig();
        $response = $this->getResponse();
        if (!isset($config->OAI)) {
            $response->setStatusCode(404);
            $response->setContent('OAI Server Not Configured.');
            return $response;
        }

        // Collect relevant parameters for OAI server:
        $url = explode('?', $this->getServerUrl());
        $baseURL = $url[0];

        // Build OAI response or die trying:
        try {
            $params = array_merge(
                $this->getRequest()->getQuery()->toArray(),
                $this->getRequest()->getPost()->toArray()
            );
            $server = $this->getService($serverClass);
            $server->init($config, $baseURL, $params);
            $server->setRecordLinkerHelper(
                $this->getViewRenderer()->plugin('recordLinker')
            );
            $server->setRecordFormatter(
                $this->getService(RecordFormatter::class)
            );
            $xml = $server->getResponse();
        } catch (\Exception $e) {
            $response->setStatusCode(500);
            $error = APPLICATION_ENV === 'development'
                ? $e->getMessage()
                : $this->translate('An error has occurred');
            $response->setContent($error);
            return $response;
        }

        // Return response:
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/xml; charset=UTF-8');
        $response->setContent($xml);
        return $response;
    }
}
