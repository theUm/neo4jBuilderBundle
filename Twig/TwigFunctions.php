<?php

namespace Nodeart\BuilderBundle\Twig;

use FrontBundle\Helpers\UrlCyrillicTransformer;
use GraphAware\Neo4j\OGM\EntityManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\EntityTypeNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Form\CommentNodeType;
use Nodeart\BuilderBundle\Helpers\FieldAndValue;
use Nodeart\BuilderBundle\Services\ObjectSearchQueryService\ObjectSearchQuery;
use Nodeart\BuilderBundle\Services\ObjectsQueriesRAMStorage;
use Nodeart\BuilderBundle\Twig\Utils\TypeFieldValuePairTransformer;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;

class TwigFunctions extends \Twig_Extension
{

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
    /** @var EntityTypeNodeRepository $etRepository */
    private $etRepository;
    /** @var ObjectSearchQuery $objectSearchQuery */
    private $objectSearchQuery;

    private $objectCache;

    /**
     * First level of array ([objId]): result of getObjectSiblings(), second level in array ([objId]['childDataObjects']) is child data objects
     * @var array
     */
    private $siblingsCache = [];
    /**
     * @var \Twig_Environment
     */
    private $environment;

    public function __construct(
        EntityManager $nm,
        CacheManager $liipImagineCacheManager,
        Packages $package,
        FormFactory $formFactory,
        ObjectsQueriesRAMStorage $oqrs,
        ObjectSearchQuery $objectSearchQuery
    )
    {
        $this->nm = $nm;
        $this->liipCM = $liipImagineCacheManager;
        $this->package = $package;
        $this->formFactory = $formFactory;
        $this->objectCache = $oqrs;
        $this->objectSearchQuery = $objectSearchQuery;

        $this->oRepository = $this->nm->getRepository(ObjectNode::class);
        $this->etRepository = $this->nm->getRepository(EntityTypeNode::class);
    }

    /**
     * Restructures array for further usage
     *
     * @param array $struct
     *
     * @return array
     */
    public static function reformatFields(array $struct)
    {
        foreach ($struct as &$row) {
            $newObjFields = [];
            foreach ($row['objectFields'] as $field) {
                $newObjFields[$field['etfSlug']] = $field['valsByFields'];
            }
            $row['objectFields'] = $newObjFields;
        }
        return $struct;
    }

    public function getFunctions()
    {
        return [
            /**
             * Fields - related functions
             */
            // gets object`s fields and values
            new \Twig_SimpleFunction('getFields', [$this, 'getFields']),
            // gets object`s fields and values array filtered by provided field slugs. May cause additional query to DB.
            new \Twig_SimpleFunction('getFieldsBySlugs', [$this, 'getFieldsBySlugs']),
            // gets object`s single values
            new \Twig_SimpleFunction('getField', [$this, 'getField']),
            // returns objects`s field type by slug
            new \Twig_SimpleFunction('getFieldType', [$this, 'getFieldType']),
            // shorthand to get webpath && thumbnail fieldValue. analog of {{ asset(getField(object, 'FIELD_NAME').webPath)|imagine_filter('FILTER_NAME') }}
            new \Twig_SimpleFunction('getFieldThumbs', [$this, 'getFieldThumbs']),
            // returns field vals of object, which fieldTypes are marked as "main"
            new \Twig_SimpleFunction('getMainFields', [$this, 'getMainFields']),
            // transforms fieldValueNode to string
            new \Twig_SimpleFunction('transformPair', [$this, 'stringifyFieldPair']),
            // get/fetch fields and transform them to strings if it is possible
            new \Twig_SimpleFunction('getCardFields', [$this, 'getCardFields'], ['needs_environment' => true]),

            /**
             * Objects - related functions - single-purpose queries
             */
            // gets object`s child objects list of specific EntityType
            new \Twig_SimpleFunction('getMultipleChildObjectsByParent', [$this, 'getMultipleChildObjectsByParent']),

            // @todo: refactor it - make it use ObjectSearchQuery
            // gets object`s siblings (same EntityType)
            new \Twig_SimpleFunction('getObjectSiblings', [$this, 'getObjectSiblings']),
            // @todo: refactor it - make it use ObjectSearchQuery
            // gets specific parent object type by type slug. returns object or null
            new \Twig_SimpleFunction('getParentObjectByTypeSlug', [$this, 'getParentObjectByTypeSlug']),

            // create form to post comment
            new \Twig_SimpleFunction('createCommentForm', [$this, 'createCommentForm']),

            // finds single EntityTypeNode by slug
            new \Twig_SimpleFunction('getEntityType', [$this, 'getEntityTypeNode']),

            /**
             * Objects - related functions - all based on ObjectSearchQuery
             */
            // finds list of objects with fields, based on filters
            new \Twig_SimpleFunction('getObjects', [$this, 'getObjects']),
            // finds list of related child objects of specified parent object with fields, based on filters
            new \Twig_SimpleFunction('getChildObjects', [$this, 'getChildObjects']),
            // finds list of related parent objects of specified child object with fields, based on filters
            new \Twig_SimpleFunction('getParentObjects', [$this, 'getParentObjects']),
        ];
    }

    public function getFilters()
    {
        return [
            // transform field value data to url format
            new \Twig_SimpleFilter('escapeCyr', array($this, 'escapeCyrillic')),
            // thumbnail for single fieldValue
            new \Twig_SimpleFilter('getSingleFieldThumb', array($this, 'getSingleFieldThumb')),
            // reformats fetched objects with fields structure
            new \Twig_SimpleFilter('reformatFields', array($this, 'reformatFields')),
            // filters inactive objects
            new \Twig_SimpleFilter('filterInactive', array($this, 'filterInactive')),
        ];
    }

    public function getCardFields(\Twig_Environment $environment, ObjectNode $object, array $fields, $typeKey = 'fieldType', $valueKey = 'val')
    {
        $this->environment = $environment;
        $cardFields = ['main' => [], 'col1' => [], 'col2' => []];

        foreach ($fields['main'] as $field) {
            $cardFields['main'][] = $this->transformFieldToCardView($field, $typeKey, $valueKey, $object);
        }

        foreach ($fields['col1'] as $field) {
            $cardFields['col1'][] = $this->transformFieldToCardView($field, $typeKey, $valueKey, $object);
        }

        foreach ($fields['col2'] as $field) {
            $cardFields['col2'][] = $this->transformFieldToCardView($field, $typeKey, $valueKey, $object);
        }

        return $cardFields;
    }

    private function transformFieldToCardView($field, $typeKey, $valueKey, $object)
    {
        if (!is_array($field)) {
            $pair = $this->getFieldPair($object, $field);
            return $this->stringifyFieldPair($pair, $typeKey, $valueKey);
        } else {
            $nameHtml = $this->environment->createTemplate($field[$typeKey]);
            $valueHtml = $this->environment->createTemplate($field[$valueKey]);

            return [$typeKey => $nameHtml, $valueKey => $valueHtml, 'isTemplate' => true];
        }
    }

    /**
     *
     * @param ObjectNode $objectNode
     * @param string $slug
     *
     * @return array
     * @throws \Exception
     */
    public function getFieldPair(ObjectNode $objectNode, string $slug)
    {
        $fields = $this->getFields($objectNode);
        if (!isset($fields[$slug])) {
            throw new \Exception(sprintf('Field with slug "%s" not found in object "%s"', $slug, $objectNode->getName()));
        }

        return $fields[$slug];
    }

    public function getFields($objectNode)
    {
        if ($this->objectCache->isStored($objectNode->getId())) {
            $objectStruct = $this->objectCache->get($objectNode->getId());
        } else {
            $objectStruct = $this->objectCache->add(['object' => $objectNode, 'objectFields' => $this->oRepository->getFieldsStructWithSlug($objectNode)]);
        }
        return $objectStruct['objectFields'];
    }

    /**
     * Filters existent or makes query resulting to array of object fields and values with provided field slugs
     *
     * @param $pair
     * @param string $etIndex
     * @param string $fvIndex
     *
     * @return array
     */
    public function stringifyFieldPair($pair, $etIndex = 'fieldType', $fvIndex = 'val')
    {
        /** @var TypeFieldNode $fieldType */
        $fieldType = $pair[$etIndex];
        $fieldValues = $pair[$fvIndex];

        if ($fieldType->isCollection()) {
            $transformedFieldValStrings[$fieldType->getSlug()] = [];
            foreach ($fieldValues as $fielVal) {
                $transformedFieldValStrings[$fieldType->getSlug()][] = TypeFieldValuePairTransformer::transformValueToView($fielVal, $fieldType);
            }
            $transformedFieldValStrings = array_filter($transformedFieldValStrings[$fieldType->getSlug()]);
            $transformedFieldValStrings = join(', ', $transformedFieldValStrings);
        } else {
            $transformedFieldValStrings = TypeFieldValuePairTransformer::transformValueToView(array_shift($fieldValues), $fieldType);
        }

        $res = [$etIndex => $fieldType, $fvIndex => $transformedFieldValStrings, 'isTemplate' => false];

        return $res;
    }

    public function getMainFields(ObjectNode $object, $asArray = true, $delimiter = ',')
    {
        $mainFieldVals = [];
        $fields = $this->getFields($object);
        /** @var FieldAndValue $fieldAndValue */
        foreach ($fields as $fieldSlug => $fieldAndValue) {
            /** @var TypeFieldNode $typeNode */
            $typeNode = $fieldAndValue['fieldType'];
            if ($typeNode->isMainField() && !is_null($fieldAndValue['val'])) {
                $values = $fieldAndValue['val'];
                if (is_array($values)) {
                    $valuesData = [];
                    /** @var FieldValueNode $fieldValue */
                    foreach ($values as $fieldValue) {
                        $valuesData[] = $fieldValue->getData();
                    }
                    $mainFieldVals = array_merge($mainFieldVals, $valuesData);
                } else {
                    $mainFieldVals[] = $values->getData();
                }
            }
        }

        if (!$asArray) {
            $mainFieldVals = join($delimiter, $mainFieldVals);
        }
        return $mainFieldVals;
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
    public function getFieldsBySlugs(ObjectNode $objectNode, array $slugs, bool $diff = false)
    {
        if ($diff) {
            return array_diff_key($this->getFields($objectNode), array_flip($slugs));
        } else {
            return array_intersect_key($this->getFields($objectNode), array_flip($slugs));
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
    public function getFieldThumbs(ObjectNode $objectNode, string $field, string $thumbFilterName, string $propertyName = 'getWebPath')
    {
        $vals = $this->getField($objectNode, $field);
        $resThumbsURLs = null;
        if (is_array($vals)) {
            foreach ($vals as $val) {
                if (!empty($val->{$propertyName}())) {
                    $resThumbsURLs[] = $this->getSingleFieldThumb($val, $thumbFilterName, $propertyName);
                }
            }
        } else {
            $resThumbsURLs = $this->getSingleFieldThumb($vals, $thumbFilterName, $propertyName);
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
    public function getField(ObjectNode $objectNode, string $type)
    {
        $fields = $this->getFields($objectNode);

        // if found single non-collection field type - return single value instead of array of values
        if (isset($fields[$type])) {
            // if field is collection field and has more th
            $val = (!$fields[$type]['fieldType']->isCollection() && (count($fields[$type]['val']) == 1)) ? $fields[$type]['val'][0] : $fields[$type]['val'];
            // if empty array on single value
            if (!$fields[$type]['fieldType']->isCollection() && empty($val)) {
                $val = null;
            }
        } else {
            $val = null;
        }
        return $val ?? new FieldValueNode();
    }

    public function getSingleFieldThumb($val, string $thumbFilterName, string $propertyName = 'getWebPath')
    {
        if (empty($val)) {
            return '';
        }
        //if this is image && webpath is not empty - get thumbnail
        if ((0 === strpos($val->getMimeType(), 'image/')) && !empty($val->$propertyName())) {
            return $this->getAssetThumbnail($val->$propertyName(), $thumbFilterName);
        } else {
            return '';
        }
    }

    private function getAssetThumbnail(string $val, $thumbFilterName)
    {
        return $this->liipCM->getBrowserPath($this->package->getUrl($val), $thumbFilterName);
    }

    /**
     * Returns FieldValue(s) for specified field of object
     *
     * @param ObjectNode $objectNode
     * @param string $type
     *
     * @return FieldValueNode|null
     */
    public function getFieldType(ObjectNode $objectNode, string $type)
    {
        $fields = $this->getFields($objectNode);

        return isset($fields[$type]) ? $fields[$type]['type'] : null;
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
    public function getChildObjectsByType($objectNode, $childType, int $limit = 10, int $skip = 0)
    {
        return $this->oRepository->findChildObjectsByParent($objectNode->getEntityType()->getSlug(), $objectNode->getSlug(), $childType->getSlug(), $limit, $skip);
    }

    /**
     * Bridge through ObjectRepository method and twig
     *
     * @todo link this with ObjectSearchQuery
     * @param ObjectNode $o
     * @param array $childSlugs
     * @param int $limit
     * @param int $skip
     *
     * @return array
     */
    public function getMultipleChildObjectsByParent($o, array $childSlugs = [], int $limit = 10, int $skip = 0)
    {
        if (!isset($this->siblingsCache[$o->getId()])) {
            $siblings = $this->oRepository->findMultipleChildObjectsByParent($o->getEntityType()->getSlug(), $o->getSlug(), $childSlugs, $limit, $skip);
            $this->siblingsCache[$o->getId()] = $siblings;
        }

        return $this->siblingsCache[$o->getId()];
    }

    /**
     * Find object siblings with fields struct
     *
     * @param ObjectNode $objectNode
     * @param int $limit
     * @param int $skip
     *
     * @param null $parentSlug
     * @param array $valuesFilters
     * @return array
     * @throws \Exception
     */
    public function getObjectSiblings($objectNode, int $limit = 5, int $skip = 0, $parentSlug = null, $valuesFilters = [])
    {
        return $this->oRepository->findObjectSiblingsWithFields($objectNode, $limit, $skip, $parentSlug, $valuesFilters);
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
    public function getChildObjectsValsByParent($objectNode, $childType, int $limit = 10, int $skip = 0)
    {
        return $this->oRepository->getChildObjectsValsByParent($objectNode->getEntityType()->getSlug(), $objectNode->getSlug(), $childType->getSlug(), $limit, $skip);
    }

    public function getParentTypeBySlug($objectNode, $parentTypeSlug)
    {
        return $this->oRepository->getParentTypeBySlug($objectNode, $parentTypeSlug);
    }

    public function getParentObjectByTypeSlug($objectNode, $parentTypeSlug)
    {
        return $this->oRepository->getParentObjectByTypeSlug($objectNode, $parentTypeSlug);
    }

    /**
     * Escapes string to pass through twig path() function
     *
     * @param string $string
     *
     * @return string
     */
    public function escapeCyrillic(string $string)
    {
        $transformer = new UrlCyrillicTransformer();

        return $transformer->transform($string);
    }

    /**
     * @param string $refType CommentNode constants: 'ref_user' || 'ref_objec' || 'ref_type';
     * @param string $refObjectId Id of thing to comment
     *
     * @return \Symfony\Component\Form\FormView
     */
    public function createCommentForm(string $refType, string $refObjectId)
    {
        $comment = new CommentNode();
        $comment->setRefType($refType);
        $formBuilder = $this->formFactory->createNamedBuilder('comment_form', CommentNodeType::class, $comment);
        $formBuilder->get('refId')->setData($refObjectId);
        /** @var Form $form */
        $form = $formBuilder->add('submit_button', SubmitType::class, ['label' => '<i class="icon edit"></i>Комментировать'])->getForm();

        return $form->createView();
    }

    public function filterInactive($objects)
    {
        if (is_object($objects)) {
            $objects = iterator_to_array($objects);
        }
        $objects = array_filter($objects, function (EntityTypeNode $entityType) {
            return $entityType->isDataType() === ObjectNode::STATUS_ACTIVE;
        });
        return $objects;
    }

    private function getObjectSearchQuery()
    {
        return clone $this->objectSearchQuery;
    }

    /**
     * //ok
     *
     * @param string $typeSlug
     * @param array $params ['cql'=>'', 'params'=> ['name'=>'', 'value'=>'']
     * @param int $limit
     * @param int $skip
     * @return array|mixed
     * @throws \Exception
     */
    public function getObjects(string $typeSlug, array $params = [], int $limit = 5, int $skip = 0)
    {
        $query = $this->getObjectSearchQuery()
            ->addObjectFilters([
                'cql' => 'type.slug = {typeSlug}',
                'params' => [
                    ['name' => 'typeSlug', 'values' => $typeSlug]
                ]]);

        if (!empty($params)) {
            $query->addObjectFilters($params);
        }
        if ($limit > 0) {
            $query
                ->addLimit($limit)
                ->addSkip($skip);
        }
        return $query
            ->addSecondOrder('o.createdAt DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @param ObjectNode $object
     * @param string|array $childTypeSlug
     * @param array $params ['cql'=>'', 'params'=> ['name'=>'', 'value'=>'']
     * @param int $limit
     * @param int $skip
     * @return array|mixed
     * @throws \Exception
     */
    public function getChildObjects(ObjectNode $object, $childTypeSlug, array $params = [], int $limit = 5, int $skip = 0)
    {
        return $this->getChildParentObjects(ObjectSearchQuery::REL_LINK_TO_PARENT, $object, $childTypeSlug, $params, $limit, $skip);
    }

    /**
     * @param ObjectNode $object
     * @param string|array $childTypeSlug
     * @param array $params ['cql'=>'', 'params'=> ['name'=>'', 'value'=>'']
     * @param int $limit
     * @param int $skip
     * @return array|mixed
     * @throws \Exception
     */
    public function getParentObjects(ObjectNode $object, $childTypeSlug, array $params = [], int $limit = 5, int $skip = 0)
    {
        return $this->getChildParentObjects(ObjectSearchQuery::REL_LINK_TO_CHILD, $object, $childTypeSlug, $params, $limit, $skip);
    }

    /**
     * @param bool $linkToParent
     * @param ObjectNode $object
     * @param string|array $childTypeSlug
     * @param array $params ['cql'=>'', 'params'=> ['name'=>'', 'value'=>'']
     * @param int $limit
     * @param int $skip
     * @return array|mixed
     * @throws \Exception
     */
    private function getChildParentObjects(bool $linkToParent = ObjectSearchQuery::REL_LINK_TO_PARENT, ObjectNode $object, $childTypeSlug, array $params = [], int $limit = 5, int $skip = 0)
    {

        if (empty($childTypeSlug))
            return null;

        $query = $this->getObjectSearchQuery();
        $query->addObjectFilters([
            'cql' => is_array($childTypeSlug) ? 'type.slug in {typeSlug}' : 'type.slug = {typeSlug}',
            'params' => [
                ['name' => 'typeSlug', 'values' => $childTypeSlug],
            ]]);
        $query->addParentChildRelations($object->getId(), $linkToParent);

        if (!empty($params)) {
            $query->addObjectFilters($params);
        }
        if ($limit > 0) {
            $query
                ->addLimit($limit)
                ->addSkip($skip);
        }
        return $query
            ->addSecondOrder('o.createdAt DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $entityTypeSlug
     * @return EntityTypeNode|null
     */
    public function getEntityTypeNode(string $entityTypeSlug)
    {
        return $this->etRepository->findOneBy(['slug' => $entityTypeSlug]);

    }
}