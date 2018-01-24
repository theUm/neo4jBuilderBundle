<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use Nodeart\BuilderBundle\Entity\CommentNode;

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

    protected function getCreateRelationsQuery(bool $isChildsLink): string
    {
        return '';
    }

    protected function getDeleteRelationsQuery(bool $isChildsLink): string
    {
        return '';
    }
}