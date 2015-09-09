<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Sql\Ddl;

use Zend\Db\Adapter\Platform\PlatformInterface;
use Zend\Db\Sql\AbstractSql;

class CreateTable extends AbstractSql implements SqlInterface
{
    const COLUMNS     = 'columns';
    const CONSTRAINTS = 'constraints';
    const TABLE       = 'table';

    /**
     * @var Column\ColumnInterface[]
     */
    protected $columns = array();

    /**
     * @var string[]
     */
    protected $constraints = array();

    /**
     * @var bool
     */
    protected $isTemporary = false;

    /**
     * {@inheritDoc}
     */
    protected $specifications = array(
        self::TABLE => 'CREATE %1$sTABLE %2$s (',
        self::COLUMNS  => array(
            "\n    %1\$s" => array(
                array(1 => '%1$s', 'combinedby' => ",\n    ")
            )
        ),
        'combinedBy' => ",",
        self::CONSTRAINTS => array(
            "\n    %1\$s" => array(
                array(1 => '%1$s', 'combinedby' => ",\n    ")
            )
        ),
        'statementEnd' => '%1$s',
    );

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @param string $table
     * @param bool   $isTemporary
     */
    public function __construct($table = '', $isTemporary = false)
    {
        $this->table = $table;
        $this->setTemporary($isTemporary);
    }

    /**
     * @param  bool $temporary
     * @return self
     */
    public function setTemporary($temporary)
    {
        $this->isTemporary = (bool) $temporary;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTemporary()
    {
        return $this->isTemporary;
    }

    /**
     * @param  string $name
     * @return self
     */
    public function setTable($name)
    {
        $this->table = $name;
        return $this;
    }

    /**
     * @param  Column\ColumnInterface $column
     * @return self
     */
    public function addColumn(Column\ColumnInterface $column)
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * @param  Constraint\ConstraintInterface $constraint
     * @return self
     */
    public function addConstraint(Constraint\ConstraintInterface $constraint)
    {
        $this->constraints[] = $constraint;
        return $this;
    }

    /**
     * @param  string|null $key
     * @return array
     */
    public function getRawState($key = null)
    {
        $rawState = array(
            self::COLUMNS     => $this->columns,
            self::CONSTRAINTS => $this->constraints,
            self::TABLE       => $this->table,
        );

        return (isset($key) && array_key_exists($key, $rawState)) ? $rawState[$key] : $rawState;
    }

    /**
     * @param PlatformInterface $adapterPlatform
     *
     * @return string[]
     */
    protected function processTable(PlatformInterface $adapterPlatform = null)
    {
        return array(
            $this->isTemporary ? 'TEMPORARY ' : '',
            $adapterPlatform->quoteIdentifier($this->table),
        );
    }

    /**
     * @param PlatformInterface $adapterPlatform
     *
     * @return string[][]|null
     */
    protected function processColumns(PlatformInterface $adapterPlatform = null)
    {
        if (! $this->columns) {
            return;
        }

        $sqls = array();

        foreach ($this->columns as $column) {
            $sqls[] = $this->processExpression($column, $adapterPlatform);
        }

        return array($sqls);
    }

    /**
     * @param PlatformInterface $adapterPlatform
     *
     * @return array|string
     */
    protected function processCombinedby(PlatformInterface $adapterPlatform = null)
    {
        if ($this->constraints && $this->columns) {
            return $this->specifications['combinedBy'];
        }
    }

    /**
     * @param PlatformInterface $adapterPlatform
     *
     * @return string[][]|null
     */
    protected function processConstraints(PlatformInterface $adapterPlatform = null)
    {
        if (!$this->constraints) {
            return;
        }

        $sqls = array();

        foreach ($this->constraints as $constraint) {
            $sqls[] = $this->processExpression($constraint, $adapterPlatform);
        }

        return array($sqls);
    }

    /**
     * @param PlatformInterface $adapterPlatform
     *
     * @return string[]
     */
    protected function processStatementEnd(PlatformInterface $adapterPlatform = null)
    {
        return array("\n)");
    }
}
