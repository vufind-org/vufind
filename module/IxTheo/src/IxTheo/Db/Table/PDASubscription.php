<?php
namespace IxTheo\Db\Table;
use VuFind\Exception\LoginRequired as LoginRequiredException,
    VuFind\Exception\RecordMissing as RecordMissingException,
    Zend\Db\Sql\Expression;
class PDASubscription extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
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
     * @param \Zend\Session\Container $session Session container (must use same
     * namespace as container provided to \VuFind\View\Helper\Root\UserList).
     */
    public function __construct()
    {
        parent::__construct('ixtheo_pda_subscriptions', 'IxTheo\Db\Row\PDASubscription');
    }

    public function getNew($userId, $ppn, $title, $author, $year, $isbn) {
        $row = $this->createRow();
        $row->id = $userId;
        $row->book_title = $title ?: "";
        $row->book_author = $author ?: "";
        $row->book_year = $year ?: "";
        $row->book_ppn = $ppn ?: "";
        $row->book_isbn = $isbn ?: "";
        return $row;
    }

    public function findExisting($userId, $ppn) {
        return $this->select(['id' => $userId, 'book_ppn' => $ppn])->current();
    }

    public function subscribe($userId, $ppn, $title, $author, $year, $isbn) {
        $row = $this->getNew($userId, $ppn, $title, $author, $year, $isbn);
        $row->save();
        return $row->id;
    }

    public function unsubscribe($userId, $recordId) {
        return $this->delete(['id' => $userId, 'book_ppn' => $recordId]);
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
            'book_title', 'book_title desc', 'book_author', 'book_author desc', 'book_year', 'book_year desc'
        ];
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            $query->order([$sort]);
        }
    }
}
