<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Sql\Platform\IbmDb2;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Adapter\Platform\PlatformInterface;
use Zend\Db\Adapter\StatementContainerInterface;
use Zend\Db\Sql\Platform\PlatformDecoratorInterface;
use Zend\Db\Sql\Select;

class SelectDecorator extends Select implements PlatformDecoratorInterface
{
    /**
     * @var bool
     */
    protected $isSelectContainDistinct= false;

    /**
     * @var Select
     */
    protected $select = null;

    /**
     * @return bool
     */
    public function getIsSelectContainDistinct()
    {
        return $this->isSelectContainDistinct;
    }

    /**
     * @param boolean $isSelectContainDistinct
     */
    public function setIsSelectContainDistinct($isSelectContainDistinct)
    {
        $this->isSelectContainDistinct = $isSelectContainDistinct;
    }

    /**
     * @param Select $select
     */
    public function setSubject($select)
    {
        $this->select = $select;
    }

    /**
     * @see Select::renderTable
     */
    protected function renderTable($table, $alias = null)
    {
        return $table . ' ' . $alias;
    }

    /**
     * @param AdapterInterface            $adapter
     * @param StatementContainerInterface $statementContainer
     */
    public function prepareStatement(AdapterInterface $adapter, StatementContainerInterface $statementContainer)
    {
        // localize variables
        foreach (get_object_vars($this->select) as $name => $value) {
            $this->{$name} = $value;
        }
        // set specifications
        unset($this->specifications[self::LIMIT]);
        unset($this->specifications[self::OFFSET]);

        $this->specifications['LIMITOFFSET'] = null;
        parent::prepareStatement($adapter, $statementContainer);
    }

    /**
     * @param  PlatformInterface $platform
     * @return string
     */
    public function getSqlString(PlatformInterface $platform = null)
    {
        // localize variables
        foreach (get_object_vars($this->select) as $name => $value) {
            $this->{$name} = $value;
        }

        unset($this->specifications[self::LIMIT]);
        unset($this->specifications[self::OFFSET]);
        $this->specifications['LIMITOFFSET'] = null;

        return parent::getSqlString($platform);
    }

    /**
     * @param  PlatformInterface  $platform
     * @param  DriverInterface    $driver
     * @param  ParameterContainer $parameterContainer
     * @param  array              $sqls
     * @param  array              $parameters
     */
    protected function processLimitOffset(PlatformInterface $platform, DriverInterface $driver = null, ParameterContainer $parameterContainer = null, &$sqls, &$parameters)
    {
        if ($this->limit === null && $this->offset === null) {
            return;
        }

        $selectParameters = $parameters[self::SELECT];

        $starSuffix = $platform->getIdentifierSeparator() . self::SQL_STAR;
        foreach ($selectParameters[0] as $i => $columnParameters) {
            if ($columnParameters[0] == self::SQL_STAR
                || (isset($columnParameters[1]) && $columnParameters[1] == self::SQL_STAR)
                || strpos($columnParameters[0], $starSuffix)
            ) {
                $selectParameters[0] = array(array(self::SQL_STAR));
                break;
            }

            if (isset($columnParameters[1])) {
                array_shift($columnParameters);
                $selectParameters[0][$i] = $columnParameters;
            }
        }

        // first, produce column list without compound names (using the AS portion only)
        array_unshift($sqls, $this->createSqlFromSpecificationAndParameters(
            array('SELECT %1$s FROM (' => current($this->specifications[self::SELECT])),
            $selectParameters
        ));

        if (preg_match('/DISTINCT/i', $sqls[0])) {
            $this->setIsSelectContainDistinct(true);
        }

        if ($parameterContainer) {
            // create bottom part of query, with offset and limit using row_number
            $limitParamName        = $driver->formatParameterName('limit');
            $offsetParamName       = $driver->formatParameterName('offset');

            array_push($sqls, sprintf(
                ") AS ZEND_IBMDB2_SERVER_LIMIT_OFFSET_EMULATION WHERE ZEND_IBMDB2_SERVER_LIMIT_OFFSET_EMULATION.ZEND_DB_ROWNUM BETWEEN %s AND %s",
                $offsetParamName,
                $limitParamName
            ));

            if ((int) $this->offset > 0) {
                $parameterContainer->offsetSet('offset', (int) $this->offset + 1);
            } else {
                $parameterContainer->offsetSet('offset', (int) $this->offset);
            }

            $parameterContainer->offsetSet('limit', (int) $this->limit + (int) $this->offset);
        } else {
            if ((int) $this->offset > 0) {
                $offset = (int) $this->offset + 1;
            } else {
                $offset = (int) $this->offset;
            }

            array_push($sqls, sprintf(
                ") AS ZEND_IBMDB2_SERVER_LIMIT_OFFSET_EMULATION WHERE ZEND_IBMDB2_SERVER_LIMIT_OFFSET_EMULATION.ZEND_DB_ROWNUM BETWEEN %d AND %d",
                $offset,
                (int) $this->limit + (int) $this->offset
            ));
        }

        if (isset($sqls[self::ORDER])) {
            $orderBy = $sqls[self::ORDER];
            unset($sqls[self::ORDER]);
        } else {
            $orderBy = '';
        }

        // add a column for row_number() using the order specification //dense_rank()
        if ($this->getIsSelectContainDistinct()) {
            $parameters[self::SELECT][0][] = array('DENSE_RANK() OVER (' . $orderBy . ')', 'ZEND_DB_ROWNUM');
        } else {
            $parameters[self::SELECT][0][] = array('ROW_NUMBER() OVER (' . $orderBy . ')', 'ZEND_DB_ROWNUM');
        }

        $sqls[self::SELECT] = $this->createSqlFromSpecificationAndParameters(
            $this->specifications[self::SELECT],
            $parameters[self::SELECT]
        );
    }
}
