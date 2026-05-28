<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use PHPUnit\Framework\TestCase;

class ActionSubscriberTest extends TestCase
{
    /**
     * @param array<string,mixed> $properties
     */
    private function createPendingEventMock(string $eventType, array $properties): PendingEvent
    {
        $event = $this->createMock(Event::class);
        $event->method('getType')->willReturn($eventType);
        $event->method('getProperties')->willReturn($properties);

        $config = $this->createMock(AbstractEventAccessor::class);
        $config->method('getConfig')->willReturn($properties);

        $pendingEvent = $this->createMock(PendingEvent::class);
        $pendingEvent->method('getEvent')->willReturn($event);
        $pendingEvent->method('getPending')->willReturn(new ArrayCollection());

        return $pendingEvent;
    }

    public function testManageFieldActionChecksContext(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $pendingEvent = $this->createPendingEventMock('unknown.event.type', []);
        $pendingEvent->expects(self::never())->method('pass');
        $pendingEvent->expects(self::never())->method('fail');
        $pendingEvent->expects(self::never())->method('failAll');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($pendingEvent);
    }

    public function testManageFieldActionFailsWithMissingFieldConfig(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $pendingEvent = $this->createPendingEventMock(
            ActionSubscriber::MANAGE_SELECT_FIELD_ACTION,
            ['other' => 'value'] // No UpdateMultiSelectFieldType::FIELD
        );

        $pendingEvent->expects(self::once())
            ->method('failAll')
            ->with('Invalid event configuration.');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($pendingEvent);
    }

    public function testManageFieldActionReturnsEarlyWhenPluginNotPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(false);

        $pendingEvent = $this->createPendingEventMock(
            ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION,
            [UpdateMultiSelectFieldType::FIELD => 123]
        );

        $pendingEvent->expects(self::once())
            ->method('failAll')
            ->with('Plugin not published');

        $leadModel = $this->createMock(LeadModel::class);
        $leadModel->expects(self::never())->method('saveEntity');

        $segmentsModel = $this->createMock(SegmentsModel::class);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageFieldAction($pendingEvent);
    }

    public function testManageSegmentsActionChecksContext(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $pendingEvent = $this->createPendingEventMock('unknown.event.type', []);
        $pendingEvent->expects(self::never())->method('pass');
        $pendingEvent->expects(self::never())->method('fail');
        $pendingEvent->expects(self::never())->method('failAll');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($pendingEvent);
    }

    /**
     * @dataProvider missingFieldProvider
     *
     * @param array<string,mixed> $fields
     */
    public function testManageSegmentsActionFailsWithMissingFieldConfig(array $fields): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $pendingEvent = $this->createPendingEventMock(
            ActionSubscriber::MANAGE_SEGMENTS_ACTION,
            $fields
        );

        $pendingEvent->expects(self::once())
            ->method('failAll')
            ->with('Invalid event configuration.');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($pendingEvent);
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function missingFieldProvider(): array
    {
        return [
            'missing field' => [
                [SettingsType::CHECKBOX => '1'],
            ],
            'missing checkbox' => [
                [SettingsType::FIELD => '1'],
            ],
        ];
    }

    public function testManageSegmentsActionFailsWithInvalidSegments(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $fieldId = 2376;

        $pendingEvent = $this->createPendingEventMock(
            ActionSubscriber::MANAGE_SEGMENTS_ACTION,
            [
                SettingsType::FIELD    => $fieldId,
                SettingsType::CHECKBOX => '0',
            ]
        );

        $pendingEvent->expects(self::once())
            ->method('failAll')
            ->with('Invalid setup.');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn([]);

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($pendingEvent);
    }

    public function testManageSegmentsActionReturnsEarlyWhenPluginNotPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(false);

        $pendingEvent = $this->createPendingEventMock(
            ActionSubscriber::MANAGE_SEGMENTS_ACTION,
            [
                SettingsType::FIELD    => 123,
                SettingsType::CHECKBOX => '1',
            ]
        );

        $pendingEvent->expects(self::once())
            ->method('failAll')
            ->with('Plugin not published');

        $leadModel = $this->createMock(LeadModel::class);
        $segmentsModel = $this->createMock(SegmentsModel::class);
        $segmentsModel->expects(self::never())->method('getSegments');

        $subscriber = new ActionSubscriber($config, $leadModel, $segmentsModel);
        $subscriber->onManageSegmentsAction($pendingEvent);
    }

    public function testSubscribedEvents(): void
    {
        $events = ActionSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(ActionSubscriber::MANAGE_MULTISELECT_FIELD_EVENT, $events);
        self::assertArrayHasKey(ActionSubscriber::MANAGE_SELECT_FIELD_EVENT, $events);
        self::assertArrayHasKey(ActionSubscriber::MANAGE_SEGMENTS_EVENT, $events);
    }
}
