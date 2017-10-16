<?php

namespace Nodeart\BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="FieldValue", repository="Nodeart\BuilderBundle\Entity\Repositories\FieldValueNodeRepository")
 */
class FieldValueNode {
	use FileFieldTrait;

	/**
	 * @OGM\GraphId()
	 * @var int
	 */
	protected $id;

	/**
	 * @OGM\Property(type="string", key="data", nullable=true)
	 * @var string
	 */
	protected $data = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $dataLabel = null;

	/**
	 * @OGM\Relationship(type="is_value_of", direction="OUTGOING", targetEntity="TypeFieldNode", collection=false)
	 * @var TypeFieldNode
	 */
	protected $typeField;

	/**
	 * @OGM\Relationship(type="is_field_of", direction="OUTGOING", targetEntity="ObjectNode", collection=true)
	 * @var ArrayCollection|ObjectNode[]
	 */
	protected $objects;

	/**
	 * copy of fileTrait`s "createdAt" field,
	 * OGM\Convert does not work with traits at the moment
	 * @var \DateTime
	 *
	 * @OGM\Property(nullable=true)
	 * @OGM\Convert(type="datetime", options={})
	 */
	protected $createdAt;

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return TypeFieldNode
	 */
	public function getTypeField() {
		return $this->typeField;
	}

	/**
	 * @param TypeFieldNode $typeField
	 */
	public function setTypeField( TypeFieldNode $typeField ) {
		$this->typeField = $typeField;
	}

	/**
	 * @return ObjectNode[]|ArrayCollection
	 */
	public function getObjects() {
		return $this->objects;
	}

	/**
	 * @param ObjectNode[]|ArrayCollection $objects
	 */
	public function setObjects( $objects ) {
		$this->objects = $objects;
	}

	/**
	 * @param ObjectNode $object
	 */
	public function addObject( $object ) {
		if ( ! $this->objects->contains( $object ) ) {
			$this->objects->add( $object );
		}
	}

	/**
	 * @param ObjectNode $object
	 */
	public function removeObject( $object ) {
		$this->objects->removeElement( $object );
	}

	public function __toString() {
		return md5( $this->getData() ) . $this->getFileName();
	}

	/**
	 * @return string
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @param string $data
	 */
	public function setData( $data ) {
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getDataLabel(): ?string {
		return $this->dataLabel;
	}

	/**
	 * @param string $dataLabel
	 *
	 * @return FieldValueNode
	 */
	public function setDataLabel( ?string $dataLabel ): FieldValueNode {
		$this->dataLabel = $dataLabel;

		return $this;
	}
}