<?php
namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Cover\Loader as CoverLoader;
use VuFind\Cover\Router;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\ILS\Driver\CacheTrait;
use VuFind\Record\Loader as RecordLoader;

class GetRecordCover extends AbstractBase implements AjaxHandlerInterface
{
    use CacheTrait;

    /**
     * @var CoverLoader
     */
    protected $coverLoader;

    /**
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * @var Router
     */
    protected $coverRouter;

    public function __construct(CoverLoader $coverLoader, RecordLoader $recordLoader, Router $coverRouter)
    {
        $this->coverLoader = $coverLoader;
        $this->recordLoader = $recordLoader;
        $this->coverRouter = $coverRouter;
    }

    public function handleRequest(Params $params)
    {
        $recordId = $params->fromQuery('recordid');
        $size = $params->fromQuery('size');
        try {
            $record = $this->recordLoader->load($recordId);
        } catch (RecordMissingException $exception) {
            return $this->formatResponse(
                'Could not load record: ' . $exception->getMessage(),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if ( !in_array($size, ['small', 'medium' , 'large'])) {
           return $this->formatResponse('Not valid size: ' . $size,
               self::STATUS_HTTP_BAD_REQUEST
           );
        }

        $cover = $record->tryMethod('getThumbnail');
        if ($cover === null) {
           return $this->formatResponse(
               'Could not load cover for record id ' . $recordId,
               self::STATUS_HTTP_NOT_FOUND
           );
        }

        return $this->formatResponse([
            'url' => $this->coverRouter->getUrl($record, $size ?? 'small')
        ]);
    }
}
