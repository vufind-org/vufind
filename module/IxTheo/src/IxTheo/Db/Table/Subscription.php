<?php
namespace IxTheo\Db\Table;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Zend\Db\Adapter\Adapter;

class Subscription extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Session container for last list information.
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'ixtheo_journal_subscriptions'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getNew($userId, $recordId, $title, $author, $year) {
        $row = $this->createRow();
        $row->id = $userId;
        $row->journal_title = $title ?: "";
        $row->journal_author = $author ?: "";
        $row->journal_year = $year ?: "";
        $row->journal_control_number = $recordId;
        $row->max_last_modification_time = date('Y-m-d H:i:s');
        return $row;
    }

    public function findExisting($userId, $recordId) {
        return $this->select(['id' => $userId, 'journal_control_number' => $recordId])->current();
    }

    public function subscribe($userId, $recordId, $title, $author, $year) {
        $row = $this->getNew($userId, $recordId, $title, $author, $year);
        $row->save();
        return $row->id;
    }

    public function unsubscribe($userId, $recordId) {
        return $this->delete(['id' => $userId, 'journal_control_number' => $recordId]);
    }

    public function getAll($userId, $sort) {
        $select = $this->getSql()->select()->where(['id' => $userId]);
        $this->applySort($select, $sort);
        return $this->selectWith($select);
    }

    public function get($userId, $sort, $start, $limit) {
        $select = $this->getSql()->select()->where(['id' => $userId])->offset($start)->limit($limit);
        $this->applySort($select, $sort);
        return $this->selectWith($select);
    }

    /**
     * Apply a sort parameter to a query on the resource table.
     *
     * @param \Zend\Db\Sql\Select $query Query to modify
     * @param string              $sort  Field to use for sorting (may include 'desc'
     * qualifier)
     *
     * @return void
     */
    public static function applySort($query, $sort)
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'journal_title', 'journal_title desc', 'journal_author', 'journal_author desc', 'journal_year', 'journal_year desc'
        ];
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            $query->order([$sort]);
        }
    }
}
