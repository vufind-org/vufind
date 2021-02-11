<?php
namespace IxTheo\Db\Table;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;
class IxTheoUser extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    protected $session;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'ixtheo_user'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getNew($userId)
    {
        $row = $this->createRow();
        $row->id = $userId;
        return $row;
    }

    public function canUseTAD($userId)
    {
        return $this->get($userId)->can_use_tad;
    }

    public function get($userId)
    {
        $select = $this->getSql()->select();
        $select->where("id=" . $userId);
        $rowset = $this->selectWith($select);
        return $rowset->current();
    }
}
