<?php

namespace Nodeart\BuilderBundle\Services;

class ObjectsQueriesRAMStorage
{
    private $objectsCache = [];

    public function add(array $objectStruct): array
    {
        $objectNodeId = $this->getStructObjectId($objectStruct);
        if (!isset($this->objectsCache[$objectNodeId])) {
            $this->objectsCache[$objectNodeId] = $objectStruct;
        }
        return $objectStruct;
    }

    private function getStructObjectId(array $struct): int
    {
        return $struct['object']->getId();
    }

    public function get(int $objectNodeId): array
    {
        return $this->objectsCache[$objectNodeId];
    }

    public function remove(array $struct)
    {

    }

    public function isStored(int $objectNodeId, callable $compareFunction = null): bool
    {
        return !is_null($compareFunction) ? $compareFunction($this->objectsCache, $objectNodeId) : isset($this->objectsCache[$objectNodeId]);
    }
}