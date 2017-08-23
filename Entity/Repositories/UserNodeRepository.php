<?php

namespace BuilderBundle\Entity\Repositories;

class UserNodeRepository extends BaseRepository {


	protected function getCreateRelationsQuery( bool $isChildsLink ): string {
		return '';
	}

	protected function getDeleteRelationsQuery( bool $isChildsLink ): string {
		return '';
	}
}