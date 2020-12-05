<?php

declare(strict_types=1);

namespace Rector\DowngradePhp80\Tests\Rector\Property\DowngradeUnionTypeTypedPropertyRector;

use Iterator;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\DowngradePhp80\Rector\Property\DowngradeUnionTypeTypedPropertyRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @requires PHP = 8.1
 */
final class DowngradeUnionTypeTypedPropertyRectorTest extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function test(SmartFileInfo $fileInfo): void
    {
        $this->doTestFileInfo($fileInfo);
    }

    public function provideData(): Iterator
    {
        return $this->yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    protected function getRectorClass(): string
    {
        return DowngradeUnionTypeTypedPropertyRector::class;
    }

    protected function getPhpVersion(): int
    {
        return PhpVersionFeature::UNION_TYPES - 1;
    }
}
