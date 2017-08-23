<?php

namespace BuilderBundle\Twig;

use BuilderBundle\Entity\CommentNode;
use BuilderBundle\Entity\EntityTypeNode;
use BuilderBundle\Entity\FieldValueNode;
use BuilderBundle\Entity\ObjectNode;
use BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use BuilderBundle\Form\CommentNodeType;
use FrontBundle\Helpers\UrlCyrillicTransformer;
use GraphAware\Neo4j\OGM\EntityManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;

class TwigFunctions extends \Twig_Extension {
	/** @var EntityManager */
	private $nm;
	/** @var CacheManager */
	private $liipCM;
	/** @var Packages twig asset packages. Required to use asset() function outside twig */
	private $package;
	/** @var FormFactory */
	private $formFactory;
	/** @var ObjectNodeRepository $oRepository */
	private $oRepository;


	private $objectsCache = [];

	public function __construct( EntityManager $nm, CacheManager $liipImagineCacheManager, Packages $package, FormFactory $formFactory ) {
		$this->nm          = $nm;
		$this->liipCM      = $liipImagineCacheManager;
		$this->package     = $package;
		$this->formFactory = $formFactory;

		$this->oRepository = $this->nm->getRepository( ObjectNode::class );
	}

	public function getFunctions() {
		return [
			// gets object`s fields and values
			new \Twig_SimpleFunction( 'getFields', [ $this, 'getFields' ] ),
			// gets object`s single values
			new \Twig_SimpleFunction( 'getField', [ $this, 'getField' ] ),
			// returns objects`s field type by slug
			new \Twig_SimpleFunction( 'getFieldType', [ $this, 'getFieldType' ] ),
			// gets objects`s fields and values array filtered by provided field slugs. May couse additional query to DB.
			new \Twig_SimpleFunction( 'getFieldsBySlugs', [ $this, 'getFieldsBySlugs' ] ),

			// gets object`s child objects list of specific EntityType
			new \Twig_SimpleFunction( 'getChildObjectsByType', [ $this, 'getChildObjectsByType' ] ),
			// gets object`s child objects AND their FIELDS & VALUES of single specific EntityType
			new \Twig_SimpleFunction( 'getChildObjectsValsByParent', [ $this, 'getChildObjectsValsByParent' ] ),
			// gets specific parent type by slug
			new \Twig_SimpleFunction( 'getParentTypeBySlug', [ $this, 'getParentTypeBySlug' ] ),

			// shorthand to get webpath && thumbnail fieldValue. analog of {{ asset(getField(object, 'FIELD_NAME').webPath)|imagine_filter('FILTER_NAME') }}
			new \Twig_SimpleFunction( 'getFieldThumbs', [ $this, 'getFieldThumbs' ] ),

			// create form to post comment
			new \Twig_SimpleFunction( 'createCommentForm', [ $this, 'createCommentForm' ] ),
		];
	}

	public function getFilters() {
		return [
			// transform field value data to url format
			new \Twig_SimpleFilter( 'escapeCyr', array( $this, 'escapeCyrillic' ) ),
			// thumbnail for single fieldValue
			new \Twig_SimpleFilter( 'getSingleFieldThumb', array( $this, 'getSingleFieldThumb' ) ),
		];
	}

	/**
	 * Filters existent or makes query resulting to array of object fields and values with provided field slugs
	 *
	 * @param ObjectNode $objectNode
	 * @param array $slugs
	 * @param bool $diff
	 *
	 * @return array
	 */
	public function getFieldsBySlugs( ObjectNode $objectNode, array $slugs, bool $diff = false ) {
		if ( $diff ) {
			return array_diff_key( $this->getFields( $objectNode ), array_flip( $slugs ) );
		} else {
			return array_intersect_key( $this->getFields( $objectNode ), array_flip( $slugs ) );
		}
	}

	public function getFields( ObjectNode $objectNode ) {
		if ( isset( $this->objectsCache[ $objectNode->getId() ] ) ) {
			return $this->objectsCache[ $objectNode->getId() ];
		} else {
			return $this->objectsCache[ $objectNode->getId() ] = $this->oRepository->getFieldsStructWithSlug( $objectNode );
		}
	}

	/**
	 * Returns all thumbnails of file (image) field.
	 * Creates/reads image cache if needed.
	 *
	 *
	 * @param ObjectNode $objectNode
	 * @param string $field
	 * @param string $thumbFilterName
	 * @param string $propertyName
	 *
	 * @return array|null
	 */
	public function getFieldThumbs( ObjectNode $objectNode, string $field, string $thumbFilterName, string $propertyName = 'getWebPath' ) {
		$vals          = $this->getField( $objectNode, $field );
		$resThumbsURLs = null;
		if ( is_array( $vals ) ) {
			foreach ( $vals as $val ) {
				if ( ! empty( $val->{$propertyName}() ) ) {
					$resThumbsURLs[] = $this->getSingleFieldThumb( $val, $thumbFilterName, $propertyName );
				}
			}
		} else {
			$resThumbsURLs = $this->getSingleFieldThumb( $vals, $thumbFilterName, $propertyName );
		}

		return $resThumbsURLs;
	}

	/**
	 * Returns FieldValue(s) for specified field of object
	 *
	 * @param ObjectNode $objectNode
	 * @param string $type
	 *
	 * @return FieldValueNode|null
	 */
	public function getField( ObjectNode $objectNode, string $type ) {
		$fields = $this->getFields( $objectNode );

		// if found single non-collection field type - return single value instead of array of values
		if ( isset( $fields[ $type ] ) ) {
			$val = ( ! $fields[ $type ]['type']->isCollection() && ( count( $fields[ $type ]['val'] ) == 1 ) ) ? $fields[ $type ]['val'][0] : $fields[ $type ]['val'];
		} else {
			$val = null;
		}

		return $val ?? new FieldValueNode();
	}

	public function getSingleFieldThumb( FieldValueNode $val, string $thumbFilterName ) {
		//if this is image && webpath is not empty - get thumbnail
		if ( ( 0 === strpos( $val->getMimeType(), 'image/' ) ) && ! empty( $val->getWebPath() ) ) {
			return $this->getAssetThumbnail( $val->getWebPath(), $thumbFilterName );
		} else {
			return '';
		}
	}

	private function getAssetThumbnail( string $val, $thumbFilterName ) {
		return $this->liipCM->getBrowserPath( $this->package->getUrl( $val ), $thumbFilterName );
	}

	/**
	 * Returns FieldValue(s) for specified field of object
	 *
	 * @param ObjectNode $objectNode
	 * @param string $type
	 *
	 * @return FieldValueNode|null
	 */
	public function getFieldType( ObjectNode $objectNode, string $type ) {
		$fields = $this->getFields( $objectNode );

		return isset( $fields[ $type ] ) ? $fields[ $type ]['type'] : null;
	}

	/**
	 * Bridge through ObjectRepository method and twig
	 *
	 * @param ObjectNode $objectNode
	 * @param EntityTypeNode $childType
	 * @param int $limit
	 * @param int $skip
	 *
	 * @return array
	 */
	public function getChildObjectsByType( $objectNode, $childType, int $limit = 10, int $skip = 0 ) {
		return $this->oRepository->findChildObjectsByParent( $objectNode->getEntityType()->getSlug(), $objectNode->getSlug(), $childType->getSlug(), $limit, $skip );
	}

	/**
	 * Bridge through ObjectRepository method and twig
	 *
	 * @param ObjectNode $objectNode
	 * @param EntityTypeNode $childType
	 * @param int $limit
	 * @param int $skip
	 *
	 * @return array
	 */
	public function getChildObjectsValsByParent( $objectNode, $childType, int $limit = 10, int $skip = 0 ) {
		return $this->oRepository->getChildObjectsValsByParent( $objectNode->getEntityType()->getSlug(), $objectNode->getSlug(), $childType->getSlug(), $limit, $skip );
	}

	public function getParentTypeBySlug( $objectNode, $parentTypeSlug ) {
		return $this->oRepository->getParentTypeBySlug( $objectNode, $parentTypeSlug );
	}

	/**
	 * Escapes string to pass through twig path() function
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function escapeCyrillic( string $string ) {
		$transformer = new UrlCyrillicTransformer();

		return $transformer->transform( $string );
	}


	/**
	 * @param string $refType CommentNode constants: 'ref_user' || 'ref_objec' || 'ref_type';
	 * @param string $refObjectId Id of thing to comment
	 *
	 * @return \Symfony\Component\Form\FormView
	 */
	public function createCommentForm( string $refType, string $refObjectId ) {
		$comment     = new CommentNode();
		$formBuilder = $this->formFactory->createNamedBuilder( 'comment_form', CommentNodeType::class, $comment );
		$formBuilder->get( 'refType' )->setData( $refType );
		$formBuilder->get( 'refId' )->setData( $refObjectId );
		/** @var Form $form */
		$form = $formBuilder->add( 'submit_button', SubmitType::class, [ 'label' => '<i class="icon edit"></i>Комментировать' ] )->getForm();

		return $form->createView();
	}
}