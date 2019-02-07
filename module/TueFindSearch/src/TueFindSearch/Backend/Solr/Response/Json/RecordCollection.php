<?php
namespace TueFindSearch\Backend\Solr\Response\Json;


class RecordCollection extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollection
{
    public function __construct(array $response)
    {
        parent::__construct($response);
    }

    public function getExplainOther()

    {
        return $this->response['debug']['explainOther'] ?? [];
    }
}
?>

