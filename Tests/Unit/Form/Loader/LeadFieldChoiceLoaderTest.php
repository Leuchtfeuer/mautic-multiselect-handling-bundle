<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\Form\Loader;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use PHPUnit\Framework\TestCase;

class LeadFieldChoiceLoaderTest extends TestCase
{
    /**
     * @dataProvider fieldTypeProvider
     */
    public function testLoadChoiceListCached(?bool $isMultiSelect): void
    {
        $fieldId1 = 11;
        $fieldId2 = 22;

        $leadField1 = $this->createMock(LeadField::class);
        // amount of calls is 2: one first fetch on first call, second call is cached, so no methods are called
        // and after reset the method is called again
        $leadField1->expects(self::exactly(2))
            ->method('getId')
            ->willReturn($fieldId1);
        $leadField1->expects(self::exactly(2))
            ->method('getName')
            ->willReturn('Field 1');
        $leadField1->expects(self::exactly(2))
            ->method('getType')
            ->willReturn('Field 1 Type');

        $leadField2 = $this->createMock(LeadField::class);
        $leadField2->expects(self::exactly(2))
            ->method('getId')
            ->willReturn($fieldId2);
        $leadField2->expects(self::exactly(2))
            ->method('getName')
            ->willReturn('Field 2');
        $leadField2->expects(self::exactly(2))
            ->method('getType')
            ->willReturn('Field 2 Type');

        $leadFieldRepository = $this->createMock(LeadFieldRepository::class);

        if (null === $isMultiSelect) {
            $invokedCount = self::exactly(4);
            $leadFieldRepository->expects($invokedCount)
                ->method('getFieldsByType')
                ->willReturnCallback(function (string $alias) use ($leadField2, $leadField1, $invokedCount): array {
                    if (1 === $invokedCount->getInvocationCount()) {
                        self::assertSame('multiselect', $alias);

                        return [$leadField1];
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        self::assertSame('select', $alias);

                        return [$leadField2];
                    }

                    if (3 === $invokedCount->getInvocationCount()) {
                        self::assertSame('multiselect', $alias);

                        return [$leadField1];
                    }

                    if (4 === $invokedCount->getInvocationCount()) {
                        self::assertSame('select', $alias);

                        return [$leadField2];
                    }

                    self::fail('Unknown invocation');
                });
        } elseif (true === $isMultiSelect) {
            $leadFieldRepository->expects(self::exactly(2))
                ->method('getFieldsByType')
                ->with('multiselect')
                ->willReturn([$leadField1, $leadField2]);
        } else {
            $leadFieldRepository->expects(self::exactly(2))
                ->method('getFieldsByType')
                ->with('select')
                ->willReturn([$leadField1, $leadField2]);
        }

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);

        if (null !== $isMultiSelect) {
            $leadFieldChoiceLoader->setType($isMultiSelect);
        }

        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId1 => (string) $fieldId1, $fieldId2 => (string) $fieldId2], $choiceList->getChoices());

        // 2nd call is cached
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId1 => (string) $fieldId1, $fieldId2 => (string) $fieldId2], $choiceList->getChoices());

        // reset and call again will call methods
        $leadFieldChoiceLoader->reset();
        $choiceList = $leadFieldChoiceLoader->loadChoiceList();
        self::assertSame([$fieldId1 => (string) $fieldId1, $fieldId2 => (string) $fieldId2], $choiceList->getChoices());
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

    /**
     * @dataProvider fieldTypeProvider
     */
    public function testLoadValuesForChoicesOrdersChoices(?bool $isMultiSelect): void
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
        if (null === $isMultiSelect) {
            $invokedCount = self::exactly(2);
            $leadFieldRepository->expects($invokedCount)
                ->method('getFieldsByType')
                ->willReturnCallback(function (string $alias) use ($leadField2, $leadField1, $invokedCount): array {
                    if (1 === $invokedCount->getInvocationCount()) {
                        self::assertSame('multiselect', $alias);

                        return [$leadField1];
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        self::assertSame('select', $alias);

                        return [$leadField2];
                    }

                    self::fail('Unknown invocation');
                });
        } elseif (true === $isMultiSelect) {
            $leadFieldRepository->expects(self::once())
                ->method('getFieldsByType')
                ->with('multiselect')
                ->willReturn([$leadField1, $leadField2]);
        } else {
            $leadFieldRepository->expects(self::once())
                ->method('getFieldsByType')
                ->with('select')
                ->willReturn([$leadField1, $leadField2]);
        }

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);
        if (null !== $isMultiSelect) {
            $leadFieldChoiceLoader->setType($isMultiSelect);
        }

        self::assertSame([1 => 22, 10 => 11], $leadFieldChoiceLoader->loadValuesForChoices($choices));
    }

    /**
     * @dataProvider fieldTypeProvider
     */
    public function testLoadFieldsForChoicesOrdersChoices(?bool $isMultiSelect): void
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
        if (null === $isMultiSelect) {
            $invokedCount = self::exactly(2);
            $leadFieldRepository->expects($invokedCount)
                ->method('getFieldsByType')
                ->willReturnCallback(function (string $alias) use ($leadField2, $leadField1, $invokedCount): array {
                    if (1 === $invokedCount->getInvocationCount()) {
                        self::assertSame('multiselect', $alias);

                        return [$leadField1];
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        self::assertSame('select', $alias);

                        return [$leadField2];
                    }

                    self::fail('Unknown invocation');
                });
        } elseif (true === $isMultiSelect) {
            $leadFieldRepository->expects(self::once())
                ->method('getFieldsByType')
                ->with('multiselect')
                ->willReturn([$leadField1, $leadField2]);
        } else {
            $leadFieldRepository->expects(self::once())
                ->method('getFieldsByType')
                ->with('select')
                ->willReturn([$leadField1, $leadField2]);
        }

        $leadFieldChoiceLoader = new LeadFieldChoiceLoader($leadFieldRepository);
        if (null !== $isMultiSelect) {
            $leadFieldChoiceLoader->setType($isMultiSelect);
        }

        self::assertSame([1 => $leadField2, 10 => $leadField1], $leadFieldChoiceLoader->loadFieldsForChoices($choices));
    }

    public static function fieldTypeProvider(): \Generator
    {
        yield 'all' => [null];
        yield 'multi' => [true];
        yield 'single' => [false];
    }
}
