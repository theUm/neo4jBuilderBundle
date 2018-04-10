<?php

namespace Nodeart\BuilderBundle\Controller\Base;

use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\UserNodeRepository;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Entity\UserObjectReaction;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class BaseController extends Controller
{

    const EMPTY_USER_REACTIONS = ['liked' => [], 'disliked' => [], 'reported' => []];
    private $user;
    private $userQueried = false;

    /**
     * @param $obj
     * @param $message
     * @param null $route
     * @param array $routeParams
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @internal param array $redirectParams
     */
    protected function redirectNotFound($obj, $message, $route = null, $routeParams = [])
    {
        if (is_null($obj)) {
            $this->addFlash('error', $message);
            if (!is_null($route)) {
                return $this->redirect($this->generateUrl($route, $routeParams));
            }
        }

        return null;
    }

    protected function getBookmarks()
    {
        $bookmarkIds = [];
        $user = $this->getUser();
        if (!is_null($user)) {
            foreach ($user->getBookmarks() as $bm) {
                $bookmarkIds[] = $bm->getRefId();
            }
        }
        return $bookmarkIds;
    }

    /**
     * Refreshes current user entity from DB to use benefits of OGM entity object
     *
     * @return mixed|UserNode|null
     */
    protected function getUser()
    {
        if ($this->userQueried) {
            return $this->user;
        }
        $nm = $this->get('neo.app.manager');
        $user = $this->container->get('security.token_storage')->getToken()->getUser();
        $this->userQueried = true;
        if (!is_object($user)) {
            return null;
        }
        // refresh user
        /** @var UserNodeRepository $oRepository */
        $uRepository = $nm->getRepository(UserNode::class);
        /** @var UserNode $user */
        $user = $uRepository->findOneBy(['email' => $user->getEmail()]);
        if (!$user instanceof UserNode) {
            throw  new AuthenticationCredentialsNotFoundException('User not found');
        }
        $this->user = $user;
        return $user;
    }

    protected function getUserObjectReactions(ObjectNode $object)
    {
        $user = $this->getUser();
        $res = null;
        if (!is_null($user)) {
            $res = $this->get('neo.app.manager')
                ->getRepository(UserNode::class)
                ->getSingleUserObjectReaction($this->getUser(), $object);
        }
        $res = $res ?? new UserObjectReaction($user, $object, false);
        return $res;
    }
}