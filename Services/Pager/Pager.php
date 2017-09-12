<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 25.05.2017
 * Time: 12:51
 */

namespace Nodeart\BuilderBundle\Services\Pager;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Services\Pager\Queries\QueriesInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Pager {
	public $request;

	//constructor
	public $count = 0;
	protected $range = 3;

	//from request
	protected $nam;
	protected $page = 1;
	protected $limit = 10;



	//fetched from db with count query
	protected $totalPages = 1;
	protected $offset;
	/**
	 * @var QueriesInterface
	 */
	private $queries;

	public function __construct( EntityManager $nam, RequestStack $request ) {
		$this->nam     = $nam;
		$this->request = $request->getCurrentRequest();

		// set page and limit form url if present
		$page        = intval( $this->request->get( 'page' ) );
		$limit       = intval( $this->request->get( 'perPage' ) );
		$this->page  = ( $page > 0 ) ? $page : $this->page;
		$this->limit = ( $limit > 0 ) ? $limit : $this->limit;
	}

	public function setLimit( int $limit ) {
		$this->limit = $limit;
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
			$this->queries->createCountQuery();
			$this->execCount();
		}
	}

	private function execCount() {

		$this->count = $this->queries->getCountQuery()->getOneResult()['count'];

		if ( $this->count > 0 ) {
			$this->totalPages = intval( ceil( $this->count / $this->limit ) );

			$offset       = abs( $this->page - 1 ) * $this->limit;
			$this->offset = ( $offset > 0 ) ? $offset : null;
			$this->queries->createQuery( $this->limit, $this->offset );
		}
	}


	private function exec() {
		return $this->queries->getQuery()->getResult();
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

		$current  = $this->page;
		$viewData = [
			'last'       => $this->totalPages,
			'current'    => $current,
			'limit'      => $this->limit,
			'first'      => 1,
			'pageCount'  => $this->totalPages,
			'totalCount' => $this->count,
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

	/**
	 * @return int
	 */
	public function getTotalPages(): int {
		return $this->totalPages;
	}

	public function createQueries( QueriesInterface $queries ) {
		$this->queries = $queries;
	}

	public function passParams( $array ) {
		$this->queries->setParams( $array );
	}
}