<?php

namespace BuilderBundle\Twig;

use BuilderBundle\Entity\EntityTypeNode;

class EntityRelationsSegregator extends \Twig_Extension {

	public function getFunctions() {
		return [
			new \Twig_SimpleFunction( 'getChilds', [ $this, 'getChilds' ] ),
		];
	}

	public function getChilds( EntityTypeNode $entityType ) {
		$segregatedArray = [
			'root' => [],
			'data' => []
		];

		/** @var EntityTypeNode $etChild */
		foreach ( $entityType->getChildTypes()->getIterator() as $etChild ) {
			if ( $etChild->isDataType() ) {
				$segregatedArray['data'][] = $etChild;
			} else {
				$segregatedArray['root'][] = $etChild;
			}
		}

		return $segregatedArray;
	}
}