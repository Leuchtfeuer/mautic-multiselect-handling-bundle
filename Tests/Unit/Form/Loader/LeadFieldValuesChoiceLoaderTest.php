<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Tests\Unit\Form\Loader;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldValuesChoiceLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LeadFieldValuesChoiceLoaderTest extends TestCase
{
    public function testLoadChoiceListCached(): void
    {
        $fieldId  = 11;
        $fieldId2 = 22;

        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::exactly(2))
            ->method('getId')
            ->willReturn($fieldId);
        $leadField1->expects(self::exactly(2))
            ->method('getName')
            ->willReturn('Name1');
        $leadField1->expects(self::exactly(2))
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 1 Value 1',
                    'value' => 'field_1_value_1',
                ], [
                    'label' => 'Field 1 Value 2',
                    'value' => 'field_1_value_2',
                ]],
            ]);

        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::exactly(2))
            ->method('getId')
            ->willReturn($fieldId2);
        $leadField2->expects(self::exactly(2))
            ->method('getName')
            ->willReturn('Name2');
        $leadField2->expects(self::exactly(2))
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 2 Value 1',
                    'value' => 'field_2_value_1',
                ], [
                    'label' => 'Field 2 Value 2',
                    'value' => 'field_2_value_2',
                ], [
                    'label' => 'Field 2 Value 3',
                    'value' => 'field_2_value_3',
                ]],
            ]);

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::exactly(2))
            ->method('getFieldsByType')
            ->with('multiselect')
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        $expectedValue = [
            '11-field_1_value_1' => '11-field_1_value_1',
            '11-field_1_value_2' => '11-field_1_value_2',
            '22-field_2_value_1' => '22-field_2_value_1',
            '22-field_2_value_2' => '22-field_2_value_2',
            '22-field_2_value_3' => '22-field_2_value_3',
        ];
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame($expectedValue, $choiceList->getChoices());

        // 2nd call is cached
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame($expectedValue, $choiceList->getChoices());

        // reset and call again will call methods
        $leadFieldChoiceLoader->reset();
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame($expectedValue, $choiceList->getChoices());
        self::assertSame([
            '11-field_1_value_1' => '(Name1) Field 1 Value 1',
            '11-field_1_value_2' => '(Name1) Field 1 Value 2',
            '22-field_2_value_1' => '(Name2) Field 2 Value 1',
            '22-field_2_value_2' => '(Name2) Field 2 Value 2',
            '22-field_2_value_3' => '(Name2) Field 2 Value 3',
        ], $choiceList->getOriginalKeys());
    }

    public function testLoadChoicesForEmptyValuesDoesNotFetch(): void
    {
        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::never())
            ->method('getEntities');

        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        self::assertSame([], $leadFieldChoiceLoader->loadChoicesForValues([]));
    }

    public function testLoadChoicesForEmptyValuesOrderedByValues(): void
    {
        $fieldId  = 11;
        $fieldId2 = 22;

        $values     = ['22-field_2_value_1', '22-field_2_value_2', '11-field_1_value_1', '11-field_1_value_non_existing_anymore'];
        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::once())
            ->method('getId')
            ->willReturn($fieldId);
        $leadField1->expects(self::never())
            ->method('getName');
        $leadField1->expects(self::once())
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 1 Value 1',
                    'value' => 'field_1_value_1',
                ], [
                    'label' => 'Field 1 Value 2',
                    'value' => 'field_1_value_2',
                ]],
            ]);

        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::once())
            ->method('getId')
            ->willReturn($fieldId2);
        $leadField2->expects(self::never())
            ->method('getName');
        $leadField2->expects(self::once())
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 2 Value 1',
                    'value' => 'field_2_value_1',
                ], [
                    'label' => 'Field 2 Value 2',
                    'value' => 'field_2_value_2',
                ], [
                    'label' => 'Field 2 Value 3',
                    'value' => 'field_2_value_3',
                ]],
            ]);

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::once())
            ->method('getEntities')
            ->with(['ids' => [$fieldId2, $fieldId]])
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        unset($values[3]);
        self::assertSame($values, $leadFieldChoiceLoader->loadChoicesForValues($values));
    }

    public function testLoadChoicesForEmptyValuesExpectsProperAlias(): void
    {
        $values = ['22-field_2_value_1-error', '22-field_2_value_2', '11-field_1_value_1', '11-field_1_value_non_existing_anymore'];

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::never())
            ->method('getEntities');

        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is something wrong with the field alias.');
        $leadFieldChoiceLoader->loadChoicesForValues($values);

        self::fail('After exception');
    }

    public function testLoadValuesForChoicesWithEmptyChoices(): void
    {
        $leadFieldRepository   = $this->createMock(LeadFieldRepository::class);
        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        self::assertSame([], $leadFieldChoiceLoader->loadValuesForChoices([]));
    }

    public function testLoadValuesForChoicesOrdersChoices(): void
    {
        $fieldId  = 11;
        $fieldId2 = 22;

        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::exactly(4)) // because the field is first in array it gets called with 4 checks and 2 assignments
            ->method('getId')
            ->willReturn($fieldId);
        $leadField1->expects(self::never())
            ->method('getName');
        $leadField1->expects(self::exactly(4))
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 1 Value 1',
                    'value' => 'field_1_value_1',
                ], [
                    'label' => 'Field 1 Value 2',
                    'value' => 'field_1_value_2',
                ]],
            ]);
        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::exactly(3))
            ->method('getId')
            ->willReturn($fieldId2);
        $leadField2->expects(self::never())
            ->method('getName');
        $leadField2->expects(self::exactly(3))
            ->method('getProperties')
            ->willReturn([
                'list' => [[
                    'label' => 'Field 2 Value 1',
                    'value' => 'field_2_value_1',
                ], [
                    'label' => 'Field 2 Value 2',
                    'value' => 'field_2_value_2',
                ], [
                    'label' => 'Field 2 Value 3',
                    'value' => 'field_2_value_3',
                ]],
            ]);

        $choices = [
            1 => '22-field_2_value_1',
            3 => null,
            4 => '22-field_2_value_2',
            5 => '11-field_1_value_1',
            9 => '11-field_1_value_non_existing_anymore',
        ];

        $leadFieldRepository   = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::once())
            ->method('getFieldsByType')
            ->with('multiselect')
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldValuesChoiceLoader($leadFieldRepository);

        self::assertSame([
            1 => '22-field_2_value_1',
            4 => '22-field_2_value_2',
            5 => '11-field_1_value_1',
        ], $leadFieldChoiceLoader->loadValuesForChoices($choices));
    }
}
