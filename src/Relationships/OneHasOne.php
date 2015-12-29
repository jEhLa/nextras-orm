<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;


class OneHasOne extends HasOne
{
	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionOneHasOne($this->metadata, $this->parent);
	}


	protected function modify()
	{
		$this->isModified = TRUE;
		if ($this->metadata->relationship->isMain) {
			$this->parent->setAsModified($this->metadata->name);
		}
	}


	protected function updateRelationship($oldEntity, $newEntity, $allowNull)
	{
		$key = $this->metadata->relationship->property;
		if (!$key) {
			return;
		}

		$this->updatingReverseRelationship = TRUE;
		if ($oldEntity && $oldEntity->hasValue($key) && $oldEntity->getValue($key) === $this->parent) {
			$oldEntity->getProperty($key)->set(NULL, $allowNull);
		}
		if ($newEntity && (!$newEntity->hasValue($key) || $newEntity->getValue($key) !== $this->parent)) {
			$newEntity->getProperty($key)->set($this->parent, $allowNull);
		}
		$this->updatingReverseRelationship = FALSE;
	}
}
