<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Nextras\Orm\Entity\IEntity;


class EntityIterator implements IEntityIterator
{
	/** @var int */
	private $position = 0;

	/** @var array */
	private $data;

	/** @var array */
	private $iteratable;

	/** @var bool */
	private $hasSubarray = false;

	/** @var array */
	private $preloadCache;


	public function __construct(array $data)
	{
		$this->data = $data;
		$this->setDataIndex(null);
	}


	public function setDataIndex($index)
	{
		if (isset($index)) {
			if (!isset($this->data[$index])) {
				$this->data[$index] = [];
			}
			$this->iteratable = & $this->data[$index];
			$this->hasSubarray = true;
		} else {
			$this->iteratable = & $this->data;
			$this->hasSubarray = false;
		}

		$this->rewind();
	}


	public function next()
	{
		++$this->position;
	}


	/**
	 * @return IEntity
	 */
	public function current()
	{
		if (!isset($this->iteratable[$this->position])) {
			return null;
		}

		$current = $this->iteratable[$this->position];
		$current->setPreloadContainer($this);
		return $current;
	}


	public function key()
	{
		return $this->position;
	}


	public function valid()
	{
		return isset($this->iteratable[$this->position]);
	}


	public function rewind()
	{
		$this->position = 0;
	}


	public function count()
	{
		return count($this->iteratable);
	}


	public function getPreloadValues($property)
	{
		if (isset($this->preloadCache[$property])) {
			return $this->preloadCache[$property];
		}

		$values = [];

		if ($this->hasSubarray) {
			foreach ($this->data as $block) {
				foreach ($block as $entity) {
					//property may not exist when using STI
					if ($entity->getMetadata()->hasProperty($property)) {
						$values[] = $entity->getRawValue($property);
					}
				}
			}
		} else {
			foreach ($this->data as $entity) {
				//property may not exist when using STI
				if ($entity->getMetadata()->hasProperty($property)) {
					$values[] = $entity->getRawValue($property);
				}
			}
		}

		return $this->preloadCache[$property] = $values;
	}
}
