<?php

namespace Nodeart\BuilderBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends Controller
{
    /**
     * ajax search for autocomplete fields
     * @todo: what a mess :/ get rid of that filthy slashes
     * @Route("/s/{label}/t", name="semantic_search_type")
     * @Route("/s/{label}/t/", name="semantic_search_type_slash")
     * @Route("/s/{label}/t/{parentAttrValue}", name="semantic_search_type_val")
     *
     * @param string $label
     * @param string $parentAttrValue
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function semanticSearch($label = '', $parentAttrValue = '')
    {
        $searchResults = $this->get('semantic.input.search')->search($label, $parentAttrValue);
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        $response->setData($searchResults);

        return $response;
    }

    /**
     * ajax search for autocomplete fields
     * @todo: what a mess :/ get rid of that filthy slashes
     * @Route("/s/{label}/v/{parentAttrValue}", name="semantic_search_child")
     * @Route("/s/{label}/v/{parentAttrValue}/", name="semantic_search_child_slash")
     * @Route("/s/{label}/v/{parentAttrValue}/{value}", name="semantic_search_child_val")
     *
     * @param string $label
     * @param string $parentAttrValue
     * @param string $value
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function semanticSearchChilds($label = '', $parentAttrValue = '', $value = '', Request $request)
    {
        $value = (!is_null($request->request->get('value'))) ? $request->request->get('value') : $value;
        $searchResults = $this->get('semantic.input.search')->search($label, $parentAttrValue, $value);
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        $response->setData($searchResults);

        return $response;
    }

    /**
     * ajax search for object attributes autocomplete fields
     * @todo: what a mess :/ get rid of that filthy slashes
     * @Route("/builder/s/{entityType}/a/{attr}", name="semantic_search_attrib")
     * @Route("/builder/s/{entityType}/a/{attr}/", name="semantic_search_attrib_slash")
     * @Route("/builder/s/{entityType}/a/{attr}/{value}", name="semantic_search_value")
     *
     * @param string $entityType
     * @param string $attr
     * @param string $value
     * @return JsonResponse
     * @throws \GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface
     */
    public function searchObjectAttribute(string $entityType, string $attr, string $value = null)
    {
        $foundData = [];
        $searchQuery = $this->get('neo.app.manager')->createQuery();
        $likePart = '';
        if (!is_null($value)) {
            $likePart = 'AND {attr} =~ {value}';
            $searchQuery->setParameter('value', '.*' . preg_quote($value) . '.*');
        }

        $searchQuery->setCQL("MATCH (et:EntityType)<--(o:Object) WHERE et.slug = {entityType} $likePart AND o.$attr IS NOT null RETURN o.$attr as name");
        $searchQuery->setParameter('entityType', $entityType);
        $searchQuery->setParameter('attr', 'o.' . $attr);

        foreach ($searchQuery->getResult() as $row) {
            $foundData[] = [
                'id' => $row['name'],
                'name' => $row['name'],
                'value' => $row['name'],
            ];
        }

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        $response->setData(['success' => true, 'results' => $foundData]);

        return $response;
    }


}