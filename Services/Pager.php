<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 25.05.2017
 * Time: 12:51
 */

namespace BuilderBundle\Services;

use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Query;
use Symfony\Component\HttpFoundation\RequestStack;

class Pager {
	const FILTER_MAIN_SEPARATOR = '+';
	const FILTER_SUB_SEPARATOR = '-';
	const FILTER_SHOW_ALL = 'all';
	//page buttons on screen <first ... range($buttons) ... last>
	public $request;

	//constructor
	public $count = 0;
	private $range = 3;

	//from request
	private $nam;
	private $page = 1;
	private $limit = 8;

	//set from controller
	private $filters = [];
	private $queryString;
	private $baseFilters;
	private $returnParam;
	private $orderBy = '';

	//calculated inside this class
	private $returnParamClass;
	private $filterString = '';
	private $limitPart;
	private $offsetPart;
	private $filterVals = [];
	private $hasWhere = false;

	//fetched from db with count query
	private $totalPages = 1;

	public function __construct( EntityManager $nam, RequestStack $request ) {
		$this->nam     = $nam;
		$this->request = $request->getCurrentRequest();

		$page        = intval( $this->request->get( 'page' ) );
		$limit       = intval( $this->request->get( 'perPage' ) );
		$this->page  = ( $page > 0 ) ? $page : $this->page;
		$this->limit = ( $limit > 0 ) ? $limit : $this->limit;
	}

	/**
	 * @return string
	 */
	public function getReturnParam(): string {
		return $this->returnParam;
	}

	/**
	 * @param string $returnParam
	 * @param string $returnParamClass
	 */
	public function setReturnMapping( string $returnParam, string $returnParamClass ) {
		$this->returnParam      = $returnParam;
		$this->returnParamClass = $returnParamClass;
	}

	/**
	 * @return int
	 */
	public function getTotalPages(): int {
		return $this->totalPages;
	}

	public function paginate() {
		$this->initPager();
		foreach ( $this->exec() as $node ) {
			yield $node;
		}

		return;
	}

	private function initPager() {
		if ( $this->count == null ) {
			$this->filtersFromURL();
			$this->execCount();
		}
	}

	public function filtersFromURL() {
		$filtersArray = [];
		$filters      = explode( self::FILTER_MAIN_SEPARATOR, $this->request->get( 'filters' ) );
		if ( is_array( $filters ) ) {
			foreach ( $filters as $filter ) {
				$currentFilter = explode( self::FILTER_SUB_SEPARATOR, $filter );
				if ( is_array( $currentFilter ) && ( count( $currentFilter ) >= 1 ) ) {
					$filtersArray[ array_shift( $currentFilter ) ] = array_unique( $currentFilter );
				}
			}
		}
		$this->filters = $filtersArray;
		$this->prepareFilters();
	}

	/**
	 * Builds query string part responsible for filtering. Currently takes just one filter at time - first one from url
	 */
	public function prepareFilters() {
		if ( is_array( $this->filters ) && ! empty( $this->filters ) ) {
			$filterVals = reset( $this->filters );
			$filterName = key( $this->filters );
			switch ( $filterName ) {
				case 'free': {
					$this->filterString .= ' AND NOT (fv)--(:EntityTypeField)';
					break;
				}

				case 'ftid': {
					$filterVals = array_map( 'intval', array_filter( $filterVals, 'is_numeric' ) );
					// update filters
					$this->setQueryString( $this->getQueryString() . '--(etf:EntityTypeField)' );
					$this->filters[ $filterName ]    = $filterVals;
					$this->filterString              .= ' AND id(etf) IN {ftid}';
					$this->filterVals[ $filterName ] = $filterVals;
					break;
				}
			}

		}
	}

	/**
	 * @return string
	 */
	public function getQueryString() {
		return $this->queryString;
	}

	/**
	 * @param string $query
	 */
	public function setQueryString( string $query ) {
		$this->queryString = $query;
	}

	private function execCount() {
		$query = $this->nam->createQuery( $this->getQueryString() . $this->getBaseFilters() . $this->getFilterString() . ' RETURN COUNT (' . $this->returnParam . ') as count' );
		$this->setQueryFilterParams( $query );

		$this->count = $query->getOneResult()['count'];

		if ( $this->count > 0 ) {
			$this->totalPages = intval( ceil( $this->count / $this->limit ) );

			$offset           = abs( $this->page - 1 ) * $this->limit;
			$this->offsetPart = ( $offset > 0 ) ? ( ' SKIP ' . $offset ) : '';

			$this->limitPart = ' LIMIT ' . $this->limit;
		}
	}

	/**
	 * @return string
	 */
	public function getBaseFilters() {
		return $this->baseFilters;
	}

	/**
	 * @param string $baseFilters
	 */
	public function setBaseFilters( string $baseFilters ) {
		if ( strpos( mb_strtolower( $this->baseFilters ), 'where' ) ) {
			$this->hasWhere = true;
		}
		$this->baseFilters = $baseFilters;
	}

	/**
	 * @return mixed
	 */
	public function getFilterString() {
		return $this->filterString;
	}

	private function setQueryFilterParams( Query $query ) {
		foreach ( $this->filterVals as $filterValName => $filterVals ) {
			$query->setParameter( $filterValName, $filterVals );
		}
	}

	private function exec() {
		$query = $this->nam->createQuery( $this->getQueryString() . $this->getBaseFilters() . $this->getFilterString() . ' RETURN ' . $this->returnParam . $this->orderBy . $this->offsetPart . $this->limitPart );
		$this->setQueryFilterParams( $query );
		$query->addEntityMapping( $this->returnParam, $this->returnParamClass );

		return $query->getResult();
	}

	/**
	 * this function was snitched from KnpLabs/knp-components .../SlidingPagination.php ->getPaginationData()
	 * @return array
	 */
	public function getPaginationData() {
		$this->initPager();
		if ( $this->range > $this->totalPages ) {
			$this->range = $this->totalPages;
		}
		$delta = intval( ceil( $this->range / 2 ) );
		if ( $this->page - $delta > $this->totalPages - $this->range ) {
			$pages = range( $this->totalPages - $this->range + 1, $this->totalPages );
		} else {
			if ( $this->page - $delta < 0 ) {
				$delta = $this->page;
			}
			$offset = $this->page - $delta;
			$pages  = range( $offset + 1, $offset + $this->range );
		}
		$data['pages'] = $pages;

		$current       = $this->page;
		$filtersString = $this->filterstoURL();
		$viewData      = [
			'last'          => $this->totalPages,
			'current'       => $current,
			'limit'         => $this->limit,
			'first'         => 1,
			'pageCount'     => $this->totalPages,
			'totalCount'    => $this->count,
			'filtersString' => empty( $filtersString ) ? self::FILTER_SHOW_ALL : $filtersString
		];
		//$viewData = [];//array_merge($viewData, $this->paginatorOptions, $this->customParameters);
		if ( $current - 1 > 0 ) {
			$viewData['previous'] = $current - 1;
		}
		if ( $current + 1 <= $this->totalPages ) {
			$viewData['next'] = $current + 1;
		}
		$viewData['pagesInRange']     = $pages;
		$viewData['firstPageInRange'] = min( $pages );
		$viewData['lastPageInRange']  = max( $pages );

		return $viewData;
	}

	private function filterstoURL() {
		$collapsedValues = [];
		foreach ( $this->filters as $filterName => $filterVals ) {
			$collapsedValues[ $filterName ][] = $filterName;
			if ( is_array( $filterVals ) ) {
				$collapsedValues[ $filterName ] = array_merge( $collapsedValues[ $filterName ], $filterVals );
			} else {
				$collapsedValues[ $filterName ][] = $filterVals;
			}
			$collapsedValues[ $filterName ] = join( self::FILTER_SUB_SEPARATOR, $collapsedValues[ $filterName ] );

		}

		return join( self::FILTER_MAIN_SEPARATOR, $collapsedValues );
	}

	/**
	 * @param string $orderBy
	 */
	public function setOrderBy( string $orderBy ) {
		$this->orderBy = $orderBy;
	}

}