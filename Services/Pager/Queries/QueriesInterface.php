<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 25.05.2017
 * Time: 12:51
 */

namespace Nodeart\BuilderBundle\Services\Pager\Queries;

use GraphAware\Neo4j\OGM\Query;

interface QueriesInterface
{

    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    public function createCountQuery();

    public function getCountQuery(): Query;

    public function createQuery($limit, $skip);

    public function getQuery(): Query;

    public function processOrder(array $getParams);

    /**
     * @return array
     */
    public function getOrder(): array;

    /**
     * @return array
     */
    public function getFilters(): array;
}