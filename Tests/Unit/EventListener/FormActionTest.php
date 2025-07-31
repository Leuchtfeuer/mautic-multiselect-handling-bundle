<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Unit\EventListener;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Exception\ValidationException;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormAction;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\NonExistingListException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormActionTest extends TestCase
{
    public function testOnActionDifferentContext(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionNoAction(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionNoLead(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    /**
     * @param array<string, string> $properties
     *
     * @dataProvider invalidActionProperties
     */
    public function testOnActionNotAllProperties(array $properties): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Seems like you do not have proper SettingsType.');

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    /**
     * @return array<array<mixed>>
     */
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
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $fieldId                   = 123;
        $exceptionMessage          = 'Exception!';
        $actionProperties          = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
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

    public function testOnActionNoActionField(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $leadField                 = $this->createMock(LeadField::class);
        $leadFieldAlias            = 'lead_field_alias';
        $fieldId                   = 123;
        $actionProperties          = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $segmentsModel->expects(self::never())
            ->method('getSegments');

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        // no exception is thrown.

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionInvalidSegments(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $leadField                 = $this->createMock(LeadField::class);
        $leadFieldAlias            = 'field_alias';
        $fieldId                   = 112;
        $exceptionMessage          = 'Exception!';
        $actionProperties          = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::INVALID_SETUP)
            ->willReturn($exceptionMessage);

        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn(null);

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
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

    public function testOnActionNotExistingSegmentAndNoCreateNewSegments(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $leadField                 = $this->createMock(LeadField::class);
        $config                    = $this->createMock(Config::class);
        $leadFieldAlias            = 'field_alias';
        $fieldId                   = 1224;
        $exceptionMessage          = 'Exception!';
        $actionProperties          = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $translator->expects(self::once())
            ->method('trans')
            ->with(FormAction::NON_EXISTING_LIST)
            ->willReturn($exceptionMessage);

        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willThrowException(new NonExistingListException());

        $leadModel->expects(self::never())
            ->method('getLists');
        $leadModel->expects(self::never())
            ->method('removeFromLists');
        $leadModel->expects(self::never())
            ->method('addToLists');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionManagesSegmentsFromMultiselect(): void
    {
        $leadFieldChoiceLoader        = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                   = $this->createMock(TranslatorInterface::class);
        $leadModel                    = $this->createMock(LeadModel::class);
        $segmentsModel                = $this->createMock(SegmentsModel::class);
        $event                        = $this->createMock(SubmissionEvent::class);
        $action                       = $this->createMock(Action::class);
        $lead                         = $this->createMock(Lead::class);
        $leadField                    = $this->createMock(LeadField::class);
        $config                       = $this->createMock(Config::class);
        $leadFieldAlias               = 'field_alias';
        $fieldId                      = 2333;
        $actionProperties             = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '1'];
        $segmentAlias                 = 'segment_alias';
        $segmentId                    = '112';
        $existingSegmentId            = '441';
        $existingSegmentAlias         = 'existing_segment';
        $existingSegment              = $this->createMock(LeadList::class);
        $createdSegmentId             = 3737;
        $createSegmentAlias           = 'create_segment_alias';
        $removeSegmentId              = 74747;
        $removeSegmentAlias           = 'remove_alias';
        $removeSegment                = $this->createMock(LeadList::class);
        $otherSegment                 = $this->createMock(LeadList::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);
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

        $translator->expects(self::never())
            ->method('trans');

        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, true)
            ->willReturn([
                $segmentId         => $segmentAlias,
                $createdSegmentId  => $createSegmentAlias,
                $existingSegmentId => $existingSegmentAlias,
                $removeSegmentId   => $removeSegmentAlias,
            ]);

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

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionManagesSegmentsFromSelect(): void
    {
        $leadFieldChoiceLoader        = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                   = $this->createMock(TranslatorInterface::class);
        $leadModel                    = $this->createMock(LeadModel::class);
        $segmentsModel                = $this->createMock(SegmentsModel::class);
        $event                        = $this->createMock(SubmissionEvent::class);
        $action                       = $this->createMock(Action::class);
        $lead                         = $this->createMock(Lead::class);
        $leadField                    = $this->createMock(LeadField::class);
        $leadFieldAlias               = 'field_alias';
        $fieldId                      = 2333;
        $actionProperties             = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '1'];
        $segmentAlias                 = 'segment_alias';
        $segmentId                    = '112';
        $existingSegmentId            = '441';
        $existingSegmentAlias         = 'existing_segment';
        $existingSegment              = $this->createMock(LeadList::class);
        $createdSegmentId             = 3737;
        $createSegmentAlias           = 'create_segment_alias';
        $removeSegmentId              = 74747;
        $removeSegmentAlias           = 'remove_alias';
        $removeSegment                = $this->createMock(LeadList::class);
        $otherSegment                 = $this->createMock(LeadList::class);
        $config                       = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

        $event->expects(self::once())
            ->method('checkContext')
            ->with(FormSubscriber::ACTION)
            ->willReturn(true);
        $event->expects(self::once())
            ->method('getContactFieldMatches')
            ->willReturn([$leadFieldAlias => $segmentAlias]);

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

        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, true)
            ->willReturn([
                $segmentId         => $segmentAlias,
                $createdSegmentId  => $createSegmentAlias,
                $existingSegmentId => $existingSegmentAlias,
                $removeSegmentId   => $removeSegmentAlias,
            ]);

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
            ->with($lead, [$existingSegmentId, $removeSegmentId]);
        $leadModel->expects(self::once())
            ->method('addToLists')
            ->with($lead, [$segmentId]);

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testOnActionDoesManagesSegmentsIfAllArePresent(): void
    {
        $leadFieldChoiceLoader     = $this->createMock(LeadFieldChoiceLoader::class);
        $translator                = $this->createMock(TranslatorInterface::class);
        $leadModel                 = $this->createMock(LeadModel::class);
        $segmentsModel             = $this->createMock(SegmentsModel::class);
        $event                     = $this->createMock(SubmissionEvent::class);
        $action                    = $this->createMock(Action::class);
        $lead                      = $this->createMock(Lead::class);
        $leadField                 = $this->createMock(LeadField::class);
        $leadFieldAlias            = 'field_alias';
        $fieldId                   = 12455;
        $actionProperties          = [SettingsType::FIELD => $fieldId, SettingsType::CHECKBOX => '0'];
        $segment1Alias             = 'segment_alias';
        $segment1Id                = '112';
        $segment2Alias             = 'existing_segment';
        $segment2Id                = '441';
        $config                    = $this->createMock(Config::class);

        $config->expects(self::once())
            ->method('isPublished')
            ->willReturn(true);

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

        $translator->expects(self::never())
            ->method('trans');

        $segmentsModel->expects(self::once())
            ->method('getSegments')
            ->with($fieldId, false)
            ->willReturn([
                $segment1Id => $segment1Alias,
                $segment2Id => $segment2Alias,
            ]);

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

        $formAction = new FormAction($config, $leadFieldChoiceLoader, $translator, $leadModel, $segmentsModel);
        $formAction->onAction($event);
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([
            FormAction::ACTION        => 'onAction',
            FormAction::ACTION_FORM   => 'onActionForm',
        ], FormAction::getSubscribedEvents());
    }
}
