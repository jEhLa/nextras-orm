<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Repository\PersistenceHelper;
use Nextras\Orm\Repository\RemovalHelper;
use function array_keys;
use function array_merge;
use function get_class;
use function is_object;
use function is_string;


class Model implements IModel
{
	use SmartObject;


	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity[] $persisted, IEntity $removed): void>
	 */
	public $onFlush = [];

	/** @var IRepositoryLoader */
	private $loader;

	/** @var MetadataStorage */
	private $metadataStorage;

	/**
	 * @var array
	 * @phpstan-var array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     }
	 */
	private $configuration;


	/**
	 * Creates repository list configuration.
	 * @param IRepository[]|string[] $repositories
	 * @phpstan-param  array<string, class-string<IRepository<IEntity>>|IRepository<IEntity>> $repositories
	 * @phpstan-return array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     }
	 */
	public static function getConfiguration(array $repositories): array
	{
		$config = [[], [], []];
		foreach ($repositories as $name => $repository) {
			/**
			 * @var string $className
			 * @phpstan-var class-string<IRepository<IEntity>> $className
			 */
			$className = is_object($repository) ? get_class($repository) : $repository;
			$config[0][$className] = true;
			$config[1][$name] = $className;
			foreach ($repository::getEntityClassNames() as $entityClassName) {
				$config[2][$entityClassName] = $className;
			}
		}
		return $config;
	}


	/**
	 * @phpstan-param array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     } $configuration
	 */
	public function __construct(
		array $configuration,
		IRepositoryLoader $repositoryLoader,
		MetadataStorage $metadataStorage
	)
	{
		$this->loader = $repositoryLoader;
		$this->metadataStorage = $metadataStorage;
		$this->configuration = $configuration;
	}


	public function hasRepositoryByName(string $name): bool
	{
		return isset($this->configuration[1][$name]);
	}


	public function getRepositoryByName(string $name): IRepository
	{
		if (!isset($this->configuration[1][$name])) {
			throw new InvalidArgumentException("Repository '$name' does not exist.");
		}
		return $this->getRepository($this->configuration[1][$name]);
	}


	public function hasRepository(string $className): bool
	{
		return isset($this->configuration[0][$className]);
	}


	public function getRepository(string $className): IRepository
	{
		if (!isset($this->configuration[0][$className])) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");
		}
		$repository = $this->loader->getRepository($className);
		return $repository;
	}


	public function getRepositoryForEntity($entity): IRepository
	{
		$entityClassName = is_string($entity) ? $entity : get_class($entity);
		if (!isset($this->configuration[2][$entityClassName])) {
			throw new InvalidArgumentException("Repository for '$entityClassName' entity does not exist.");
		}
		return $this->getRepository($this->configuration[2][$entityClassName]);
	}


	public function getMetadataStorage(): MetadataStorage
	{
		return $this->metadataStorage;
	}


	/** {@inheritdoc} */
	public function persist(IEntity $entity, bool $withCascade = true): IEntity
	{
		$queue = PersistenceHelper::getCascadeQueue($entity, $this, $withCascade);
		foreach ($queue as $object) {
			if ($object instanceof IEntity) {
				$repository = $this->configuration[2][get_class($object)];
				$this->loader->getRepository($repository)->doPersist($object);
			} elseif ($object instanceof IRelationshipCollection) {
				$object->doPersist();
			}
		}
		return $entity;
	}


	/** {@inheritdoc} */
	public function persistAndFlush(IEntity $entity): IEntity
	{
		$this->persist($entity);
		$this->flush();
		return $entity;
	}


	/** {@inheritdoc} */
	public function remove(IEntity $entity, bool $withCascade = true): IEntity
	{
		$queuePersist = $queueRemove = [];
		RemovalHelper::getCascadeQueueAndSetNulls($entity, $this, $withCascade, $queuePersist, $queueRemove);
		foreach ($queuePersist as $object) {
			if ($object instanceof IEntity) {
				$repository = $this->configuration[2][get_class($object)];
				$this->loader->getRepository($repository)->doPersist($object);
			} elseif ($object instanceof IRelationshipCollection) {
				$object->doPersist();
			}
		}
		foreach ($queueRemove as $object) {
			$repository = $this->configuration[2][get_class($object)];
			$this->loader->getRepository($repository)->doRemove($object);
		}
		return $entity;
	}


	/** {@inheritdoc} */
	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->remove($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	/** {@inheritdoc} */
	public function flush(): void
	{
		$allPersisted = [];
		$allRemoved = [];
		foreach ($this->getLoadedRepositories() as $repository) {
			[$persisted, $removed] = $repository->doFlush();
			$allPersisted = array_merge($allPersisted, $persisted);
			$allRemoved = array_merge($allRemoved, $removed);
		}

		$this->onFlush($allPersisted, $allRemoved);
	}


	/** {@inheritdoc} */
	public function clear(): void
	{
		foreach ($this->getLoadedRepositories() as $repository) {
			$repository->doClear();
		}
	}


	/** {@inheritdoc} */
	public function refreshAll(bool $allowOverwrite = false): void
	{
		foreach ($this->getLoadedRepositories() as $repository) {
			$repository->doRefreshAll($allowOverwrite);
		}
	}


	/**
	 * Returns repository by name.
	 * @phpstan-return IRepository<\Nextras\Orm\Entity\IEntity>
	 */
	public function &__get(string $name): IRepository
	{
		$repository = $this->getRepositoryByName($name);
		return $repository;
	}


	/**
	 * @return IRepository[]
	 * @phpstan-return list<IRepository<\Nextras\Orm\Entity\IEntity>>>
	 */
	private function getLoadedRepositories()
	{
		$repositories = [];
		foreach (array_keys($this->configuration[0]) as $className) {
			if ($this->loader->isCreated($className)) {
				$repositories[] = $this->loader->getRepository($className);
			}
		}

		return $repositories;
	}
}
