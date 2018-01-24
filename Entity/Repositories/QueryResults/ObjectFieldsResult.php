<?php

namespace Nodeart\BuilderBundle\Entity\Repositories\QueryResults;

use GraphAware\Neo4j\OGM\Annotations as OGM;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;

/**
 * @OGM\QueryResult()
 */
class ObjectFieldResult
{
    /**
     * @OGM\MappedResult(type="ENTITY", target="FieldValueNode")
     */
    protected $value;

    /**
     * @OGM\MappedResult(type="ENTITY", target="TypeFieldNode")
     */
    protected $type;

    /**
     * @return FieldValueNode
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return TypeFieldNode
     */
    public function getType()
    {
        return $this->type;
    }
}
