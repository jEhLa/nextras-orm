<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @method Thread|Comment|NULL getById($id)
 */
class ContentsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [ThreadCommentCommon::class, Comment::class, Thread::class];
	}


	public function getEntityClassName(array $data): string
	{
		return $data['type'] === 'comment' ? Comment::class : Thread::class;
	}
}
