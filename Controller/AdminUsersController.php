<?php

namespace Nodeart\BuilderBundle\Controller;

use FrontBundle\Controller\Base\BaseController;
use Nodeart\BuilderBundle\Entity\Repositories\UserNodeRepository;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Services\Pager\Pager;
use Nodeart\BuilderBundle\Services\Pager\Queries\ManageUsersQueries;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminUsersController extends BaseController
{

    const POSSIBLE_ACTIONS = ['activate', 'deactivate', 'approve', 'disapprove'];

    /**
     * Updates uses by get param
     *
     * @Route("/builder/users/update/{action}", name="users_mass_action_update")
     * @param string $action
     * @param Request $request
     *
     * @return Response
     */
    public function processMassActionAction(string $action, Request $request)
    {
        $response = new JsonResponse();

        if (!in_array($action, array_keys(self::POSSIBLE_ACTIONS))) {
            $response->setData(['status' => 'error', 'message' => 'Unknown action']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $response;
        }

        if (!$request->query->has('ids')) {
            $response->setData(['status' => 'error', 'message' => 'No Id parameter provided to update']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $response;
        }

        $ids = $request->query->get('ids');
        if (!is_string($ids)) {
            $response->setData([
                'status' => 'error',
                'message' => 'Id`s must be in string, values separated by comma'
            ]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $response;
        }
        if (empty($ids)) {
            $response->setData(['status' => 'error', 'message' => 'No Id`s provided to update']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $response;
        }

        $ids = array_map('intval', explode(',', $ids));
        $nm = $this->get('neo.app.manager');

        /** @var UserNodeRepository $userRepo */
        $userRepo = $nm->getRepository(UserNode::class);
        if (in_array($action, ['activate', 'deactivate'])) {
            $res = $userRepo->setUsersActive(($action == 'activate'), $ids);
        } else {
            $res = $userRepo->setUsersApproved(($action == 'approve'), $ids);
            $userRepo->setUsersCommentsApproved(($action == 'approve'), $ids);

        }

        $response->setData([
            'status' => 'success',
            'message' => count($res['ids']) . ' node(s) updated',
            'payload' => [
                'status' => $this->get('translator')->trans($action),
                'updatedIds' => $res['ids']
            ]
        ]);

        return $response;
    }

    /**
     * @Route("/builder/users/{page}/{perPage}/{statusFilter}", name="users_list_manage", defaults={"page":1, "perPage":20, "statusFilter":-1} )
     * @param int $page
     * @param int $perPage
     * @param string $statusFilter
     * @param Request $request
     *
     * @return Response
     */
    public function manageListAction(int $page, int $perPage, string $statusFilter, Request $request)
    {
        $nm = $this->get('neo.app.manager');

        $pagerQueries = new ManageUsersQueries($nm);
        $pagerQueries->processOrder($request->query->all());
        $pagerQueries->processFilter($statusFilter);

        /** @var Pager $pager */
        $pager = $this->get('neo.pager');
        $pager->setLimit($request->get('perPage'));
        $pager->createQueries($pagerQueries);

        $masterRequest = $this->get('request_stack')->getMasterRequest();

        return $this->render('BuilderBundle:Users:admin/paged.list.comments.html.twig', [
            'comments' => $pager->paginate(),
            'pager' => $pager->getPaginationData(),
            'masterRoute' => $masterRequest->attributes->get('_route'),
            'masterParams' => $masterRequest->attributes->get('_route_params')
        ]);
    }

}