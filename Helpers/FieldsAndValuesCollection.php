<?php

namespace Nodeart\BuilderBundle\Helpers;

use ArrayIterator;
use IteratorAggregate;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Traversable;

/**
 * Implements structure for storing FieldType and FieldValue nodes
 *
 * $container[$fieldTypeId]= FieldAndValueObj{FieldTypeObj,ValueObj}
 *
 * Class FieldsAndValuesContainer
 * @package Nodeart\BuilderBundle\Helpers
 */
class FieldsAndValuesCollection implements IteratorAggregate {
	private $container = [];

	/**
	 * Add Value of field to collection or create new type and set value
	 *
	 * @param TypeFieldNode $typeNode
	 * @param FieldValueNode $value
	 */
	public function addFieldValue( TypeFieldNode $typeNode, FieldValueNode $value ) {
		if ( isset( $this->container[ $typeNode->getId() ] ) ) {
			/** @var FieldAndValue $pair */
			$pair = $this->container[ $typeNode->getId() ];
			$pair->addVal( $value );
			$this->container[ $typeNode->getId() ] = $pair;
		} else {
			$this->addField( $typeNode, $value );
		}
	}

	/**
	 * Adds a field to collection. Field will be replaced if already in collection
	 *
	 * @param TypeFieldNode $type
	 * @param FieldValueNode|null $value
	 */
	public function addField( TypeFieldNode $type, FieldValueNode $value = null ) {
		$this->container[ $type->getSlug() ] = new FieldAndValue( $type, $value );
	}

	/**
	 * @param int $id
	 *
	 * @return FieldAndValue|null
	 */
	public function getPair( int $id ) {
		if ( isset( $this->container[ $id ] ) ) {
			return $this->container[ $id ];
		}

		return null;
	}


	/**
	 * Retrieve an external iterator
	 * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @return Traversable An instance of an object implementing <b>Iterator</b> or
	 * <b>Traversable</b>
	 * @since 5.0.0
	 */
	public function getIterator() {
		return new ArrayIterator( $this->container );
	}
}