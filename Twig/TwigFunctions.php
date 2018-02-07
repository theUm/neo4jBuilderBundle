<?php

namespace Nodeart\BuilderBundle\Twig;

use FrontBundle\Helpers\UrlCyrillicTransformer;
use GraphAware\Neo4j\OGM\EntityManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
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
    private $objectCache;
    private $objectSearchQuery;

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
            // gets object`s fields and values
            new \Twig_SimpleFunction('getFields', [$this, 'getFields']),
            // gets object`s single values
            new \Twig_SimpleFunction('getField', [$this, 'getField']),
            // returns objects`s field type by slug
            new \Twig_SimpleFunction('getFieldType', [$this, 'getFieldType']),
            // gets objects`s fields and values array filtered by provided field slugs. May couse additional query to DB.
            new \Twig_SimpleFunction('getFieldsBySlugs', [$this, 'getFieldsBySlugs']),
            // same as getFieldsBySlugs, but only for one field. May couse additional query to DB.
            new \Twig_SimpleFunction('getFieldBySlug', [$this, 'getFieldBySlug']),

            // transforms fieldValueNode to string
            new \Twig_SimpleFunction('transformPair', [$this, 'stringifyFieldPair']),
            // get/fetch fields and transform them to strings if it is possible
            new \Twig_SimpleFunction('getCardFields', [$this, 'getCardFields'], ['needs_environment' => true]),

            // gets object`s child objects list of specific EntityType
            new \Twig_SimpleFunction('getMultipleChildObjectsByParent', [$this, 'getMultipleChildObjectsByParent']),
            // gets object`s siblings (same EntityType)
            new \Twig_SimpleFunction('getObjectSiblings', [$this, 'getObjectSiblings']),

            // get objects list by provided value
            new \Twig_SimpleFunction('getObjectsByValue', [$this, 'getObjectsByValue']),

            // gets object`s child objects list of specific EntityType
            new \Twig_SimpleFunction('getChildObjectsByType', [$this, 'getChildObjectsByType']),
            // gets object`s child objects AND their FIELDS & VALUES of single specific EntityType
            new \Twig_SimpleFunction('getChildObjectsValsByParent', [$this, 'getChildObjectsValsByParent']),
            // gets specific parent type by slug
            new \Twig_SimpleFunction('getParentTypeBySlug', [$this, 'getParentTypeBySlug']),
            // gets specific parent object type by type slug. returns object or null
            new \Twig_SimpleFunction('getParentObjectByTypeSlug', [$this, 'getParentObjectByTypeSlug']),

            // shorthand to get webpath && thumbnail fieldValue. analog of {{ asset(getField(object, 'FIELD_NAME').webPath)|imagine_filter('FILTER_NAME') }}
            new \Twig_SimpleFunction('getFieldThumbs', [$this, 'getFieldThumbs']),

            // shorthand to get webpath && thumbnail fieldValue. analog of {{ asset(getField(object, 'FIELD_NAME').webPath)|imagine_filter('FILTER_NAME') }}
            new \Twig_SimpleFunction('getMainFields', [$this, 'getMainFields']),

            // create form to post comment
            new \Twig_SimpleFunction('createCommentForm', [$this, 'createCommentForm']),

            // finds list of objects with fields, based on filters
            new \Twig_SimpleFunction('getObjects', [$this, 'getObjects']),
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

    public function getCardFields(\Twig_Environment $environment, ObjectNode $object, array $fields, $typeKey = 'type', $valueKey = 'val')
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
            $pair = $this->getFieldBySlug($object, $field);
            return $this->stringifyFieldPair($pair, $typeKey, $valueKey);
        } else {
            $nameHtml = $this->environment->createTemplate($field[$typeKey]);
            $valueHtml = $this->environment->createTemplate($field[$valueKey]);

            return ['type' => $nameHtml, 'val' => $valueHtml, 'isTemplate' => true];
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
    public function getFieldBySlug(ObjectNode $objectNode, string $slug)
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
    public function stringifyFieldPair($pair, $etIndex = 'type', $fvIndex = 'val')
    {
        /** @var TypeFieldNode $fieldType */
        $fieldType = $pair[$etIndex];
        $fieldValues = $pair[$fvIndex];

        if ($fieldType->isCollection()) {
            $transformedFieldValStrings[$fieldType->getSlug()] = [];
            foreach ($fieldValues as $fielVal) {
                $transformedFieldValStrings[$fieldType->getSlug()][] = TypeFieldValuePairTransformer::transformValueToView($fielVal, $fieldType);
            }
            $transformedFieldValStrings = join(', ', $transformedFieldValStrings[$fieldType->getSlug()]);
        } else {
            $transformedFieldValStrings = TypeFieldValuePairTransformer::transformValueToView(array_shift($fieldValues), $fieldType);
        }

        $res = ['type' => $fieldType, 'val' => $transformedFieldValStrings, 'isTemplate' => false];

        return $res;
    }

    public function getMainFields(ObjectNode $object, $asArray = true, $delimiter = ',')
    {
        $mainFieldVals = [];
        $fields = $this->getFields($object);
        /** @var FieldAndValue $fieldAndValue */
        foreach ($fields as $fieldSlug => $fieldAndValue) {
            /** @var TypeFieldNode $typeNode */
            $typeNode = $fieldAndValue['type'];
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
            $val = (!$fields[$type]['type']->isCollection() && (count($fields[$type]['val']) == 1)) ? $fields[$type]['val'][0] : $fields[$type]['val'];
            // if empty array on single value
            if (!$fields[$type]['type']->isCollection() && empty($val)) {
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
     * @param ObjectNode $objectNode
     * @param array $childSlugs
     * @param int $limit
     * @param int $skip
     *
     * @return array
     */
    public function getMultipleChildObjectsByParent($objectNode, array $childSlugs = [], int $limit = 10, int $skip = 0)
    {
        if (!isset($this->objectsCache[$objectNode->getId()]['childDataObjects'])) {
            $childObjectsStruct = $this->oRepository->findMultipleChildObjectsByParent($objectNode->getEntityType()->getSlug(), $objectNode->getSlug(), $childSlugs, $limit, $skip);
        } else {
            $childObjectsStruct = $this->objectsCache[$objectNode->getId()]['childDataObjects'];
        }

        return $childObjectsStruct;
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
        $siblings = $this->oRepository->findObjectSiblingsWithFields($objectNode, $limit, $skip, $parentSlug, $valuesFilters);
        return $siblings;
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

    public function getObjectsByValue($entityType, $entityTypeField, $value, int $limit = 10, int $skip = 0)
    {
        return $this->oRepository->findObjectsByValue($entityType, $entityTypeField, $value, $limit, $skip);
    }


    public function filterInactive($objects)
    {
        if (is_object($objects)) {
            $objects = iterator_to_array($objects);
        }
        $filteredObjects = [];
        /** @var ObjectNode $object */
        foreach ($objects as $object) {
            if ($object->getStatus() === ObjectNode::STATUS_ACTIVE)
                $filteredObjects[] = $object;
        }
        return $filteredObjects;
    }

    private function getObjectSearchQuery()
    {
        return clone $this->objectSearchQuery;
    }

    /**
     * @param string $typeSlug
     * @param array $params ['cql'=>'', 'params'=> ['name'=>'', 'value'=>'']
     * @param int $limit
     * @param int $skip
     * @return array|mixed
     * @throws \Exception
     */
    public function getObjects(string $typeSlug, array $params, int $limit = 5, int $skip = 0)
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
}