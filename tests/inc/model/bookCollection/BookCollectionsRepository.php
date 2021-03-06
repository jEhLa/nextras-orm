<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @method BookCollection|NULL getById($id)
 */
class BookCollectionsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [BookCollection::class];
	}
}
