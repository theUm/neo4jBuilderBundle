<?php

namespace Nodeart\BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;
use Nodeart\BuilderBundle\Helpers\TemplateTwigFileResolver;
use Symfony\Component\Workflow\Exception\LogicException;

/**
 * @OGM\Node(label="Object", repository="Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository")
 */
class ObjectNode {
	use isCommentableTrait;

	/**
	 * @OGM\GraphId()
	 * @var int
	 */
	protected $id;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $name;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $description;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $slug = '';

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigFilePath = null;

	/**
	 * @var \DateTime
	 * @OGM\Property()
	 * @OGM\Convert(type="datetime", options={})
	 */
	protected $createdAt;

	/**
	 * @OGM\Relationship(type="has_type", direction="OUTGOING", targetEntity="EntityTypeNode", collection=false)
	 * @var EntityTypeNode
	 */
	protected $entityType;

	/**
	 * @OGM\Relationship(type="is_field_of", direction="BOTH", targetEntity="FieldValueNode", collection=true, mappedBy="objects")
	 * @var ArrayCollection|FieldValueNode[]
	 */
	protected $fieldVals;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="INCOMING", targetEntity="ObjectNode", collection=true, mappedBy="parentObjects")
	 * @var ArrayCollection|ObjectNode[]
	 */
	protected $childObjects;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="OUTGOING", targetEntity="ObjectNode", collection=true)
	 * @var ArrayCollection|ObjectNode[]
	 */
	protected $parentObjects;

	/**
	 * @var array
	 */
	protected $fields = [];

	/**
	 * @var bool flag to show if fields are already fetched
	 */
	private $fieldsInitialized = false;

	/**
	 * @OGM\Relationship(type="is_comment_of", direction="INCOMING", targetEntity="CommentNode", collection=true)
	 * @var ArrayCollection|CommentNode[]
	 */
	private $comments;

	public function __construct( $name = '' ) {
		$this->name          = $name;
		$this->childObjects  = new ArrayCollection();
		$this->parentObjects = new ArrayCollection();
		$this->fieldVals     = new ArrayCollection();
		$this->comments      = new ArrayCollection();
		$this->createdAt     = new \DateTime();
	}

	/**
	 * @return EntityTypeNode
	 */
	public function getEntityType() {
		return $this->entityType;
	}

	/**
	 * @param EntityTypeNode $type
	 */
	public function setEntityType( EntityTypeNode $type ) {
		$this->entityType = $type;
	}

	/**
	 * @param ObjectNode $objectNode
	 */
	public function addChildObjects( $objectNode ) {
		if ( ! $this->getChildObjects()->contains( $objectNode ) ) {
			$this->getChildObjects()->add( $objectNode );
		}
	}

	/**
	 * @return ArrayCollection|ObjectNode[]
	 */
	public function getChildObjects() {
		return $this->childObjects;
	}

	/**
	 * @param mixed $childObjects
	 */
	public function setChildObjects( $childObjects ) {
		$this->childObjects = $childObjects;
	}

	/**
	 * @param ObjectNode $objectNode
	 */
	public function removeChild( $objectNode ) {
		$this->childObjects->removeElement( $objectNode );
	}

	/**
	 * @param ObjectNode $parentObjects
	 */
	public function addParentObjects( $parentObjects ) {
		if ( ! $this->getParentObjects()->contains( $parentObjects ) ) {
			$this->getParentObjects()->add( $parentObjects );
		}
	}

	/**
	 * @return ArrayCollection
	 */
	public function getParentObjects() {
		return $this->parentObjects;
	}

	public function setParentObjects( $parentObjects ) {
		$this->parentObjects = $parentObjects;
	}

	/**
	 * @param ObjectNode $parentObjects
	 */
	public function removeParentObject( $parentObjects ) {
		$this->getParentObjects()->removeElement( $parentObjects );
	}

	/**
	 * @param FieldValueNode $fieldValue
	 */
	public function addFieldValue( $fieldValue ) {
		if ( ! $this->fieldVals->contains( $fieldValue ) ) {
			$this->fieldVals->add( $fieldValue );
		}
	}

	/**
	 * @param FieldValueNode $fieldValue
	 */
	public function removeFieldValue( $fieldValue ) {
		$this->fieldVals->removeElement( $fieldValue );
	}

	/**
	 * @return FieldValueNode[]|ArrayCollection
	 */
	public function getFieldVals() {
		return $this->fieldVals;
	}

	/**
	 * @param FieldValueNode[]|ArrayCollection $fieldVals
	 */
	public function setFieldVals( $fieldVals ) {
		$this->fieldVals = $fieldVals;
	}

	public function toArray( $withoutId = false ) {
		$fieldsArray = [
			'name'         => $this->getName(),
			'slug'         => $this->getSlug(),
			'description'  => $this->getDescription(),
			'twigFilePath' => $this->getTwigFilePath(),
			'createdAt'    => is_null( $this->getCreatedAt() ) ? $this->getCreatedAt()->getTimestamp() : null
		];
		if ( ! $withoutId ) {
			$fieldsArray['id'] = $this->getId();
		}

		return $fieldsArray;
	}


	/**
	 * @return \DateTime
	 */
	public function getCreatedAt(): ?\DateTime {
		return $this->createdAt;
	}

	/**
	 * @param \DateTime $createdAt
	 *
	 * @return ObjectNode
	 */
	public function setCreatedAt( \DateTime $createdAt ): ObjectNode {
		$this->createdAt = $createdAt;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName( string $name ) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getSlug(): string {
		return $this->slug;
	}

	/**
	 * @param string $slug
	 */
	public function setSlug( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription( string $description ) {
		$this->description = $description;
	}

	/**
	 * @return mixed
	 */
	public function getTwigFilePath() {
		return $this->twigFilePath;
	}

	/**
	 * @param mixed $twigFilePath
	 */
	public function setTwigFilePath( $twigFilePath ) {
		$this->twigFilePath = ( $twigFilePath === TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigFilePath;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param $id int
	 */
	public function setId( $id ) {
		$this->id = $id;
	}


	public function isFieldsInitialized() {
		return $this->fieldsInitialized;
	}

	public function getFields() {
		if ( ! $this->fieldsInitialized ) {
			throw new LogicException( 'Use ObjectRepository->getFields($object) or twig`s {{ getFields(object) }} first. This is singleton-ish method (and will )' );
		}

		return $this->fields;
	}

	public function setFields( array $fields ) {
		$this->fields            = $fields;
		$this->fieldsInitialized = true;
	}

	/**
	 * @return ArrayCollection|CommentNode[]
	 */
	public function getComments() {
		return $this->comments;
	}

	/**
	 * @param ArrayCollection|CommentNode[] $comments
	 */
	public function setComments( $comments ) {
		$this->comments = $comments;
	}
}