<?php

/**
 * Record export action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFind\Action\Record;

use GuzzleHttp\Utils as GuzzleUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Export;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record;

/**
 * Record export action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExportAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory    $searchMemory     Search memory
     * @param TabManager      $tabManager       Tab manager
     * @param AuthManager     $authManager      Authentication manager
     * @param RecordLoader    $recordLoader     Record loader
     * @param RecordRouter    $recordRouter     Record router
     * @param ResultScroller  $resultScroller   Result scroller
     * @param array           $config           VuFind configuration
     * @param Export          $export           Export service
     * @param ServerUrlHelper $serverUrlHelper  Server URL helper
     * @param Record          $recordViewHelper Record view helper
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        protected Export $export,
        protected ServerUrlHelper $serverUrlHelper,
        #[Autowire(container: 'ViewHelperManager')]
        protected Record $recordViewHelper,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Export a record.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $format = $this->getQueryParam('style', '');
        return $this->doExport($request, $response, $format);
    }

    /**
     * Perform the export and return an appropriate response.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     * @param string                 $format   Export format
     *
     * @return ResponseInterface
     */
    protected function doExport(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $format
    ): ResponseInterface {
        $driver = $this->loadRecord();
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);

        // Display export menu if missing/invalid option
        if (empty($format) || !$this->export->recordSupportsFormat($driver, $format)) {
            if (!empty($format)) {
                $flashMessagesHelper->addErrorMessage('export_invalid_format');
            }
            return $this->renderTemplate($request, $response, $this->getTemplateParams(), 'record/export-menu');
        }

        // If this is an export format that redirects to an external site, perform
        // the redirect now (unless we're being called back from that service!):
        if (
            $this->export->needsRedirect($format)
            && !$this->getQueryParam('callback')
        ) {
            // Build callback URL:
            $parts = explode('?', $this->serverUrlHelper->getCurrentUrl());
            $callback = $parts[0] . '?callback=1&style=' . urlencode($format);

            return $this->getHelper(RedirectHelper::class)
                ->redirectToUrl($response, $this->export->getRedirectUrl($format, $callback));
        }

        try {
            $exportedRecord = ($this->recordViewHelper)($driver)->getExport($format);
        } catch (\VuFind\Exception\FormatUnavailable $e) {
            $flashMessagesHelper->addErrorMessage('export_unsupported_format');
            return $this->redirectToRecord();
        }

        $exportType = $this->export->getBulkExportType($format);
        if ('post' === $exportType) {
            $params = [
                'exportType' => 'post',
                'postField' => $this->export->getPostField($format),
                'postData' => $exportedRecord,
                'targetWindow' => $this->export->getTargetWindow($format),
                'url' => $this->export->getRedirectUrl($format, ''),
                'format' => $format,
            ];
            $msg = [
                'translate' => false,
                'html' => true,
                'msg' => $this->templateRenderer->renderTemplateAsString(
                    $request,
                    'cart/export-success.phtml',
                    $params
                ),
            ];
            $flashMessagesHelper->addSuccessMessage($msg);
            return $this->redirectToRecord();
        }

        // Send appropriate HTTP headers for requested format:
        foreach (GuzzleUtils::headersFromLines($this->export->getHeaders($format)) as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        // Actually export the record
        $response->getBody()->write($exportedRecord);
        return $response;
    }
}
