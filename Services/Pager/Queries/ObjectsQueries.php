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
use Nodeart\BuilderBundle\Services\ObjectSearchQueryService\ObjectSearchQuery;

class ObjectsQueries implements QueriesInterface
{

    /**
     * @var Query
     */
    protected $countQuery;
    /**
     * @var ObjectSearchQuery
     */
    private $objectSearchQueryService;
    /**
     * @var Query
     */
    private $query;

    private $order = [];
    private $filters = [];

    private $limit = 20;
    private $skip = 0;

    public function __construct(ObjectSearchQuery $objectSearchQueryService)
    {
        $this->objectSearchQueryService = $objectSearchQueryService;
    }

    public function getObjectSearchQueryService(): ObjectSearchQuery
    {
        return $this->objectSearchQueryService;
    }

    public function createCountQuery()
    {
        $this->countQuery = $this->objectSearchQueryService->getCountQuery();
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getCountQuery(): Query
    {
        return $this->countQuery;
    }

    /**
     * Returns structure of objects and their fields
     *
     * @param $limit
     * @param $skip
     */
    public function createQuery($limit, $skip)
    {
        $this->limit = $limit;
        $this->skip = $skip;
        $this->objectSearchQueryService->addSkip(intval($skip));
        $this->objectSearchQueryService->addLimit(intval($limit));
        $this->query = $this->objectSearchQueryService->getQuery();
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function processOrder(array $getParams)
    {
    }

    /**
     * @return array
     */
    public function getOrder(): array
    {
        return $this->order;
    }
}