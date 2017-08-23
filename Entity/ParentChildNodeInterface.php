<?php

namespace Nodeart\BuilderBundle\Entity;

interface ParentChildNodeInterface {

	/**
	 * @return int
	 */
	public function getId(): int;

	public function getChilds();

	public function setChilds( $child );

	public function addChild( $child );

	public function removeChild( $child );

	public function getParentObjects();

	public function setParents( $parent );

	public function addParent( $parent );

	public function removeParent( $parent );

}