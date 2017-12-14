<?php

namespace Nodeart\BuilderBundle\Services\ObjectSearchQueryService;

use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Query;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;

class ObjectSearchQuery
{

    const DEFAULT_PAGE_LIMIT = 20;

    private $query;

    private $baseFiltersCount = 0;

    private $baseWhere = '';
    private $objectFilter = '';
    private $etFilter = '';
    private $valuesFilter = '';
    private $parentChildLinks = '';
    private $parentChildFilter = '';
    private $skip = 0;
    private $limit = self::DEFAULT_PAGE_LIMIT;
    private $baseOrder = '';

    public function __construct(EntityManager $entityManager)
    {
        $this->query = $entityManager->createQuery();
    }

    public function execute()
    {
        return $this->getQuery()->getResult();
    }

    public function getQuery()
    {
        $this->prepareMainQuery();
        return $this->query;
    }

    private function prepareMainQuery()
    {
        $baseWhere = $this->baseWhere;
        $skip = 'skip ' . $this->skip;
        $limit = 'limit ' . $this->limit;
        $parentChildLinks = $this->parentChildLinks;
        $objectFilter = $this->objectFilter;
        $etFilter = $this->etFilter;
        $valuesFilter = $this->valuesFilter;
        $parentChildFilter = $this->parentChildFilter;
        $baseOrder = $this->baseOrder;

        $cql = "MATCH (type:EntityType)<-[:has_type]-(o:Object)$parentChildLinks $baseWhere $objectFilter $etFilter $parentChildFilter
                WITH o $baseOrder $skip $limit $valuesFilter
        	    OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf:EntityTypeField)-[:has_field]-(type:EntityType)-[:has_type]-(o)
        	    OPTIONAL MATCH (childEtf:EntityTypeField)-[:has_field]->(childEt:EntityType)<-[:has_type]-(childO:Object)
                WITH etf, o, collect(DISTINCT fv) as val ORDER BY o.createdAt
                RETURN o as object, 
                    CASE WHEN etf IS NULL THEN [] ELSE collect({etfSlug:etf.slug, valsByFields:{fieldType:etf, val:val}}) END as objectFields";
        $this->query->setCQL($cql);

        $this->query->addEntityMapping('object', ObjectNode::class);
        $this->query->addEntityMapping('fieldType', TypeFieldNode::class);
        $this->query->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $this->query->addEntityMapping('valsByFields', null, Query::HYDRATE_MAP);
        $this->query->addEntityMapping('objectFields', null, Query::HYDRATE_MAP_COLLECTION);
        $this->query->addEntityMapping('objects', null, Query::HYDRATE_MAP_COLLECTION);
    }

    public function executeCount()
    {
        return $this->getCountQuery()->getOneResult();
    }

    public function getCountQuery()
    {
        $this->prepareSearchQuery();
        return $this->query;
    }

    private function prepareSearchQuery()
    {
        $baseWhere = $this->baseWhere;
        $parentChildLinks = $this->parentChildLinks;
        $objectFilter = $this->objectFilter;
        $etFilter = $this->etFilter;
        $valuesFilter = $this->valuesFilter;
        $parentChildFilter = $this->parentChildFilter;

        $cql = "MATCH (type:EntityType)<-[:has_type]-(o:Object)$parentChildLinks $baseWhere $objectFilter $etFilter $parentChildFilter
                WITH o $valuesFilter
                RETURN count(o) as count";

        $this->query->setCQL($cql);
    }

    public function addObjectFilters($params = [])
    {
        if (!empty($params)) {
            if ($this->baseFiltersCount < 1) {
                $this->baseWhere = 'WHERE ';
                $this->objectFilter = $params['cql'];
            } else {
                $this->objectFilter = ' AND ' . $params['cql'];
            }

            foreach ($params['params'] as $paramPair) {
                $this->query->setParameter($paramPair['name'], $paramPair['values']);
            }
            $this->baseFiltersCount++;
        }
        return $this;
    }

    public function addETFilters($params = [])
    {
        if (!empty($params)) {
            if ($this->baseFiltersCount < 1) {
                $this->baseWhere = 'WHERE ';
                $this->etFilter = $params['cql'];
            } else {
                $this->etFilter = ' AND ' . $params['cql'];
            }
            foreach ($params['params'] as $paramPair) {
                $this->query->setParameter($paramPair['name'], $paramPair['values']);
            }
            $this->baseFiltersCount++;
        }
        return $this;
    }

    public function addValuesFilter($params = [])
    {
        if (!empty($params)) {
            $this->valuesFilter = ' MATCH (o)<-[:is_field_of]-(filterFV:FieldValue)-[:is_value_of]->(filterETF:EntityTypeField) WHERE ' . $params['cql'];
            foreach ($params['params'] as $paramPair) {
                $this->query->setParameter($paramPair['name'], $paramPair['values']);
            }
        }
        return $this;
    }

    public function addParentChildRelations($params = [], $filterByParent = true)
    {
        if (!empty($params)) {
            $this->parentChildLinks = ($filterByParent) ?
                '-[:is_child_of]->(refObject:Object)' :
                '<-[:is_child_of]-(refObject:Object)';

            if ($this->baseFiltersCount < 1) {
                $this->baseWhere = 'WHERE ';
                $this->parentChildFilter = $params['cql'];
            } else {
                $this->parentChildFilter = ' AND ' . $params['cql'];
            }

            foreach ($params['params'] as $paramPair) {
                $this->query->setParameter($paramPair['name'], $paramPair['values']);
            }
            $this->baseFiltersCount++;
        }
        return $this;
    }

    public function addSkip(int $skip)
    {
        $this->skip = $skip;
        return $this;
    }

    public function addLimit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function addBaseOrder(string $cql)
    {
        if (!empty($cql)) {
            $this->baseOrder = ' ORDER BY ' . $cql;
        }
        return $this;
    }
}