<?php
namespace TueFindApi\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;

class MltApiController extends \VuFindApi\Controller\SearchApiController
{

    /**
     * Search route uri
     *
     * @var string
     */
    protected $searchRoute = 'mlt';


    public function similarAction() {
         // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->recordAccessPermission)) {
            return $result;
        }

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        if (is_array($request['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Multiple ids unsupported');
        }

        $loader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        try {
            $results[] = $loader->load($request['id'], $this->searchClassId);
        } catch (\Exception $e) {
            return $this->output(
                [], self::STATUS_ERROR, 400,
                'Error loading record ' . $request['id']
            );
        }

        $searchService = $this->serviceLocator->get(\VuFindSearch\Service::class);
        try {
            $results = $searchService->similar($this->searchClassId, $request['id'])->getRecords();
        } catch (\Exception $e) {
            return $this->output(
                [], self::STATUS_ERROR, 400,
                'Error determining similar records'
            );
        }

        $response = [
            'resultCount' => count($results)
        ];
        $requestedFields = $this->getFieldList($request);
        if ($records = $this->recordFormatter->format($results, $requestedFields)) {
            $response['records'] = $records;
        }

        return $this->output($response, self::STATUS_OK);
    }
}
?>
