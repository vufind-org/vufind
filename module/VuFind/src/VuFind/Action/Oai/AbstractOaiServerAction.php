<?php

/**
 * Base class for OAI-PMH server actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2016-2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Oai;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\OAI\Server;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\RecordLinker;
use VuFindApi\Formatter\RecordFormatter;

/**
 * Base class for OAI-PMH server actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractOaiServerAction extends AbstractTemplateRenderingAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param ServerUrlHelper $serverUrlHelper        Server URL helper
     * @param RecordFormatter $recordFormatter        Record formatter
     * @param RecordLinker    $recordLinkerViewHelper RecordLinker view helper
     * @param array           $config                 VuFind configuration
     */
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        protected RecordFormatter $recordFormatter,
        #[Autowire(container: 'ViewHelperManager')]
        protected RecordLinker $recordLinkerViewHelper,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Shared OAI logic.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param Server                 $server   Class for handling OAI requests
     *
     * @return ResponseInterface
     */
    protected function handleOAI(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Server $server
    ): ResponseInterface {
        // Check if the OAI Server is enabled before continuing
        if (empty($this->config['OAI'])) {
            $response = $response->withStatus(404);
            $response->getBody()->write('OAI Server Not Configured.');
            return $response;
        }

        // Collect relevant parameters for OAI server:
        [$baseURL] = explode('?', $this->serverUrlHelper->getCurrentUrl());

        // Build OAI response or die trying:
        try {
            $params = array_merge(
                $request->getQueryParams(),
                $request->getParsedBody()
            );
            $server->init($this->config, $baseURL, $params);
            $server->setRecordLinkerHelper($this->recordLinkerViewHelper);
            $server->setRecordFormatter($this->recordFormatter);
            $xml = $server->getResponse();
        } catch (\Exception $e) {
            $response = $response->withStatus(500);
            $error = APPLICATION_ENV === 'development'
                ? $e->getMessage()
                : $this->translate('An error has occurred');
            $response->getBody()->write($error);
            return $response;
        }

        // Return response:
        $response = $response->withHeader('Content-type', 'text/xml; charset=UTF-8');
        $response->getBody()->write($xml);
        return $response;
    }
}
