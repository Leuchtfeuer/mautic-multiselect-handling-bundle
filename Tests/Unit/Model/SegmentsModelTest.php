<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\Model;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SegmentsModelTest extends TestCase
{
    /**
     * @dataProvider trueFalse
     */
    public function testInvalidChoices(bool $zeroOrTwo): void
    {
        $fieldId               = 123;
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $listModel             = $this->createMock(ListModel::class);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn($zeroOrTwo ? [] : [1, 2]);

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $this->expectException(InvalidSetupException::class);
        $this->expectExceptionMessage('Invalid setup.');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        $segmentsModel->getSegments($fieldId, false);

        self::fail('After exception.');
    }

    /**
     * @param array<mixed> $properties
     *
     * @dataProvider invalidProperties
     */
    public function testInvalidChoiceProperties(array $properties): void
    {
        $fieldId               = 123;
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $listModel             = $this->createMock(ListModel::class);
        $leadField             = $this->createMock(LeadField::class);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $this->expectException(InvalidSetupException::class);
        $this->expectExceptionMessage('Invalid setup.');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        $segmentsModel->getSegments($fieldId, false);

        self::fail('After exception.');
    }

    /**
     * @param array<mixed> $segmentsData
     *
     * @dataProvider invalidSegmentData
     */
    public function testInvalidSegmentsReturnsEmptyArray(array $segmentsData): void
    {
        $fieldId               = 123;
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $listModel             = $this->getListModel();
        $leadField             = $this->createMock(LeadField::class);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $listModel->expects(self::once())
            ->method('getUserLists')
            ->with($listModel->cleanAlias($segmentAlias, '', 0, '-'))
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        self::assertSame([], $segmentsModel->getSegments($fieldId, false));
    }

    public function testNotExistingSegmentAndNoCreateNewSegments(): void
    {
        $fieldId               = 123;
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $listModel             = $this->getListModel();
        $leadField             = $this->createMock(LeadField::class);
        $segmentsData          = [];

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $listModel->expects(self::once())
            ->method('getUserLists')
            ->with($listModel->cleanAlias($segmentAlias, '', 0, '-'))
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        self::assertSame([], $segmentsModel->getSegments($fieldId, false));
    }

    public function testGetSegmentsOk(): void
    {
        $listModel                = $this->getListModel();
        $fieldId                  = 123;
        $segmentAlias             = 'segment_alias';
        $segmentName              = 'Segment name';
        $segmentId                = '112';
        $existingSegmentId        = '441';
        $existingSegmentAlias     = 'existing_segment';
        $existingSegmentName      = 'Existing segment';
        $createdSegmentId         = 3737;
        $createSegmentAlias       = 'create_segment_alias';
        $createSegmentAliasClean  = $listModel->cleanAlias($createSegmentAlias, '', 0, '-');
        $createSegmentName        = 'Create segment name';
        $removeSegmentId          = 74747;
        $removeSegmentAlias       = 'remove_alias';
        $removeSegmentName        = 'Remove segment name';
        $properties               = ['list' => [
            ['value' => $segmentAlias, 'label' => $segmentName],
            ['value' => $createSegmentAlias, 'label' => $createSegmentName],
            ['value' => $existingSegmentAlias, 'label' => $existingSegmentName],
            ['value' => $removeSegmentAlias, 'label' => $removeSegmentName], ],
        ];
        $segmentsData          = [
            [['id' => $segmentId, 'alias' => $listModel->cleanAlias($segmentAlias, '', 0, '-')]],
            [],
            [['id' => $existingSegmentId, 'alias' => $listModel->cleanAlias($existingSegmentAlias, '', 0, '-')]],
            [['id' => $removeSegmentId, 'alias' => $listModel->cleanAlias($removeSegmentAlias, '', 0, '-')]],
        ];
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $leadField             = $this->createMock(LeadField::class);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $invokedCount = self::exactly(4);
        $listModel->expects($invokedCount)
            ->method('getUserLists')
            ->willReturnCallback(function (string $alias) use ($removeSegmentAlias, $existingSegmentAlias, $createSegmentAliasClean, $segmentsData, $segmentAlias, $listModel, $invokedCount) {
                if (1 === $invokedCount->getInvocationCount()) {
                    self::assertSame($listModel->cleanAlias($segmentAlias, '', 0, '-'), $alias);

                    return $segmentsData[0];
                }

                if (2 === $invokedCount->getInvocationCount()) {
                    self::assertSame($createSegmentAliasClean, $alias);

                    return $segmentsData[1];
                }

                if (3 === $invokedCount->getInvocationCount()) {
                    self::assertSame($listModel->cleanAlias($existingSegmentAlias, '', 0, '-'), $alias);

                    return $segmentsData[2];
                }

                if (4 === $invokedCount->getInvocationCount()) {
                    self::assertSame($listModel->cleanAlias($removeSegmentAlias, '', 0, '-'), $alias);

                    return $segmentsData[3];
                }

                self::fail('Unknown invocation');
            });
        $listModel->expects(self::once())
            ->method('saveEntity')
            ->willReturnCallback(static function (LeadList $leadList) use ($createSegmentName, $createdSegmentId, $createSegmentAliasClean): void {
                self::assertSame($createSegmentAliasClean, $leadList->getAlias());
                self::assertSame($createSegmentName, $leadList->getName());
                $reflection = new \ReflectionProperty(LeadList::class, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($leadList, $createdSegmentId);
            });

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        self::assertSame([
            $segmentId         => $listModel->cleanAlias($segmentAlias, '', 0, '-'),
            $createdSegmentId  => $createSegmentAliasClean,
            $existingSegmentId => $listModel->cleanAlias($existingSegmentAlias, '', 0, '-'),
            $removeSegmentId   => $listModel->cleanAlias($removeSegmentAlias, '', 0, '-'),
        ], $segmentsModel->getSegments($fieldId, true));
    }

    /**
     * @return array<array<bool>>
     */
    public function trueFalse(): array
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @return array<array<mixed>>
     */
    public function invalidProperties(): array
    {
        return [
            [[]],
            [['list' => 'not array']],
            [['list' => []]],
        ];
    }

    /**
     * @return array<array<mixed>>
     */
    public function invalidSegmentData(): array
    {
        return [
            [['not array']],
            [['not_id' => 'index']],
        ];
    }

    /**
     * @return MockObject&ListModel
     */
    private function getListModel(): MockObject
    {
        $platform = $this->createMock(MySQL80Platform::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')
            ->willReturn($platform);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')
            ->willReturn($connection);

        $leadModel = $this->getMockBuilder(ListModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveEntity', 'getUserLists'])
            ->getMock();
        $reflectionObject   = new \ReflectionObject($leadModel);
        $reflectionProperty = $reflectionObject->getProperty('em');
        $reflectionProperty->setValue($leadModel, $entityManager);

        return $leadModel;
    }
}
