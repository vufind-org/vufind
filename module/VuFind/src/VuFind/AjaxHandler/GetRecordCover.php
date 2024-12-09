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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\PhpRenderer;
use VuFind\Cache\CacheTrait;
use VuFind\Cover\Router as CoverRouter;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Session\Settings as SessionSettings;

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
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Cover router
     *
     * @var CoverRouter
     */
    protected $coverRouter;

    /**
     * PHP renderer
     *
     * @var PhpRenderer
     */
    protected $renderer;

    /**
     * If true we will render a fallback html template in case no image could be
     * loaded
     *
     * @var bool
     */
    protected $useCoverFallbacksOnFail = false;

    /**
     * GetRecordCover constructor.
     *
     * @param SessionSettings $ss                      Session settings
     * @param RecordLoader    $recordLoader            Record loader
     * @param CoverRouter     $coverRouter             Cover router
     * @param PhpRenderer     $renderer                PHP renderer (only
     * required if $userCoverFallbacksOnFail is set to true)
     * @param bool            $useCoverFallbacksOnFail If true we will render a
     * fallback html template in case no image could be loaded
     */
    public function __construct(
        SessionSettings $ss,
        RecordLoader $recordLoader,
        CoverRouter $coverRouter,
        ?PhpRenderer $renderer = null,
        $useCoverFallbacksOnFail = false
    ) {
        $this->sessionSettings = $ss;
        $this->recordLoader = $recordLoader;
        $this->coverRouter = $coverRouter;
        $this->renderer = $renderer;
        $this->useCoverFallbacksOnFail = $useCoverFallbacksOnFail;
    }

    /**
     * Handle request
     *
     * @param Params $params Request parameters
     *
     * @return array
     * @throws \Exception
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();

        $recordId = $params->fromQuery('recordId');
        $recordSource = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $size = $params->fromQuery('size', 'small');
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
                    'html' => $this->renderer->render(
                        'record/coverReplacement',
                        ['driver' => $record]
                    ),
                ]
            );
    }
}
