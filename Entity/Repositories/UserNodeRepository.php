<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Entity\UserObjectReaction;

class UserNodeRepository extends BaseRepository
{
    /**
     * @param bool $setActive
     * @param array $ids
     *
     * @return array|null updated nodes
     */
    public function setUsersActive(bool $setActive, array $ids): ?array
    {
        $query = $this->entityManager->createQuery('MATCH (n:User) WHERE id (n) in {ids} SET n.enabled = {status} RETURN collect(id(n)) as ids');
        $query->setParameter('status', $setActive);
        $query->setParameter('ids', $ids);
        $res = $query->getOneResult();

        return $res;
    }

    /**
     * @param bool $setApproved
     * @param array $ids
     *
     * @return array|null updated nodes
     */
    public function setUsersApproved(bool $setApproved, array $ids): ?array
    {
        $query = $this->entityManager->createQuery('MATCH (n:User) WHERE id (n) in {ids} SET n.approved = {status} RETURN collect(id(n)) as ids');
        $query->setParameter('status', $setApproved);
        $query->setParameter('ids', $ids);
        $res = $query->getOneResult();

        return $res;
    }

    /**
     * @param bool $setApproved
     * @param array $ids
     *
     * @return array|null updated nodes
     */
    public function setUsersCommentsApproved(bool $setApproved, array $ids): ?array
    {
        $query = $this->entityManager->createQuery(
            'MATCH (n:User)-[:commented]-(com:Comment) 
            WHERE id (n) IN {ids} AND com.status = {fromStatus}
            SET com.status = {toStatus} RETURN collect(id(com))');
        $query->setParameter('fromStatus', ($setApproved) ? CommentNode::STATUS_INITIAL : CommentNode::STATUS_APPROVED);
        $query->setParameter('toStatus', ($setApproved) ? CommentNode::STATUS_APPROVED : CommentNode::STATUS_DISAPPROVED);
        $query->setParameter('ids', $ids);
        $res = $query->getOneResult();

        return $res;
    }

    /**
     * @param UserNode $user
     * @param ObjectNode $object
     * @param UserObjectReaction $uor
     * @return array|mixed
     * @throws \Exception
     */
    public function updateUserObjectReaction(UserNode $user, ObjectNode $object, UserObjectReaction $uor)
    {
        $query = $this->entityManager->createQuery(
            'MATCH (u:User),(o:Object)
             WHERE id(u) = {uId} AND id(o)={oId}
             MERGE (u)-[r:Reaction]-(o) 
             SET r.liked = {liked}
             SET r.disliked = {disliked}
             SET r.createdAt = {createdAt}'
        );
        $query->setParameter('uId', $user->getId());
        $query->setParameter('oId', $object->getId());

        $query->setParameter('liked', $uor->isLiked());
        $query->setParameter('disliked', $uor->isDisliked());
        $query->setParameter('createdAt', $uor->getCreatedAt()->getTimestamp());

        $res = $query->execute();
        return $res;
    }

    public function getSingleUserObjectReaction(UserNode $userNode, ObjectNode $objectNode)
    {
        $query = $this->entityManager->createQuery(
            'MATCH (u:User)-[r:Reaction]-(o:Object)
             WHERE id(u) = {uId} AND id(o) = {oId}
             RETURN r.liked AS liked, r.disliked AS disliked'
        );
        $query->setParameter('uId', $userNode->getId());
        $query->setParameter('oId', $objectNode->getId());

        return $query->getOneOrNullResult()[0] ?? ['liked' => false, 'disliked' => false];
    }

    protected function getCreateRelationsQuery(bool $isChildsLink): string
    {
        return '';
    }

    protected function getDeleteRelationsQuery(bool $isChildsLink): string
    {
        return '';
    }
}