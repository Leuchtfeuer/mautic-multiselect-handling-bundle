<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Tests\Unit\EventListener;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Exception\ValidationException;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormAction;
use MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormSubscriber;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormActionTest extends TestCase
{
    public function testOnActionDifferentContext(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(false);
        $event->expects(self::never())
            ->method('getContactFieldMatches');

        $event->expects(self::never())
            ->method('getAction');
        $event->expects(self::never())
            ->method('getLead');

        $leadFieldChoiceLoader->expects(self::never())
            ->method('loadFieldsForChoices');

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function testOnActionNoAction(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::never())
            ->method('getContactFieldMatches');

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn(null);
        $event->expects(self::never())
            ->method('getLead');

        $leadFieldChoiceLoader->expects(self::never())
            ->method('loadFieldsForChoices');

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function testOnActionNoLead(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::never())
            ->method('getContactFieldMatches');

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn(null);

        $action->expects(self::never())
            ->method('getProperties');

        $leadFieldChoiceLoader->expects(self::never())
            ->method('loadFieldsForChoices');

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    /**
     * @param array<string, string> $properties
     * @dataProvider invalidActionProperties
     */
    public function testOnActionNotAllProperties(array $properties): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::never())
            ->method('getContactFieldMatches');

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);
        $event->expects(self::never())
            ->method('getResults');

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $leadFieldChoiceLoader->expects(self::never())
            ->method('loadFieldsForChoices');

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Seems like you do not have proper SettingsType.');

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function invalidActionProperties(): array
    {
        return [
            [[]],
            [[SettingsType::FIELD => 'data']],
            [[SettingsType::CHECKBOX => 'data']],
        ];
    }

    /**
     * @dataProvider trueFalse
     */
    public function testOnActionInvalidChoices(bool $zeroOrTwo): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $fieldId               = 123;
        $exceptionMessage      = 'Exception!';
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::never())
            ->method('getContactFieldMatches');

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn($zeroOrTwo ? [] : [1, 2]);

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::INVALID_SETUP)
            ->willReturn($exceptionMessage);

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function trueFalse(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function testOnActionNoActionField(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $leadField             = $this->createMock(LeadField::class);
        $leadFieldAlias        = 'lead_field_alias';
        $fieldId               = 123;
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([]);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        // no exception is thrown.

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    /**
     * @param array<mixed> $properties
     * @dataProvider invalidProperties
     */
    public function testOnActionInvalidChoiceProperties(array $properties): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $leadField             = $this->createMock(LeadField::class);
        $leadFieldAlias        = 'field_alias';
        $fieldId               = 451;
        $exceptionMessage      = 'Exception!';
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => '']);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);
        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::INVALID_SETUP)
            ->willReturn($exceptionMessage);

        $listModel->expects(self::never())
            ->method('getUserLists');
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function invalidProperties(): array
    {
        return [
            [[]],
            [['list' => 'not array']],
            [['list' => []]],
        ];
    }

    /**
     * @param array<mixed> $segmentsData
     * @dataProvider invalidSegmentData
     */
    public function testOnActionInvalidSegments(array $segmentsData): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $leadField             = $this->createMock(LeadField::class);
        $leadFieldAlias        = 'field_alias';
        $fieldId               = 112;
        $exceptionMessage      = 'Exception!';
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => '']);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);
        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::INVALID_SETUP)
            ->willReturn($exceptionMessage);

        $listModel->expects(self::once())
            ->method('getUserLists')
            ->with($segmentAlias)
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function invalidSegmentData(): array
    {
        return [
            [['not array']],
            [['not_id' => 'index']],
        ];
    }

    public function testOnActionNotExistingSegmentAndNoCreateNewSegments(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $leadField             = $this->createMock(LeadField::class);
        $leadFieldAlias        = 'field_alias';
        $fieldId               = 1224;
        $exceptionMessage      = 'Exception!';
        $segmentAlias          = 'segment_alias';
        $segmentName           = 'Segment name';
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $properties            = ['list' => [['value' => $segmentAlias, 'label' => $segmentName]]];
        $segmentsData          = [];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => '']);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);
        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::NON_EXISTING_LIST)
            ->willReturn($exceptionMessage);

        $listModel->expects(self::once())
            ->method('getUserLists')
            ->with($segmentAlias)
            ->willReturn($segmentsData);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function testOnActionManagesSegments(): void
    {
        $leadFieldChoiceLoader    = $this->createMock(LeadFieldChoiceLoader::class);
        $translator               = $this->createMock(TranslatorInterface::class);
        $leadModel                = $this->createMock(LeadModel::class);
        $listModel                = $this->createMock(ListModel::class);
        $event                    = $this->createMock(SubmissionEvent::class);
        $action                   = $this->createMock(Action::class);
        $lead                     = $this->createMock(Lead::class);
        $leadField                = $this->createMock(LeadField::class);
        $leadFieldAlias           = 'field_alias';
        $fieldId                  = 2333;
        $actionProperties         = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '1'];
        $segmentAlias             = 'segment_alias';
        $segmentName              = 'Segment name';
        $segmentId                = '112';
        $existingSegmentId        = '441';
        $existingSegmentAlias     = 'existing_segment';
        $existingSegmentName      = 'Existing segment';
        $existingSegment          = $this->createMock(LeadList::class);
        $createdSegmentId         = 3737;
        $createSegmentAlias       = 'create_segment_alias';
        $createSegmentName        = 'Create segment name';
        $removeSegmentId          = 74747;
        $removeSegmentAlias       = 'remove_alias';
        $removeSegmentName        = 'Remove segment name';
        $removeSegment            = $this->createMock(LeadList::class);
        $otherSegment             = $this->createMock(LeadList::class);
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

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => [
                $segmentAlias, // selected aliases
                $createSegmentAlias,
                $existingSegmentAlias,
            ]]);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);
        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $translator->expects(self::never())
            ->method('trans');

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

        $existingSegment->expects(self::once())
            ->method('getId')
            ->willReturn($existingSegmentId);
        $existingSegment->expects(self::once())
            ->method('getAlias')
            ->willReturn($existingSegmentAlias);

        $removeSegment->expects(self::once())
            ->method('getId')
            ->willReturn($removeSegmentId);
        $removeSegment->expects(self::once())
            ->method('getAlias')
            ->willReturn($removeSegmentAlias);

        $otherSegment->expects(self::once())
            ->method('getId')
            ->willReturn(8881111);

        $leadModel->expects(self::once())
            ->method('getLists')
            ->with($lead)
            ->willReturn([$existingSegment, $otherSegment, $removeSegment]);
        $leadModel->expects(self::once())
            ->method('removeFromLists')
            ->with($lead, [$removeSegmentId]);
        $leadModel->expects(self::once())
            ->method('addToLists')
            ->with($lead, [$segmentId, $createdSegmentId]);

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function testOnActionDoesManagesSegmentsIfAllArePresent(): void
    {
        $leadFieldChoiceLoader = $this->createMock(LeadFieldChoiceLoader::class);
        $translator            = $this->createMock(TranslatorInterface::class);
        $leadModel             = $this->createMock(LeadModel::class);
        $listModel             = $this->createMock(ListModel::class);
        $event                 = $this->createMock(SubmissionEvent::class);
        $action                = $this->createMock(Action::class);
        $lead                  = $this->createMock(Lead::class);
        $leadField             = $this->createMock(LeadField::class);
        $leadFieldAlias        = 'field_alias';
        $fieldId               = 12455;
        $actionProperties      = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $segment1Alias         = 'segment_alias';
        $segment1Name          = 'Segment name';
        $segment1Id            = '112';
        $segment2Alias         = 'existing_segment';
        $segment2Name          = 'Existing segment';
        $segment2Id            = '441';
        $properties            = ['list' => [['value' => $segment1Alias, 'label' => $segment1Name], ['value' => $segment2Alias, 'label' => $segment2Name]]];
        $segmentsData          = [
            [['id' => $segment1Id, 'alias' => $segment1Alias]],
            [['id' => $segment2Id, 'alias' => $segment2Alias]],
        ];

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => [
                $segment1Alias,
                $segment2Alias,
            ]]);

        $event->expects(self::once())
            ->method('getAction')
            ->willReturn($action);
        $event->expects(self::once())
            ->method('getLead')
            ->willReturn($lead);

        $action->expects(self::once())
            ->method('getProperties')
            ->willReturn($actionProperties);

        $leadFieldChoiceLoader->expects(self::once())
            ->method('loadFieldsForChoices')
            ->with([$fieldId])
            ->willReturn([$leadField]);

        $leadField->expects(self::once())
            ->method('getAlias')
            ->willReturn($leadFieldAlias);
        $leadField->expects(self::once())
            ->method('getProperties')
            ->willReturn($properties);

        $translator->expects(self::never())
            ->method('trans');

        $listModel->expects(self::exactly(2))
            ->method('getUserLists')
            ->withConsecutive([$segment1Alias], [$segment2Alias])
            ->willReturnOnConsecutiveCalls($segmentsData[0], $segmentsData[1]);
        $listModel->expects(self::never())
            ->method('saveEntity');

        $segment1 = $this->createMock(LeadList::class);
        $segment1->expects(self::once())
            ->method('getId')
            ->willReturn($segment1Id);
        $segment1->expects(self::once())
            ->method('getAlias')
            ->willReturn($segment1Alias);

        $segment2 = $this->createMock(LeadList::class);
        $segment2->expects(self::once())
            ->method('getId')
            ->willReturn($segment2Id);
        $segment2->expects(self::once())
            ->method('getAlias')
            ->willReturn($segment2Alias);

        $leadModel->expects(self::once())
            ->method('getLists')
            ->with($lead)
            ->willReturn([$segment1, $segment2]);
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($leadFieldChoiceLoader, $translator, $leadModel, $listModel);
        $formAction->onAction($event);
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([
            FormAction::ACTION   => 'onAction',
        ], FormAction::getSubscribedEvents());
    }
}
