<?php

namespace Nodeart\BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;
use Nodeart\BuilderBundle\Helpers\TemplateTwigFileResolver;

/**
 * @OGM\Node(label="EntityTypeField", repository="Nodeart\BuilderBundle\Entity\Repositories\TypeFieldNodeRepository")
 */
class TypeFieldNode {
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
	protected $fieldType = '';

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $isCollection = false;

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $isMainField = false;

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $hasOwnUrl = true;

	/**
	 * @OGM\Relationship(type="has_field", direction="OUTGOING", targetEntity="EntityTypeNode", collection=false)
	 * @var EntityTypeNode
	 */
	protected $entityType;

	/**
	 * @OGM\Relationship(type="is_value_of", direction="INCOMING", targetEntity="FieldValueNode", collection=true)
	 * @var ArrayCollection|FieldValueNode[]
	 */
	protected $fieldValues;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $options = '';

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $metaDesc = '';

	/**
     * @OGM\Property(type="string", nullable=false)
	 * @var string
	 */
	protected $tabGroup = 'default';


	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $tooltip = '';

	/**
	 * @OGM\Property(type="int")
	 * @var integer
	 */
	protected $order = 0;

	/**
	 * @OGM\Property(type="array")
	 * @var array
	 */
	protected $predefinedFields = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigListFieldVal = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigSingleFieldVal = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigListDataTypeField = null;

	/**
	 * @OGM\Property(type="string", nullable=true)
	 * @var string
	 */
	protected $twigSingleDataTypeField = null;

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $required = false;

    /**
     * @OGM\Property(type="boolean")
     * @var boolean
     */
    protected $comparable = true;

    /**
     * @OGM\Property(type="boolean")
     * @var boolean
     */
    protected $mainFilter = false;
    /**
     * @OGM\Property(type="boolean")
     * @var boolean
     */
    protected $hideInFilters = true;

	public function __construct( $name = null ) {
		$this->name        = $name;
		$this->fieldValues = new ArrayCollection();
	}

	/**
	 * @param FieldValueNode $fv
	 */
	public function addFieldValue( FieldValueNode $fv ) {
		if ( ! $this->fieldValues->contains( $fv ) ) {
			$this->fieldValues->add( $fv );
		}
	}

	/**
	 * @return FieldValueNode|ArrayCollection
	 */
	public function getFieldValues() {
		return $this->fieldValues;
	}

	/**
	 * @param FieldValueNode|ArrayCollection $fieldValues
	 */
	public function setFieldValues( $fieldValues ) {
		$this->fieldValues = $fieldValues;
	}

    /*
     * That`s hella bad code over there ;(
     */
	public function toArray( $withEntityType = false, $withoutId = false ) {
		$fieldsArray = [
			'name'                    => $this->getName(),
			'slug'                    => $this->getSlug(),
			'fieldType'               => $this->getFieldType(),
			'options'                 => $this->getOptions(),
			'isCollection'            => $this->isCollection(),
			'isMainField'             => $this->isMainField(),
			'metaDesc'                => $this->getMetaDesc(),
			'tabGroup'                => $this->getTabGroup(),
			'tooltip'                 => $this->getTooltip(),
			'predefinedFields'        => $this->getPredefinedFields(),
			'hasOwnUrl'               => $this->hasOwnUrl(),
			'required'                => $this->isRequired(),
			'order'                   => $this->getOrder(),

            //filter-related fields
            'comparable' => $this->isComparable(),
            'mainFilter' => $this->isMainFilter(),
            'hideInFilters' => $this->isHideInFilters(),

			// template fields
			'twigListFieldVal'        => $this->getTwigListFieldVal(),
			'twigSingleFieldVal'      => $this->getTwigSingleFieldVal(),
			'twigListDataTypeField'   => $this->getTwigListDataTypeField(),
			'twigSingleDataTypeField' => $this->getTwigSingleDataTypeField(),
		];
		if ( ! $withoutId ) {
			$fieldsArray['id'] = $this->getId();
		}
		if ( $withEntityType ) {
			$fieldsArray['type'] = $this->getEntityType()->getName();
		}

		return $fieldsArray;
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
	public function getFieldType(): string {
		return $this->fieldType;
	}

	/**
	 * @param string $fieldType
	 */
	public function setFieldType( string $fieldType ) {
		$this->fieldType = $fieldType;
	}

	/**
	 * @return string
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * @param string $options
	 */
	public function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * @return boolean
	 */
	public function isCollection(): bool {
		return $this->isCollection;
	}

	/**
	 * @param boolean $isCollection
	 */
	public function setIsCollection( bool $isCollection ) {
		$this->isCollection = $isCollection;
	}

	/**
	 * @return bool
	 */
    public function isMainField(): ?bool
    {
		return $this->isMainField;
	}

	/**
	 * @return string|null
	 */
    public function getMetaDesc(): ?string
    {
		return $this->metaDesc;
	}

	/**
	 * @param string|null $metaDesc
	 */
	public function setMetaDesc( $metaDesc ) {
		$this->metaDesc = $metaDesc;
	}

	/**
	 * @return string
	 */
    public function getTabGroup(): ?string
    {
		return $this->tabGroup;
	}

	/**
	 * @param string $tabGroup
	 */
	public function setTabGroup( string $tabGroup ) {
		$this->tabGroup = $tabGroup;
	}

	/**
	 * @return string
	 */
	public function getTooltip(): string {
		return $this->tooltip;
	}

	/**
	 * @param string $tooltip
	 */
	public function setTooltip( string $tooltip ) {
		$this->tooltip = $tooltip;
	}

	/**
	 * @return array
	 */
	public function getPredefinedFields() {
		return $this->predefinedFields;
	}

	/**
	 * @param array $predefinedFields
	 */
	public function setPredefinedFields( $predefinedFields ) {
		if ( empty( $predefinedFields ) ) {
			$predefinedFields = null;
		}
		$this->predefinedFields = $predefinedFields;
	}

	/**
	 * @return bool
	 */
	public function hasOwnUrl() {
		return $this->hasOwnUrl;
	}

	/**
	 * @return bool
	 */
	public function isRequired() {
		return $this->required;
	}

	/**
	 * @param bool $required
	 */
	public function setRequired( bool $required ) {
		$this->required = $required;
	}

	/**
	 * @return int
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 * @param int $order
	 */
	public function setOrder( $order ) {
		$this->order = intval( $order );
	}

	/**
	 * @return string
	 */
	public function getTwigListFieldVal() {
		return $this->twigListFieldVal;
	}

	/**
	 * @param string $twigListFieldVal
	 */
	public function setTwigListFieldVal( $twigListFieldVal ) {
		$this->twigListFieldVal = ( $twigListFieldVal == TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigListFieldVal;
	}

	/**
	 * @return string
	 */
	public function getTwigSingleFieldVal() {
		return $this->twigSingleFieldVal;
	}

	/**
	 * @param string $twigSingleFieldVal
	 */
	public function setTwigSingleFieldVal( $twigSingleFieldVal ) {
		$this->twigSingleFieldVal = ( $twigSingleFieldVal == TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigSingleFieldVal;
	}

	/**
	 * @return string
	 */
	public function getTwigListDataTypeField() {
		return $this->twigListDataTypeField;
	}

	/**
	 * @param string $twigListDataTypeField
	 */
	public function setTwigListDataTypeField( $twigListDataTypeField ) {
		$this->twigListDataTypeField = ( $twigListDataTypeField == TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigListDataTypeField;
	}

	/**
	 * @return string
	 */
	public function getTwigSingleDataTypeField() {
		return $this->twigSingleDataTypeField;
	}

	/**
	 * @param string $twigSingleDataTypeField
	 */
	public function setTwigSingleDataTypeField( $twigSingleDataTypeField ) {
		$this->twigSingleDataTypeField = ( $twigSingleDataTypeField == TemplateTwigFileResolver::DEFAULT_TEMPLATE_FULL_NAME ) ? null : $twigSingleDataTypeField;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return EntityTypeNode
	 */
	public function getEntityType(): EntityTypeNode {
		return $this->entityType;
	}

	/**
	 * @param EntityTypeNode $entityType
	 */
	public function setEntityType( EntityTypeNode $entityType ) {
		$this->entityType = $entityType;
	}

	/**
	 * @param bool $isMainField
	 */
	public function setMainField( bool $isMainField ) {
		$this->isMainField = $isMainField;
	}

	/**
	 * @param bool $ownUrl
	 */
	public function setOwnUrl( bool $ownUrl ) {
		$this->hasOwnUrl = $ownUrl;
	}

    /**
     * @return bool
     */
    public function isComparable(): ?bool
    {
        return $this->comparable;
    }

    /**
     * @param bool $comparable
     * @return TypeFieldNode
     */
    public function setComparable(bool $comparable): TypeFieldNode
    {
        $this->comparable = $comparable;
        return $this;
    }

    /**
     * @return bool
     */
    public function isMainFilter(): ?bool
    {
        return $this->mainFilter;
    }

    /**
     * @param bool $mainFilter
     * @return TypeFieldNode
     */
    public function setMainFilter(bool $mainFilter): TypeFieldNode
    {
        $this->mainFilter = $mainFilter;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHideInFilters(): ?bool
    {
        return $this->hideInFilters;
    }

    /**
     * @param bool $hideInFilters
     * @return TypeFieldNode
     */
    public function setHideInFilters(bool $hideInFilters): TypeFieldNode
    {
        $this->hideInFilters = $hideInFilters;
        return $this;
    }
}