<?php

namespace BuilderBundle\Entity\Repositories\QueryResults;

use BuilderBundle\Entity\FieldValueNode;
use BuilderBundle\Entity\TypeFieldNode;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\QueryResult()
 */
class ObjectFieldResult {
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
	public function getValue() {
		return $this->value;
	}

	/**
	 * @return TypeFieldNode
	 */
	public function getType() {
		return $this->type;
	}
}
