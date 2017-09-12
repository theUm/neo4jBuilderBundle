<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 25.05.2017
 * Time: 12:51
 */

namespace Nodeart\BuilderBundle\Services\Pager\Queries;

use GraphAware\Neo4j\OGM\Query;

interface QueriesInterface {

	public function createCountQuery();

	public function getCountQuery(): Query;

	public function createQuery( $limit, $skip );

	public function getQuery(): Query;

	public function setParams( array $array );

}