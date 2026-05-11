<?php

/**
 * GetRecordCover AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Cache\CacheTrait;
use VuFind\Cover\Router as CoverRouter;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

use function in_array;

/**
 * GetRecordCover AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordCover extends AbstractBase implements AjaxHandlerInterface
{
    use CacheTrait;

    /**
     * GetRecordCover constructor.
     *
     * @param SessionSettings            $ss                      Session settings
     * @param RecordLoader               $recordLoader            Record loader
     * @param CoverRouter                $coverRouter             Cover router
     * @param ?TemplateRendererInterface $renderer                Template renderer (required if
     *                                                            $userCoverFallbacksOnFail is
     *                                                            set to true)
     * @param bool                       $useCoverFallbacksOnFail If true we will render a
     *                                                            fallback html template
     *                                                            in case no image could
     *                                                            be loaded
     */
    public function __construct(
        SessionSettings $ss,
        protected RecordLoader $recordLoader,
        protected CoverRouter $coverRouter,
        protected ?TemplateRendererInterface $renderer,
        protected $useCoverFallbacksOnFail = false
    ) {
        parent::__construct($ss);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $this->disableSessionWrites();

        $recordId = $this->getQueryParam($request, 'recordId');
        $recordSource = $this->getQueryParam($request, 'source', DEFAULT_SEARCH_BACKEND);
        $size = $this->getQueryParam($request, 'size', 'small');
        if (!in_array($size, ['small', 'medium', 'large'])) {
            $size = 'small';
        }
        $record = $this->recordLoader->load($recordId, $recordSource, true);
        $metadata = $this->coverRouter->getMetadata(
            $record,
            $size ?? 'small',
            true,
            $this->useCoverFallbacksOnFail,
            true
        );

        return ($metadata || !$this->renderer || !$this->useCoverFallbacksOnFail)
            ? $this->formatResponse(array_merge($metadata, compact('size')))
            : $this->formatResponse(
                [
                    'html' => $this->renderer->renderTemplateAsString(
                        $request,
                        'record/coverReplacement',
                        ['driver' => $record]
                    ),
                ]
            );
    }
}
