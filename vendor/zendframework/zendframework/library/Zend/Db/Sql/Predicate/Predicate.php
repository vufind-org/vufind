<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Sql\Predicate;

use Zend\Db\Sql\Exception\RuntimeException;

/**
 * @property Predicate $and
 * @property Predicate $or
 * @property Predicate $AND
 * @property Predicate $OR
 * @property Predicate $NEST
 * @property Predicate $UNNEST
 */
class Predicate extends PredicateSet
{
    protected $unnest = null;
    protected $nextPredicateCombineOperator = null;

    /**
     * Begin nesting predicates
     *
     * @return Predicate
     */
    public function nest()
    {
        $predicateSet = new Predicate();
        $predicateSet->setUnnest($this);
        $this->addPredicate($predicateSet, ($this->nextPredicateCombineOperator) ?: $this->defaultCombination);
        $this->nextPredicateCombineOperator = null;
        return $predicateSet;
    }

    /**
     * Indicate what predicate will be unnested
     *
     * @param  Predicate $predicate
     * @return void
     */
    public function setUnnest(Predicate $predicate)
    {
        $this->unnest = $predicate;
    }

    /**
     * Indicate end of nested predicate
     *
     * @return Predicate
     * @throws RuntimeException
     */
    public function unnest()
    {
        if ($this->unnest == null) {
            throw new RuntimeException('Not nested');
        }
        $unnset       = $this->unnest;
        $this->unnest = null;
        return $unnset;
    }

    /**
     * Create "Equal To" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function equalTo($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_EQUAL_TO, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Not Equal To" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function notEqualTo($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_NOT_EQUAL_TO, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Less Than" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function lessThan($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_LESS_THAN, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Greater Than" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function greaterThan($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_GREATER_THAN, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Less Than Or Equal To" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function lessThanOrEqualTo($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_LESS_THAN_OR_EQUAL_TO, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Greater Than Or Equal To" predicate
     *
     * Utilizes Operator predicate
     *
     * @param  int|float|bool|string $left
     * @param  int|float|bool|string $right
     * @param  string $leftType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_IDENTIFIER {@see allowedTypes}
     * @param  string $rightType TYPE_IDENTIFIER or TYPE_VALUE by default TYPE_VALUE {@see allowedTypes}
     * @return Predicate
     */
    public function greaterThanOrEqualTo($left, $right, $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE)
    {
        $this->addPredicate(
            new Operator($left, Operator::OPERATOR_GREATER_THAN_OR_EQUAL_TO, $right, $leftType, $rightType),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Like" predicate
     *
     * Utilizes Like predicate
     *
     * @param  string $identifier
     * @param  string $like
     * @return Predicate
     */
    public function like($identifier, $like)
    {
        $this->addPredicate(
            new Like($identifier, $like),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create an expression, with parameter placeholders
     *
     * @param $expression
     * @param $parameters
     * @return $this
     */
    public function expression($expression, $parameters)
    {
        $this->addPredicate(
            new Expression($expression, $parameters),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "Literal" predicate
     *
     * Literal predicate, for parameters, use expression()
     *
     * @param  string $literal
     * @return Predicate
     */
    public function literal($literal)
    {
        // process deprecated parameters from previous literal($literal, $parameters = null) signature
        if (func_num_args() >= 2) {
            $parameters = func_get_arg(1);
            $predicate = new Expression($literal, $parameters);
        }

        // normal workflow for "Literals" here
        if (!isset($predicate)) {
            $predicate = new Literal($literal);
        }

        $this->addPredicate(
            $predicate,
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "IS NULL" predicate
     *
     * Utilizes IsNull predicate
     *
     * @param  string $identifier
     * @return Predicate
     */
    public function isNull($identifier)
    {
        $this->addPredicate(
            new IsNull($identifier),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "IS NOT NULL" predicate
     *
     * Utilizes IsNotNull predicate
     *
     * @param  string $identifier
     * @return Predicate
     */
    public function isNotNull($identifier)
    {
        $this->addPredicate(
            new IsNotNull($identifier),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "in" predicate
     *
     * Utilizes In predicate
     *
     * @param  string $identifier
     * @param  array|\Zend\Db\Sql\Select $valueSet
     * @return Predicate
     */
    public function in($identifier, $valueSet = null)
    {
        $this->addPredicate(
            new In($identifier, $valueSet),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Create "between" predicate
     *
     * Utilizes Between predicate
     *
     * @param  string $identifier
     * @param  int|float|string $minValue
     * @param  int|float|string $maxValue
     * @return Predicate
     */
    public function between($identifier, $minValue, $maxValue)
    {
        $this->addPredicate(
            new Between($identifier, $minValue, $maxValue),
            ($this->nextPredicateCombineOperator) ?: $this->defaultCombination
        );
        $this->nextPredicateCombineOperator = null;

        return $this;
    }

    /**
     * Overloading
     *
     * Overloads "or", "and", "nest", and "unnest"
     *
     * @param  string $name
     * @return Predicate
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'or':
                $this->nextPredicateCombineOperator = self::OP_OR;
                break;
            case 'and':
                $this->nextPredicateCombineOperator = self::OP_AND;
                break;
            case 'nest':
                return $this->nest();
            case 'unnest':
                return $this->unnest();
        }
        return $this;
    }
}
