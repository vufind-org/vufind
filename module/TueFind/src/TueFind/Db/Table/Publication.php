<?php

namespace TueFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface as ResultSet;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;

class Publication extends \VuFind\Db\Table\Gateway {

    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_publications'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getByUserId($userId): ResultSet
    {
        return $this->select(['user_id' => $userId]);
    }

    public function getByControlNumber($controlNumber)
    {
        return $this->select(['control_number' => $controlNumber])->current();
    }

    public function addPublication(int $userId, string $controlNumber, int $documntId, string $externalDocumentGuid, string $termsDate): bool
    {
        $this->insert(['user_id' => $userId, 'control_number' => $controlNumber, 'external_document_id' => $documntId, 'external_document_guid' => $externalDocumentGuid, 'terms_date' => $termsDate]);
        return true;
    }
}
