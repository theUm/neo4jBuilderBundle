<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Repository\BaseRepository as BaseSymfonyRepository;

abstract class BaseRepository extends BaseSymfonyRepository
{
    const LINK_CHILDS = 0;
    const LINK_TO_PARENTS = 1;

    /**
     * @var Client
     */
    private $db;

    public function __construct($classMetadata, EntityManager $manager, $className)
    {
        parent::__construct($classMetadata, $manager, $className);
        $this->db = $this->entityManager->getDatabaseDriver();
    }

    public function countBy($params = [])
    {
        return count($this->findBy($params));
    }

    final function prepareUpdateQuery($data, $prefix = 'etf')
    {
        $queryPart = ['params' => [], 'values' => []];
        unset($data['id']);
        foreach ($data as $key => $field) {
            // @todo: look at it closely
            //warning!
            //if (!is_null($field)) {
            $queryPart['params'][] = $prefix . '.' . $key . ' = {' . $key . '}';
            $queryPart['values'][$key] = (is_array($field) && empty($field)) ? null : $field;
            //}
        }
        $queryPart['params'] = join(',', $queryPart['params']);

        return $queryPart;
    }

    /**
     * @todo: REFACTOR IT! THIS IS DISGUSTING!
     *
     * @param $data
     *
     * @return string
     */
    final function prepareCreateQuery($data)
    {
        $queryPart = [];
        unset($data['id']);
        foreach ($data as $key => $value) {
            $queryPart[] = $key . ':' . $this->transformValue($value);
        }

        return join(', ', $queryPart);
    }

    private function transformValue($value)
    {
        if (is_null($value)) {
            return '""';
        } else {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public final function updateParenthesisRelationsByIds($nodeId, array $currentNodeIds, array $previousNodeIds, $linkChilds = self::LINK_CHILDS)
    {
        $currentNodeIds = array_unique($currentNodeIds);
        $nodeIdsToDelete = array_diff($previousNodeIds, $currentNodeIds);
        $nodeIdsToLink = array_diff($currentNodeIds, $previousNodeIds);

        if (count($nodeIdsToLink) > 0) {
            $this->getDb()->run(
                $this->getCreateRelationsQuery($linkChilds),
                [
                    'id' => $nodeId,
                    'id_add' => $nodeIdsToLink
                ]);
        }

        if (count($nodeIdsToDelete) > 0) {
            $this->getDb()->run(
                $this->getDeleteRelationsQuery($linkChilds),
                [
                    'id' => $nodeId,
                    'id_rem' => $nodeIdsToDelete
                ]);
        }
    }

    /**
     * @return Client
     */
    protected function getDb(): Client
    {
        return $this->db;
    }

    abstract protected function getCreateRelationsQuery(bool $isChildsLink): string;

    abstract protected function getDeleteRelationsQuery(bool $isChildsLink): string;

    /**
     * @param $isChildsLink
     *
     * @return array
     */
    protected final function getQueryDirections($isChildsLink)
    {
        if ($isChildsLink) {
            $start = '';
            $finish = '>';
        } else {
            $start = '<';
            $finish = '';
        }

        return ['leftPart' => $start, 'rightPart' => $finish];
    }
}