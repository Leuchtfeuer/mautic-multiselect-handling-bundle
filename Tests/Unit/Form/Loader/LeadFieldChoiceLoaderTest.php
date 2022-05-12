<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Tests\Unit\Form\Loader;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use PHPUnit\Framework\TestCase;

class LeadFieldChoiceLoaderTest extends TestCase
{
    public function testLoadChoiceListCached(): void
    {
        $fieldId = 11;

        $leadField = $this->createMock(LeadField::class);
        $leadField->expects(self::exactly(2))
            ->method('getId')
            ->willReturn($fieldId);

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::exactly(2))
            ->method('getFieldsByType')
            ->with('multiselect')
            ->willReturn([$leadField]);

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId => (string) $fieldId], $choiceList->getChoices());

        // 2nd call is cached
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId => (string) $fieldId], $choiceList->getChoices());

        // reset and call again will call methods
        $leadFieldChoiceLoader->reset();
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId => (string) $fieldId], $choiceList->getChoices());
    }

    public function testLoadChoicesForEmptyValuesDoesNotFetch(): void
    {
        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::never())
            ->method('getEntities');

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        self::assertSame([], $leadFieldChoiceLoader->loadChoicesForValues([]));
    }

    public function testLoadChoicesForEmptyValuesOrderedByValues(): void
    {
        $values     = ['22', '11'];
        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::exactly(2))
            ->method('getId')
            ->willReturn(11);
        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::exactly(2))
            ->method('getId')
            ->willReturn(22);

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::once())
            ->method('getEntities')
            ->with(['ids' => $values])
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        self::assertSame([22, 11], $leadFieldChoiceLoader->loadChoicesForValues($values));
    }

    public function testLoadValuesForChoicesWithEmptyChoices(): void
    {
        $leadFieldRepository   = $this->createMock(LeadFieldRepository::class);
        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        self::assertSame([], $leadFieldChoiceLoader->loadValuesForChoices([]));
    }

    public function testLoadValuesForChoicesOrdersChoices(): void
    {
        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::exactly(3)) // because the field is first in array it gets called with 2 checks and 1 assignment
            ->method('getId')
            ->willReturn(11);
        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::exactly(2))
            ->method('getId')
            ->willReturn(22);

        $choices = [
            1  => 22,
            4  => null,
            10 => 11,
        ];

        $leadFieldRepository   = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::once())
            ->method('getFieldsByType')
            ->with('multiselect')
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        self::assertSame([1 => 22, 10 => 11], $leadFieldChoiceLoader->loadValuesForChoices($choices));
    }

    public function testLoadFieldsForChoicesOrdersChoices(): void
    {
        $leadField1 = $this->createMock(LeadField::class);
        $leadField1->expects(self::exactly(2)) // because the field is first in array it gets called with 2 checks and 1 assignment
            ->method('getId')
            ->willReturn(11);
        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::once())
            ->method('getId')
            ->willReturn(22);

        $choices = [
            1  => 22,
            4  => null,
            10 => 11,
        ];

        $leadFieldRepository   = $this->createMock(LeadFieldRepository::class);
        $leadFieldRepository->expects(self::once())
            ->method('getFieldsByType')
            ->with('multiselect')
            ->willReturn([$leadField1, $leadField2]);

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        self::assertSame([1 => $leadField2, 10 => $leadField1], $leadFieldChoiceLoader->loadFieldsForChoices($choices));
    }
}
