<?php

namespace BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use FrontBundle\Helpers\TemplateTwigFileResolver;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="EntityType", repository="BuilderBundle\Entity\Repositories\EntityTypeNodeRepository")
 */
class EntityTypeNode {
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
	protected $slug = '';

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $description = '';

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $isDataType = false;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigFilePath = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigFilePathAsChild = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigSingleObjectPath = null;

	/**
	 * @OGM\Relationship(type="has_type", direction="INCOMING", targetEntity="ObjectNode", collection=true)
	 * @var ArrayCollection|ObjectNode[]
	 */
	protected $objects;

	/**
	 * @OGM\Relationship(type="has_field", direction="INCOMING", targetEntity="TypeFieldNode", collection=true, mappedBy="entityType")
	 * @var ArrayCollection|TypeFieldNode[]
	 */
	protected $entityTypeFields;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="INCOMING", targetEntity="EntityTypeNode", collection=true, mappedBy="parentTypes")
	 * @OGM\OrderBy(property="name", order="ASC")
	 * @var ArrayCollection|EntityTypeNode[]
	 */
	protected $childTypes;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="OUTGOING", targetEntity="EntityTypeNode", collection=true, mappedBy="childTypes")
	 * @var ArrayCollection|EntityTypeNode[]
	 */
	protected $parentTypes;

	public function __construct( $name = '', $description = null ) {
		$this->name             = $name;
		$this->description      = $description;
		$this->objects          = new ArrayCollection();
		$this->entityTypeFields = new ArrayCollection();
		$this->childTypes       = new ArrayCollection();
		$this->parentTypes      = new ArrayCollection();
	}

	public function __toString() {
		return (string) $this->getId();
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
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
	 * @return ArrayCollection|ObjectNode[]
	 */
	public function getObjects() {
		return $this->objects;
	}

	/**
	 * @param ObjectNode|ArrayCollection $objects
	 */
	public function setObjects( $objects ) {
		$this->objects = $objects;
	}

	/**
	 * @return ArrayCollection|TypeFieldNode[]
	 */
	public function getEntityTypeFields() {
		return $this->entityTypeFields;
	}

	/**
	 * @param ArrayCollection|TypeFieldNode[] $entityTypeFields
	 */
	public function setEntityTypeFields( $entityTypeFields ) {
		$this->entityTypeFields = $entityTypeFields;
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
	 * @return ArrayCollection|EntityTypeNode[]
	 */
	public function getParentTypes() {
		return $this->parentTypes;
	}

	/**
	 * @param EntityTypeNode[]|ArrayCollection $parentTypes
	 */
	public function setParentTypes( $parentTypes ) {
		$this->parentTypes = $parentTypes;
	}

	/**
	 * @param EntityTypeNode $parentType
	 */
	public function addParentType( $parentType ) {
		if ( ! $this->parentTypes->contains( $parentType ) ) {
			$this->parentTypes->add( $parentType );
		}
	}

	/**
	 * @param EntityTypeNode $parentType
	 */
	public function removeParentType( $parentType ) {
		$this->parentTypes->removeElement( $parentType );
	}

	/**
	 * @param TypeFieldNode $typeField
	 */
	public function addEntityTypeField( $typeField ) {
		if ( ! $this->entityTypeFields->contains( $typeField ) ) {
			$this->entityTypeFields->add( $typeField );
		}
	}

	/**
	 * @param TypeFieldNode $typeField
	 */
	public function removeEntityTypeField( $typeField ) {
		$this->entityTypeFields->removeElement( $typeField );
	}

	/**
	 * @return bool
	 */
	public function isDataType(): ?bool {
		return $this->isDataType;
	}

	/**
	 * @param bool $isDataType
	 *
	 * @return EntityTypeNode
	 */
	public function setIsDataType( bool $isDataType ): EntityTypeNode {
		$this->isDataType = $isDataType;

		return $this;
	}

	/**
	 * @return EntityTypeNode[]|ArrayCollection
	 */
	public function getChildTypes() {
		return $this->childTypes;
	}

	/**
	 * @param EntityTypeNode[]|ArrayCollection $childTypes
	 */
	public function setChildTypes( $childTypes ) {
		$this->childTypes = $childTypes;
	}

	/**
	 * @return string
	 */
	public function getTwigFilePathAsChild() {
		return $this->twigFilePathAsChild;
	}

	/**
	 * @param string $twigFilePathAsChild
	 */
	public function setTwigFilePathAsChild( $twigFilePathAsChild ) {
		$this->twigFilePathAsChild = ( $twigFilePathAsChild === TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigFilePathAsChild;
	}

	/**
	 * @return string
	 */
	public function getTwigFilePath() {
		return $this->twigFilePath;
	}

	/**
	 * @param string $twigFilePath
	 */
	public function setTwigFilePath( $twigFilePath ) {
		$this->twigFilePath = ( $twigFilePath === TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigFilePath;
	}

	/**
	 * @return string
	 */
	public function getTwigSingleObjectPath() {
		return $this->twigSingleObjectPath;
	}

	/**
	 * @param string $twigSingleObjectPath
	 */
	public function setTwigSingleObjectPath( $twigSingleObjectPath ) {
		$this->twigSingleObjectPath = $twigSingleObjectPath;
	}

}