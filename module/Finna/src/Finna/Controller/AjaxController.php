<?php

/**
 * Ajax Controller Module
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Controller;

/**
 * This controller handles Finna AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    /**
     * Handle a file download with AJAX call
     *
     * @return \Laminas\Http\Response
     */
    public function fileAction()
    {
        $method = $this->params()->fromQuery('method');
        if (!$method) {
            return $this->getAjaxResponse('text/plain', ['error' => 'Parameter "method" missing'], 400);
        }
        // Check the AJAX handler plugin manager for the method.
        if (!$this->ajaxManager) {
            throw new \Exception('AJAX Handler Plugin Manager missing.');
        }
        if ($this->ajaxManager->has($method)) {
            try {
                $handler = $this->ajaxManager->get($method);
                if ($handler->supportsStream ?? false) {
                    [$data, $status] = $handler->handleRequest($this->params());
                    if ($status === 200) {
                        return $this->getFileResponse($data);
                    }
                }
            } catch (\Exception $e) {
                return $this->getExceptionResponse('text/plain', $e);
            }
        }

        // If we got this far, we can't handle the requested method:
        return $this->getAjaxResponse(
            'text/plain',
            $this->translate('Invalid Method'),
            \VuFind\AjaxHandler\AjaxHandlerInterface::STATUS_HTTP_BAD_REQUEST
        );
    }

    /**
     * Send output data and exit.
     *
     * @param mixed $data The response data
     *
     * @return \Laminas\Http\Response
     * @throws \Exception
     */
    protected function getFileResponse($data)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', $data['mediaType']);
        $headers->addHeaderLine('Content-Disposition', 'attachment; filename="' . $data['fileName'] . '"');
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        return $response->setContent(stream_get_contents($data['filePointer']));
    }
}
