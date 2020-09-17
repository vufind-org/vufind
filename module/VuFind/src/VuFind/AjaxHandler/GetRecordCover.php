<?php
/**
 * GetRecordCover AJAX handler.
 *
 * PHP version 7
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
use VuFind\Cover\Router as CoverRouter;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\ILS\Driver\CacheTrait;
use VuFind\Record\Loader as RecordLoader;

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
     * @param RecordLoader $recordLoader            Record loader
     * @param CoverRouter  $coverRouter             Cover router
     * @param PhpRenderer  $renderer                PHP renderer (only required if
     * $userCoverFallbacksOnFail is set to true)
     * @param bool         $useCoverFallbacksOnFail If true we will render a
     * fallback html template in case no image could be loaded
     */
    public function __construct(RecordLoader $recordLoader,
        CoverRouter $coverRouter,
        ?PhpRenderer $renderer = null,
        $useCoverFallbacksOnFail = false
    ) {
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
        $recordId = $params->fromQuery('recordId');
        $recordSource = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $size = $params->fromQuery('size', 'small');
        try {
            $record = $this->recordLoader->load($recordId, $recordSource);
        } catch (RecordMissingException $exception) {
            return $this->formatResponse(
                'Could not load record: ' . $exception->getMessage(),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (!in_array($size, ['small', 'medium', 'large'])) {
            return $this->formatResponse(
                'Not valid size: ' . $size,
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        $url = $this->coverRouter->getUrl(
            $record, $size ?? 'small', true, $this->useCoverFallbacksOnFail
        );

        return ($url || !$this->renderer || !$this->useCoverFallbacksOnFail)
            ? $this->formatResponse(compact('url', 'size'))
            : $this->formatResponse(
                [
                    'html' => $this->renderer->render(
                        'record/coverReplacement',
                        ['driver' => $record]
                    )
                ]
            );
    }
}
