<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Sql;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Adapter\Platform\PlatformInterface;

class Sql
{
    /** @var AdapterInterface */
    protected $adapter = null;

    /** @var string|array|TableIdentifier */
    protected $table = null;

    /** @var Platform\Platform */
    protected $sqlPlatform = null;

    public function __construct(AdapterInterface $adapter, $table = null, Platform\AbstractPlatform $sqlPlatform = null)
    {
        $this->adapter = $adapter;
        if ($table) {
            $this->setTable($table);
        }
        $this->sqlPlatform = ($sqlPlatform) ?: new Platform\Platform($adapter);
    }

    /**
     * @return null|\Zend\Db\Adapter\AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    public function hasTable()
    {
        return ($this->table != null);
    }

    public function setTable($table)
    {
        if (is_string($table) || is_array($table) || $table instanceof TableIdentifier) {
            $this->table = $table;
        } else {
            throw new Exception\InvalidArgumentException('Table must be a string, array or instance of TableIdentifier.');
        }
        return $this;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getSqlPlatform()
    {
        return $this->sqlPlatform;
    }

    public function select($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Select(($table) ?: $this->table);
    }

    public function insert($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Insert(($table) ?: $this->table);
    }

    public function update($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Update(($table) ?: $this->table);
    }

    public function delete($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Delete(($table) ?: $this->table);
    }

    /**
     * @param PreparableSqlInterface $sqlObject
     * @param StatementInterface|null $statement
     * @return StatementInterface
     */
    public function prepareStatementForSqlObject(PreparableSqlInterface $sqlObject, StatementInterface $statement = null)
    {
        $statement = ($statement) ?: $this->adapter->getDriver()->createStatement();

        if ($this->sqlPlatform) {
            $this->sqlPlatform->setSubject($sqlObject);
            $this->sqlPlatform->prepareStatement($this->adapter, $statement);
        } else {
            $sqlObject->prepareStatement($this->adapter, $statement);
        }

        return $statement;
    }

    public function getSqlStringForSqlObject(SqlInterface $sqlObject, PlatformInterface $platform = null)
    {
        $platform = ($platform) ?: $this->adapter->getPlatform();

        if ($this->sqlPlatform) {
            $this->sqlPlatform->setSubject($sqlObject);
            $sqlString = $this->sqlPlatform->getSqlString($platform);
        } else {
            $sqlString = $sqlObject->getSqlString($platform);
        }

        return $sqlString;
    }
}
