<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\OGM\Repository\BaseRepository;
use Nodeart\BuilderBundle\Entity\BookmarkNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class BookmarkNodeRepository extends BaseRepository {

    public function findByRefIdAndUser( int $refId, UserNode $user ):?BookmarkNode {
        $query = $this->entityManager->createQuery(
            'MATCH (bm:Bookmark {refId:{refId}})-[:bookmarked]->(user:User) WHERE id(user) = {uId} RETURN bm LIMIT 1' );
        $query->setParameter( 'refId', $refId );
        $query->setParameter( 'uId', $user->getId() );
        $query->addEntityMapping( 'bm', BookmarkNode::class );
        $res = $query->getOneOrNullResult();

        return ( ! is_null( $res ) ) ? $res[0] : $res;
    }

}