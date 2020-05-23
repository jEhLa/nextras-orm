<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;


use DateTimeImmutable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\InvalidArgumentException;
use ReflectionClass;


/**
 * @phpstan-template E of IEntity
 */
class IdentityMap
{
	/**
	 * @var IRepository
	 * @phpstan-var IRepository<E>
	 */
	private $repository;

	/**
	 * @var E[]
	 * @phpstan-var array<int|string, E|false>
	 */
	private $entities = [];

	/**
	 * @var bool[]
	 * @phpstan-var array<int|string, bool>
	 */
	private $entitiesForRefresh = [];

	/**
	 * @var ReflectionClass[]
	 * @phpstan-var array<class-string<E>, ReflectionClass<E>>
	 */
	private $entityReflections;


	/**
	 * @phpstan-param IRepository<E> $repository
	 */
	public function __construct(IRepository $repository)
	{
		$this->repository = $repository;
	}


	/**
	 * @param array|int|mixed $id
	 */
	public function hasById($id): bool
	{
		$idHash = $this->getIdHash($id);
		return isset($this->entities[$idHash]);
	}


	/**
	 * @param array|int|mixed $id
	 * @return IEntity|null|false
	 * @phpstan-return E|null|false
	 */
	public function getById($id)
	{
		$idHash = $this->getIdHash($id);
		if (!isset($this->entities[$idHash])) {
			return null;
		}

		$entity = $this->entities[$idHash];
		if ($entity instanceof IEntityHasPreloadContainer) {
			$entity->setPreloadContainer(null);
		}
		return $entity;
	}


	/**
	 * @phpstan-param E $entity
	 */
	public function add(IEntity $entity): void
	{
		$id = $this->getIdHash($entity->getPersistedId());
		$this->entities[$id] = $entity;
	}


	/**
	 * @param array|int|mixed $id
	 */
	public function remove($id): void
	{
		$idHash = $this->getIdHash($id);
		$this->entities[$idHash] = false;
		unset($this->entitiesForRefresh[$idHash]);
	}


	/**
	 * @param array<string, mixed> $data
	 * @phpstan-return E|null
	 */
	public function create(array $data): ?IEntity
	{
		$entity = $this->createEntity($data);
		$id = $this->getIdHash($entity->getPersistedId());

		if (isset($this->entities[$id])) {
			$this->repository->detach($entity);
			if (!$this->entities[$id]) {
				return null;
			}
			$entity = $this->entities[$id];
			if (isset($this->entitiesForRefresh[$id])) {
				$entity->onRefresh($data);
				unset($this->entitiesForRefresh[$id]);
			}
			return $entity;
		}

		return $this->entities[$id] = $entity; // = intentionally
	}


	/**
	 * @return IEntity[]
	 * @phpstan-return list<E>
	 */
	public function getAll(): array
	{
		return array_values(array_filter($this->entities));
	}


	public function check(IEntity $entity): void
	{
		if (!in_array(get_class($entity), $this->repository->getEntityClassNames(), true)) {
			throw new InvalidArgumentException("Entity '" . get_class($entity) . "' is not accepted by '" . get_class($this->repository) . "' repository.");
		}
	}


	public function destroyAllEntities(): void
	{
		foreach ($this->entities as $entity) {
			if ($entity) {
				$this->repository->detach($entity);
				$entity->onFree();
			}
		}

		$this->entities = [];
	}


	public function markForRefresh(IEntity $entity): void
	{
		$id = $this->getIdHash($entity->getPersistedId());
		$this->entitiesForRefresh[$id] = true;
	}


	public function isMarkedForRefresh(IEntity $entity): bool
	{
		$id = $this->getIdHash($entity->getPersistedId());
		return isset($this->entitiesForRefresh[$id]);
	}


	/**
	 * @param array<string, mixed> $data
	 * @phpstan-return E
	 */
	protected function createEntity(array $data): IEntity
	{
		$entityClass = $this->repository->getEntityClassName($data);

		if (!isset($this->entityReflections[$entityClass])) {
			$this->entityReflections[$entityClass] = new ReflectionClass($entityClass);
		}

		$entity = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		assert($entity instanceof IEntity);
		$this->repository->attach($entity);
		$entity->onLoad($data);
		return $entity;
	}


	/**
	 * @param mixed $id
	 */
	protected function getIdHash($id): string
	{
		if (!is_array($id)) {
			return $id instanceof DateTimeImmutable
				? $id->format('c.u')
				: (string) $id;
		}

		return implode(
			',',
			array_map(
				function ($id) {
					return $id instanceof DateTimeImmutable
						? $id->format('c.u')
						: (string) $id;
				},
				$id
			)
		);
	}
}
