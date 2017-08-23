<?php

namespace BuilderBundle\Services;

use GraphAware\Common\Result\Record;
use GraphAware\Neo4j\OGM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Yaml\Yaml;

class SearchService {
	private $container;
	/** @var EntityManager $nm */
	private $nm;
	private $mapping;

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->nm        = $container->get( 'neo.app.manager' );
		$this->mapping   = Yaml::parse( file_get_contents( $this->container->get( 'kernel' )->getRootDir() . '/config/node_types.yml' ) );
	}

	/**
	 * @param $label
	 * @param $parentAttrValue
	 * @param $value
	 *
	 * @return array
	 */
	public function search( $label, $parentAttrValue, $value = null ) {
		if ( ! in_array( $label, array_keys( $this->getMapping() ) ) ) {
			return [];
		} else {

			if ( ! is_null( $value ) ) {
				$records = $this->runChildValuesSearch( $label, $parentAttrValue, $value );
			} else {
				$records = $this->runSearch( $label, $parentAttrValue );
			}

			$foundData = [];
			/** @var Record $record */
			foreach ( $records as $record ) {
				$foundData[] = [
					'name'  => $record->get( 'name' ),
					'value' => ( empty( $parentAttrValue ) ) ? $record->get( 'id' ) : $record->get( 'name' ),
//                    'text' => $record->get('name')
				];
			}

			return [ 'success' => true, 'results' => $foundData ];
		}
	}

	private function getMapping() {
		return $this->mapping;
	}

	/**
	 * @todo: notice that WHERE part. neo4j cant regex with numbers
	 *
	 * @param string $parentLabel
	 * @param string $parentAttrValue
	 * @param $value
	 *
	 * @return Record[]
	 */
	private function runChildValuesSearch( string $parentLabel, string $parentAttrValue, $value ) {
		$parentAttr = $this->getMapping()[ $parentLabel ]['valAttr'];
		$childAttr  = $this->getMapping()['FieldValue']['valAttr'];

		return $this->getNM()->getDatabaseDriver()->run(
			'MATCH (n:' . $parentLabel . ' {' . $parentAttr . ':{parentAttrValue}})<-[:is_value_of]-(fv:FieldValue)
             WHERE (fv.' . $childAttr . '+\'\') =~ {fieldValue} RETURN fv.' . $childAttr . ' as name, id(fv) as id, n.slug as slug',
			[
				'parentAttrValue' => $parentAttrValue,
				'fieldValue'      => '.*' . preg_quote( $value ) . '.*'
			]
		)->records();
	}

	private function getNM() {
		return $this->nm;
	}

	/**
	 * @param string $label
	 * @param $value
	 *
	 * @return \GraphAware\Common\Result\Record[]
	 */
	private function runSearch( string $label, $value ) {
		$field = $this->getMapping()[ $label ]['valAttr'];

		return $this->getNM()->getDatabaseDriver()->run(
			'MATCH (n:' . $label . ') WHERE n.' . $field . ' =~ {like} RETURN n.' . $field . ' as name, id(n) as id, n.slug as slug',
			[ 'like' => '.*' . preg_quote( $value ) . '.*' ]
		)->records();
	}

}