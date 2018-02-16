<?php

namespace Nodeart\BuilderBundle\Controller;

use FrontBundle\Controller\Base\BaseController;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\UserNodeRepository;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Entity\UserObjectReaction;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LikeController extends BaseController
{

    const ACTION_LIKE = 'like';
    const ACTION_DISLIKE = 'dislike';

    /**
     * @Route("/like/{id}/{action}", name="user_like_object", defaults={"action"=LikeController::ACTION_LIKE})
     * @param int $id
     * @param string $action
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function likeAction(int $id, string $action = self::ACTION_LIKE, Request $request)
    {

        $response = new JsonResponse();

        $checkAction = $this->checkAction($action, $response);
        if ($checkAction['failed'])
            return $checkAction['response'];

        $nm = $this->get('neo.app.manager');
        /** @var ObjectNodeRepository $objectRepo */
        $objectRepo = $nm->getRepository(ObjectNode::class);
        /** @var UserNodeRepository $userRepo */
        $userRepo = $nm->getRepository(UserNode::class);
        /** @var ObjectNode $objectNode */
        $objectNode = $objectRepo->findOneById($id);

        $user = $this->getUser();
        $checkUser = $this->checkUser($user, $response);
        if ($checkUser['failed'])
            return $checkUser['response'];

        $checkObjectNode = $this->checkObject($objectNode, $response);
        if ($checkObjectNode['failed'])
            return $checkObjectNode['response'];

        /** @var array['liked'=>(bool), 'disliked'=>(bool)] $oldUserObjectReaction */
        $oldUserObjectReactionArray = $userRepo->getSingleUserObjectReaction($user, $objectNode);

        // update object likes/dislikes counters
        $this->updateObjectCounters($oldUserObjectReactionArray, $action, $objectNode);

        $nm->persist($objectNode);
        $userObjectReaction = new UserObjectReaction($user, $objectNode);
        // switcher for "liked" bool
        $userObjectReaction->setLiked($action === self::ACTION_LIKE && !$oldUserObjectReactionArray['liked']);
        // switcher for "disliked" bool
        $userObjectReaction->setDisliked($action === self::ACTION_DISLIKE && !$oldUserObjectReactionArray['disliked']);
        $nm->flush();
        $userRepo->updateUserObjectReaction($user, $objectNode, $userObjectReaction);

        $response->setData([
            'status' => 'updated',
            'action' => $action,
            'likesNewValue' => $objectNode->getLikes(),
            'dislikesNewValue' => $objectNode->getDisLikes(),
        ]);

        return $response;
    }

    private function checkAction(string $action, JsonResponse $response)
    {
        $checkFailed = false;
        $possibleActions = [
            self::ACTION_LIKE,
            self::ACTION_DISLIKE
        ];

        if (!in_array($action, $possibleActions)) {
            $response->setData([
                'status' => 'bad_action',
                'message' => 'Second url param must one of the following: "' . implode('", "', $possibleActions) . '"',
            ]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $checkFailed = true;
        }
        return ['failed' => $checkFailed, 'response' => $response];
    }

    private function checkUser($user, JsonResponse $response)
    {
        $checkFailed = false;
        if (is_null($user)) {
            $response->setData([
                'status' => 'not_found',
                'message' => 'User not found',
            ]);
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $checkFailed = true;
        }
        return ['failed' => $checkFailed, 'response' => $response];
    }

    private function checkObject($objectNode, JsonResponse $response)
    {
        $checkFailed = false;
        if (!$objectNode) {
            $response->setData([
                'status' => 'not_found',
                'message' => 'Object not found',
            ]);
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $checkFailed = true;
        }

        return ['failed' => $checkFailed, 'response' => $response];
    }

    private function updateObjectCounters(array $oldUORArray, string $action, ObjectNode $objectNode)
    {
        $likes = $objectNode->getLikes();
        $dislikes = $objectNode->getDisLikes();
        if ($action === self::ACTION_LIKE) {
            $objectNode->setLikes($oldUORArray['liked'] ? --$likes : ++$likes);
            $objectNode->setDislikes($oldUORArray['disliked'] ? --$dislikes : $dislikes);
        }

        if ($action === self::ACTION_DISLIKE) {
            $objectNode->setDislikes($oldUORArray['disliked'] ? --$dislikes : ++$dislikes);
            $objectNode->setLikes($oldUORArray['liked'] ? --$likes : $likes);
        }
    }

}