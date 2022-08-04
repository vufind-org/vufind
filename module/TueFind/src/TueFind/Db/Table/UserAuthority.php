<?php

namespace TueFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface as ResultSet;
use Laminas\Db\Sql\Select;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use TueFind\Db\Row\UserAuthority as UserAuthorityRow;

class UserAuthority extends \VuFind\Db\Table\Gateway {

    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_user_authorities'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getAll()
    {
        $select = $this->getSql()->select();
        $select->join('user', 'tuefind_user_authorities.user_id = user.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->order('username ASC, authority_id ASC');
        return $this->selectWith($select);
    }

    public function hasGrantedAuthorityRight($userId, $authorityIds): bool
    {
        $select = $this->getSql()->select();
        $where = new \Laminas\Db\Sql\Where();
        $where->in("authority_id", $authorityIds);
        $where->equalTo('user_id', $userId);
        $where->equalTo('access_state', 'granted');
        $select->where($where);

        $rows = $this->selectWith($select);
        return count($rows) > 0;
    }

    public function getByUserId($userId, $accessState=null): ResultSet
    {
        $whereParams = ['user_id' => $userId];
        if (isset($accessState))
            $whereParams['access_state'] = $accessState;

        return $this->select($whereParams);
    }

    public function getByAuthorityId($authorityId): ?UserAuthorityRow
    {
        return $this->select(['authority_id' => $authorityId])->current();
    }

    public function getByUserIdAndAuthorityId($userId, $authorityId): ?UserAuthorityRow
    {
        return $this->select(['user_id' => $userId, 'authority_id' => $authorityId])->current();
    }

    public function addRequest($userId, $authorityId)
    {
        $this->insert(['user_id' => $userId, 'authority_id' => $authorityId, 'access_state' => 'requested']);
    }
}
