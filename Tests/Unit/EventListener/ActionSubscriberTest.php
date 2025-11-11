<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActionSubscriberTest extends TestCase
{
    public function testManageFieldActionChecksContext(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        /** @phpstan-ignore-next-line */
        $event        = $this->createMock(CampaignExecutionEvent::class);
        $invokedCount = self::exactly(2);
        $event->expects($invokedCount)
            ->method('checkContext')
            ->willReturnCallback(function (string $eventType) use ($invokedCount) {
                if (1 === $invokedCount->getInvocationCount()) {
                    self::assertSame(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION, $eventType);

                    return false;
                }

                if (2 === $invokedCount->getInvocationCount()) {
                    self::assertSame(ActionSubscriber::MANAGE_SELECT_FIELD_ACTION, $eventType);

                    return false;
                }

                self::fail('Unknown invocation');
            });
        $event->expects(self::never())
            ->method('getConfig');
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    public function testManageFieldActionMissingField(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        /** @phpstan-ignore-next-line */
        $event        = $this->createMock(CampaignExecutionEvent::class);
        $invokedCount = self::exactly(2);
        $event->expects($invokedCount)
            ->method('checkContext')
            ->willReturnCallback(function (string $eventType) use ($invokedCount) {
                if (1 === $invokedCount->getInvocationCount()) {
                    self::assertSame(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION, $eventType);

                    return false;
                }

                if (2 === $invokedCount->getInvocationCount()) {
                    self::assertSame(ActionSubscriber::MANAGE_SELECT_FIELD_ACTION, $eventType);

                    return true;
                }

                self::fail('Unknown invocation');
            });
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value']);
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid event configuration.');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    public function testManageFieldActionMissingFieldInContact(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId = 2376;

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => []]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', UpdateMultiSelectFieldType::FIELD => $fieldId]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    /**
     * @param array<string> $expectedFieldValues
     *
     * @dataProvider manageFieldProvider
     */
    public function testManageFieldActionManagesMultiselectFieldInContact(?string $fieldValue, array $expectedFieldValues): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId    = 2376;
        $fieldAlias = 'field_alias';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias, 'type' => 'multiselect'],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'other'                            => 'value',
                UpdateMultiSelectFieldType::FIELD  => $fieldId,
                UpdateMultiSelectFieldType::ADD    => [$fieldId.'-alias_add_1', $fieldId.'-alias_add_2'],
                UpdateMultiSelectFieldType::REMOVE => [$fieldId.'-alias_remove_1', $fieldId.'-alias_remove_2'],
            ]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->getLeadModel();
        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->with($lead);
        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with($lead, [$fieldAlias => $expectedFieldValues], true);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    /**
     * @return array<int, array<int, array<int, string>|string|null>>
     */
    public function manageFieldProvider(): array
    {
        return [
            [null, [0 => 'alias_add_1', 1 => 'alias_add_2']],
            ['other', ['other', 'alias_add_1', 'alias_add_2']],
            ['alias_add_1', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1|alias_add_1|alias_remove_2', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1|alias_add_1|alias_remove_2|other', ['alias_add_1', 'other', 'alias_add_2']],
        ];
    }

    public function testManageFieldActionManagesSelectFieldInContact(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId    = 2376;
        $fieldAlias = 'field_alias';
        $fieldValue = 'selected';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias, 'type' => 'select'],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'other'                            => 'value',
                UpdateMultiSelectFieldType::FIELD  => $fieldId,
                UpdateMultiSelectFieldType::ADD    => $fieldId.'-alias_add_1',
                UpdateMultiSelectFieldType::REMOVE => [$fieldId.'-alias_remove_1', $fieldId.'-alias_remove_2'], // this actually does not matter, but i'd leave it here
            ]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->getLeadModel();
        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->with($lead);
        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with($lead, [$fieldAlias => 'alias_add_1'], true);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    public function testManageFieldActionAddsLastToSelectFieldInContact(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId    = 2376;
        $fieldAlias = 'field_alias';
        $fieldValue = 'selected';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias, 'type' => 'select'],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'other'                            => 'value',
                UpdateMultiSelectFieldType::FIELD  => $fieldId,
                UpdateMultiSelectFieldType::ADD    => [$fieldId.'-alias_add_1', $fieldId.'-alias_add_2'],
                UpdateMultiSelectFieldType::REMOVE => [$fieldId.'-alias_remove_1', $fieldId.'-alias_remove_2'], // this actually does not matter, but i'd leave it here
            ]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->getLeadModel();
        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->with($lead);
        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with($lead, [$fieldAlias => 'alias_add_2'], true);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    public function testManageFieldActionRemovesFromSelectFieldInContact(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId    = 2376;
        $fieldAlias = 'field_alias';
        $fieldValue = 'alias_remove_1';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias, 'type' => 'select'],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'other'                            => 'value',
                UpdateMultiSelectFieldType::FIELD  => $fieldId,
                UpdateMultiSelectFieldType::ADD    => '', // note empty field here
                UpdateMultiSelectFieldType::REMOVE => [$fieldId.'-'.$fieldValue, $fieldId.'-alias_remove_2'],
            ]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->getLeadModel();
        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->with($lead);
        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with($lead, [$fieldAlias => null], true);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($event);
    }

    public function testManageSegmentsActionChecksContext(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(false);
        $event->expects(self::never())
            ->method('getConfig');
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::never())
            ->method('addToLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    /**
     * @param array<string> $fields
     *
     * @dataProvider missingManageSegmentsField
     */
    public function testManageSegmentsActionMissingField(array $fields): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn($fields);
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::never())
            ->method('addToLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid event configuration.');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    /**
     * @return array<array<array<string>>>
     */
    public function missingManageSegmentsField(): array
    {
        return [
            [['other' => 'value']],
            [[SettingsType::FIELD => 'value']],
            [[SettingsType::CHECKBOX => 'value']],
        ];
    }

    public function testManageSegmentsActionMissingFieldInContact(): void
    {
        $config                    = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        $fieldId = 2376;

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => []]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0']);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::never())
            ->method('addToLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    public function testManageSegmentsActionInvalidSegments(): void
    {
        $config                    = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId    = 2376;
        $fieldAlias = 'field_alias';
        $fieldValue = 'alias_remove_1|alias_add_1|alias_remove_2|other';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0']);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::never())
            ->method('addToLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid setup.');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    public function testManageSegmentsActionManagesSegments(): void
    {
        $fieldId      = 2376;
        $fieldAlias   = 'field_alias';
        $idRemove1    = 3332;
        $idRemove2    = 3344;
        $idAdd1       = 5544;
        $idAdd2       = 5577;
        $aliasRemove1 = 'alias_remove_1';
        $aliasRemove2 = 'alias_remove_2';
        $aliasAdd1    = 'alias_add_1';
        $aliasAdd2    = 'alias_add_2';
        $fieldValue   = $aliasAdd1.'|'.$aliasAdd2;
        $config       = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0']);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $otherSegment = $this->createMock(LeadList::class);
        $otherSegment->expects(self::any())
            ->method('getId')
            ->willReturn(123123);
        $removeSegment2 = $this->createMock(LeadList::class);
        $removeSegment2->expects(self::any())
            ->method('getId')
            ->willReturn($idRemove2);
        $existingAddSegment = $this->createMock(LeadList::class);
        $existingAddSegment->expects(self::any())
            ->method('getId')
            ->willReturn($idAdd2);

        $leadModel = $this->getLeadModel(['saveEntity', 'getLists', 'addToLists', 'removeFromLists']);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::once())
            ->method('getLists')
            ->with($lead)
            ->willReturn([$otherSegment, $removeSegment2, $existingAddSegment]);
        $leadModel->expects(self::once())
            ->method('addToLists')
            ->with($lead, [$idAdd1]);
        $leadModel->expects(self::once())
            ->method('removeFromLists')
            ->with($lead, [$idRemove2]);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn([
                $idRemove1 => $leadModel->cleanAlias($aliasRemove1, '', 0, '-'),
                $idRemove2 => $leadModel->cleanAlias($aliasRemove2, '', 0, '-'),
                $idAdd1    => $leadModel->cleanAlias($aliasAdd1, '', 0, '-'),
                $idAdd2    => $leadModel->cleanAlias($aliasAdd2, '', 0, '-'),
            ]);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    public function testManageSegmentsActionDoNotManagesSegmentsIfAllAreInSync(): void
    {
        $fieldId      = 2376;
        $fieldAlias   = 'field_alias';
        $idRemove1    = 3332;
        $idRemove2    = 3344;
        $idAdd1       = 5544;
        $idAdd2       = 5577;
        $aliasRemove1 = 'alias_remove_1';
        $aliasRemove2 = 'alias_remove_2';
        $aliasAdd1    = 'alias_add_1';
        $aliasAdd2    = 'alias_add_2';
        $fieldValue   = $aliasAdd1.'|'.$aliasAdd2;
        $config       = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias],
            ]]);
        /** @phpstan-ignore-next-line */
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::MANAGE_SEGMENTS_ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0']);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $otherSegment = $this->createMock(LeadList::class);
        $otherSegment->expects(self::any())
            ->method('getId')
            ->willReturn(123123);
        $existingAddSegment = $this->createMock(LeadList::class);
        $existingAddSegment->expects(self::any())
            ->method('getId')
            ->willReturn($idAdd1);
        $existingAddSegment2 = $this->createMock(LeadList::class);
        $existingAddSegment2->expects(self::any())
            ->method('getId')
            ->willReturn($idAdd2);

        $leadModel = $this->getLeadModel(['saveEntity', 'getLists', 'addToLists', 'removeFromLists']);
        $leadModel->expects(self::never())
            ->method('saveEntity');
        $leadModel->expects(self::once())
            ->method('getLists')
            ->with($lead)
            ->willReturn([$otherSegment, $existingAddSegment2, $existingAddSegment]);
        $leadModel->expects(self::never())
            ->method('addToLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn([
                $idRemove1 => $leadModel->cleanAlias($aliasRemove1, '', 0, '-'),
                $idRemove2 => $leadModel->cleanAlias($aliasRemove2, '', 0, '-'),
                $idAdd1    => $leadModel->cleanAlias($aliasAdd1, '', 0, '-'),
                $idAdd2    => $leadModel->cleanAlias($aliasAdd2, '', 0, '-'),
            ]);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($event);
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([
            ActionSubscriber::MANAGE_MULTISELECT_FIELD_EVENT => 'onManageFieldAction',
            ActionSubscriber::MANAGE_SELECT_FIELD_EVENT      => 'onManageFieldAction',
            ActionSubscriber::MANAGE_SEGMENTS_EVENT          => 'onManageSegmentsAction',
        ], ActionSubscriber::getSubscribedEvents());
    }

    /**
     * @param list<string> $methods
     *
     * @return MockObject&LeadModel
     */
    private function getLeadModel(array $methods = ['saveEntity', 'setFieldValues']): MockObject
    {
        $platform = $this->createMock(MySQL80Platform::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')
            ->willReturn($platform);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')
            ->willReturn($connection);

        $leadModel = $this->getMockBuilder(LeadModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $reflectionObject   = new \ReflectionObject($leadModel);
        $reflectionProperty = $reflectionObject->getProperty('em');
        $reflectionProperty->setValue($leadModel, $entityManager);

        return $leadModel;
    }
}
