<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Adapter;

use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DataTableState;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * AbstractAdapter.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /** @var PropertyAccessor */
    protected $accessor;

    /**
     * AbstractAdapter constructor.
     */
    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    final public function getData(DataTableState $state): ResultSetInterface
    {
        $query = new AdapterQuery($state);
        $propertyMap = [];

        $this->prepareQuery($query);
        foreach ($state->getDataTable()->getColumns() as $column) {
            $field = $column->getField();
            $propertyMap[] = [$column, empty($field) ? null : $this->mapPropertyPath($query, $column)];
        }
        $rows = [];
        $transformer = $state->getDataTable()->getTransformer();
        $identifier = $query->getIdentifierPropertyPath();
        foreach ($this->getResults($query) as $result) {
            $row = [];
            if ($identifier) {
                $row['DT_RowId'] = $this->accessor->getValue($result, $identifier);
            }

            /** @var AbstractColumn $column */
            foreach ($propertyMap as list($column, $mapping)) {
                $value = ($mapping && $this->accessor->isReadable($result, $mapping)) ? $this->accessor->getValue($result, $mapping) : null;
                $row[$column->getName()] = $column->transform($value, $result);
            }
            if ($transformer) {
                $row = call_user_func($transformer, $row, $result);
            }
            $rows[] = $row;
        }

        return new ArrayResultSet($rows, $query->getTotalRows(), $query->getFilteredRows());
    }

    /**
     * @param AdapterQuery $query
     */
    abstract protected function prepareQuery(AdapterQuery $query);

    /**
     * @param AdapterQuery $query
     * @param AbstractColumn $column
     * @return string|null
     */
    abstract protected function mapPropertyPath(AdapterQuery $query, AbstractColumn $column);

    /**
     * @param AdapterQuery $query
     * @return \Traversable
     */
    abstract protected function getResults(AdapterQuery $query): \Traversable;
}
