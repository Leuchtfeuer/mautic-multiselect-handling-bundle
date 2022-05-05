<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticContactSegmentsBundle\EventListener\ActionSubscriber;
use MauticPlugin\MauticContactSegmentsBundle\Form\Type\UpdateMultiselectFieldType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ActionSubscriberTest extends TestCase
{
    public function testChecksContext(): void
    {
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::ACTION)
            ->willReturn(false);
        $event->expects(self::never())
            ->method('getConfig');
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');

        $subscriber = new ActionSubscriber($leadModel);
        $subscriber->onAction($event);
    }

    public function testMissingField(): void
    {
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value']);
        $event->expects(self::never())
            ->method('getLead');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid event configuration.');

        $subscriber = new ActionSubscriber($leadModel);
        $subscriber->onAction($event);
    }

    public function testMissingFieldInContact(): void
    {
        $fieldId = 2376;

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => []]);

        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn(['other' => 'value', UpdateMultiselectFieldType::FIELD => $fieldId]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())
            ->method('saveEntity');

        $subscriber = new ActionSubscriber($leadModel);
        $subscriber->onAction($event);
    }

    /**
     * @param array<string> $expectedFieldValues
     * @dataProvider manageFieldProvider
     */
    public function testManagesFieldInContact(?string $fieldValue, array $expectedFieldValues): void
    {
        $fieldId    = 2376;
        $fieldAlias = 'field_alias';

        $lead = $this->createMock(Lead::class);
        $lead->expects(self::once())
            ->method('getFields')
            ->willReturn(['core' => [
                ['id' => '22'],
                ['id' => (string) $fieldId, 'value' => $fieldValue, 'alias' => $fieldAlias],
            ]]);

        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('checkContext')
            ->with(ActionSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'other'                            => 'value',
                UpdateMultiselectFieldType::FIELD  => $fieldId,
                UpdateMultiselectFieldType::ADD    => ['alias_add_1', 'alias_add_2'],
                UpdateMultiselectFieldType::REMOVE => ['alias_remove_1', 'alias_remove_2'],
            ]);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->with($lead);
        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with($lead, [$fieldAlias => $expectedFieldValues]);

        $subscriber = new ActionSubscriber($leadModel);
        $subscriber->onAction($event);
    }

    public function manageFieldProvider(): array
    {
        return [
            [null, [1 => 'alias_add_1', 2 => 'alias_add_2']],
            ['other', ['other', 'alias_add_1', 'alias_add_2']],
            ['alias_add_1', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1|alias_add_1|alias_remove_2', ['alias_add_1', 'alias_add_2']],
            ['alias_remove_1|alias_add_1|alias_remove_2|other', ['alias_add_1', 'other', 'alias_add_2']],
        ];
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([ActionSubscriber::EVENT => 'onAction'], ActionSubscriber::getSubscribedEvents());
    }
}
