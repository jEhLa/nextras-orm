<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $test1 {default 0}
 * @property int $test2 {default true}
 * @property int $test3 {default false}
 * @property int $test4 {default null}
 * @property int $test5 {default self::DEF_VALUE_1}
 * @property int $test6 {default static::DEF_VALUE_2}
 * @property int $test7 {default EnumTestEntity::DEF_VALUE_1}
 */
class EnumTestEntity
{
	const DEF_VALUE_1 = 1;
	const DEF_VALUE_2 = NULL;
}

/**
 * @property int $test {default self::UNKNWON}
 */
class Unknown
{
}


class AnnotationParserParseDefaultTest extends TestCase
{

	public function testBasics()
	{
		$dependencies = [];
		$parser = new AnnotationParser();
		$metadata = $parser->parseMetadata('Nextras\Orm\Tests\Entity\Reflection\EnumTestEntity', $dependencies);

		Assert::same('0', $metadata->getProperty('test1')->defaultValue);
		Assert::same(TRUE, $metadata->getProperty('test2')->defaultValue);
		Assert::same(FALSE, $metadata->getProperty('test3')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test4')->defaultValue);
		Assert::same(1, $metadata->getProperty('test5')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test6')->defaultValue);
		Assert::same(1, $metadata->getProperty('test7')->defaultValue);
	}


	public function testUnknown()
	{
		Assert::throws(function() {
			$dependencies = [];
			$parser = new AnnotationParser();
			$parser->parseMetadata('Nextras\Orm\Tests\Entity\Reflection\Unknown', $dependencies);
		}, 'Nextras\Orm\InvalidArgumentException', 'Constant Nextras\Orm\Tests\Entity\Reflection\Unknown::UNKNWON required by default macro in Nextras\Orm\Tests\Entity\Reflection\Unknown::$test not found.');
	}

}


$test = new AnnotationParserParseDefaultTest($dic);
$test->run();
