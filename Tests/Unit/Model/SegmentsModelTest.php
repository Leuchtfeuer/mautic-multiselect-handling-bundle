<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\Model;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\NonExistingListException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
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
    public function testInvalidSegmentsReturnsNull(array $segmentsData): void
    {
        $fieldId               = 123;
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
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

        $listModel->expects(self::once())
            ->method('getUserLists')
            ->with($segmentAlias)
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        self::assertNull($segmentsModel->getSegments($fieldId, false));
    }

    public function testNotExistingSegmentAndNoCreateNewSegments(): void
    {
        $fieldId               = 123;
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $listModel             = $this->createMock(ListModel::class);
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
            ->with($segmentAlias)
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $this->expectException(NonExistingListException::class);
        $this->expectExceptionMessage('Segment does not exist.');

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        $segmentsModel->getSegments($fieldId, false);

        self::fail('After exception.');
    }

    public function testGetSegmentsOk(): void
    {
        $fieldId                  = 123;
        $segmentAlias             = 'segment_alias';
        $segmentName              = 'Segment name';
        $segmentId                = '112';
        $existingSegmentId        = '441';
        $existingSegmentAlias     = 'existing_segment';
        $existingSegmentName      = 'Existing segment';
        $createdSegmentId         = 3737;
        $createSegmentAlias       = 'create_segment_alias';
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
            [['id' => $segmentId, 'alias' => $segmentAlias]],
            [],
            [['id' => $existingSegmentId, 'alias' => $existingSegmentAlias]],
            [['id' => $removeSegmentId, 'alias' => $removeSegmentAlias]],
        ];
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

        $listModel->expects(self::exactly(4))
            ->method('getUserLists')
            ->withConsecutive([$segmentAlias], [$createSegmentAlias], [$existingSegmentAlias], [$removeSegmentAlias])
            ->willReturnOnConsecutiveCalls($segmentsData[0], $segmentsData[1], $segmentsData[2], $segmentsData[3]);
        $listModel->expects(self::once())
            ->method('saveEntity')
            ->willReturnCallback(static function (LeadList $leadList) use ($createSegmentName, $createdSegmentId, $createSegmentAlias): void {
                self::assertSame($createSegmentAlias, $leadList->getAlias());
                self::assertSame($createSegmentName, $leadList->getName());
                $reflection = new \ReflectionProperty(LeadList::class, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($leadList, $createdSegmentId);
            });

        $segmentsModel = new SegmentsModel($listModel, $leadFieldChoiceLoader);
        self::assertSame([
            $segmentId         => $segmentAlias,
            $createdSegmentId  => $createSegmentAlias,
            $existingSegmentId => $existingSegmentAlias,
            $removeSegmentId   => $removeSegmentAlias,
        ], $segmentsModel->getSegments($fieldId, true));
    }

    public function trueFalse(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function invalidProperties(): array
    {
        return [
            [[]],
            [['list' => 'not array']],
            [['list' => []]],
        ];
    }

    public function invalidSegmentData(): array
    {
        return [
            [['not array']],
            [['not_id' => 'index']],
        ];
    }
}
