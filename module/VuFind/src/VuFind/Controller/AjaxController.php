<?php

/**
 * Ajax Controller Module
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use VuFind\AjaxHandler\PluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AbstractActionController implements TranslatorAwareInterface
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
        // Prevent errors, notices etc. from being displayed so that they don't mess
        // with the output (only in production mode):
        if ('production' === APPLICATION_ENV) {
            ini_set('display_errors', '0');
        }
        $this->ajaxManager = $am;
    }

    /**
     * Make an AJAX call with a JSON-formatted response.
     *
     * @return \Laminas\Http\Response
     */
    public function jsonAction()
    {
        $method = $this->params()->fromQuery('method');
        if (!$method) {
            return $this->getAjaxResponse('application/json', ['error' => 'Parameter "method" missing'], 400);
        }
        return $this->callAjaxMethod($method);
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return \Laminas\Http\Response
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
     * @return \Laminas\Http\Response
     */
    public function systemStatusAction()
    {
        return $this->callAjaxMethod('systemStatus', 'text/plain');
    }
}
