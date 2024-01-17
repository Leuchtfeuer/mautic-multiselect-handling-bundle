<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Functional;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CampaignChangeFieldValuesFunctionalTest extends MauticMysqlTestCase
{
    private LeadRepository $contactRepository;

    private const FIELD_NAME_MULTISELECT = 'test_multiselect_field';
    private const FIELD_NAME_SELECT      = 'test_select_field';

    protected $useCleanupRollback = false;

    private array $contacts = [
        [
            'email'     => 'contact1@email.com',
            'firstname' => 'Isaac',
            'lastname'  => 'Asimov',
        ],
        [
            'email'                      => 'contact2@email.com',
            'firstname'                  => 'Robert A.',
            'lastname'                   => 'Heinlein',
            self::FIELD_NAME_MULTISELECT => ['field1', 'field4', 'other'],
            self::FIELD_NAME_SELECT      => 'field1',
        ],
        [
            'email'                      => 'contact3@email.com',
            'firstname'                  => 'Arthur C.',
            'lastname'                   => 'Clarke',
            self::FIELD_NAME_MULTISELECT => ['field1', 'other2'],
            self::FIELD_NAME_SELECT      => 'other2',
        ],
        [
            'email'                      => 'contact4@email.com',
            'firstname'                  => 'Jonathan',
            'lastname'                   => 'Dafis',
            self::FIELD_NAME_MULTISELECT => ['field4', 'other3'],
            self::FIELD_NAME_SELECT      => 'field4',
        ],
        [
            'email'                      => 'contact5@email.com',
            'firstname'                  => 'Grzegorz',
            'lastname'                   => 'Brzeczyszczykiewicz',
            self::FIELD_NAME_MULTISELECT => ['field4', 'field3'],
            self::FIELD_NAME_SELECT      => 'field3',
        ],
    ];

    private array $fieldData = [
        [
            'name'  => 'Field 1',
            'alias' => 'field1',
        ],
        [
            'name'  => 'Field 2',
            'alias' => 'field2',
        ],
        [
            'name'  => 'Field 3',
            'alias' => 'field3',
        ],
        [
            'name'  => 'Field 4',
            'alias' => 'field4',
        ],
        [
            'name'  => 'Other',
            'alias' => 'other',
        ],
        [
            'name'  => 'Other 2',
            'alias' => 'other2',
        ],
        [
            'name'  => 'Other 3',
            'alias' => 'other3',
        ],
    ];

    /**
     * @var array<int, int>
     */
    private ?array $contactIds = null;

    private ?int $campaignId = null;

    private ?int $fieldId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactRepository = $this->em->getRepository(Lead::class);
        $this->activatePlugin(true);
    }

    protected function beforeTearDown(): void
    {
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);

        if (null !== $this->contactIds) {
            foreach ($this->contactIds as $contactId) {
                $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$contactId.'/delete', []);
                $clientResponse = $this->client->getResponse();
                self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            }
            $this->contactIds = null;
        }

        if (null !== $this->campaignId) {
            $this->client->request(Request::METHOD_DELETE, '/api/campaigns/'.$this->campaignId.'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->campaignId = null;
        }

        if (null !== $this->fieldId) {
            $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$this->fieldId.'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->fieldId = null;
        }
    }

    private function activatePlugin($isPublished=true)
    {
        $this->client->request('GET', '/s/plugins/reload');
        self::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerMultiselect']);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerMultiselectHandlingBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerMultiselect');
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
    }

    public function testApplyFieldChangesToMultiselect(): void
    {
        $fieldId  = $this->fieldId = $this->createField(true);
        $contacts = $this->contactIds = $this->createContacts();
        $campaign = $this->createCampaign(
            $contacts,
            $fieldId,
            [$fieldId.'-'.$this->fieldData[0]['alias'], $fieldId.'-'.$this->fieldData[1]['alias']],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']],
            true
        );
        $this->campaignId = $campaign->getId();

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $applicationTester = new ApplicationTester($application);

        // Force Doctrine to re-fetch the entities otherwise the campaign won't know about any events.
        $this->em->clear();

        // Execute the campaign.
        $exitCode = $applicationTester->run(
            [
                'command'       => 'mautic:campaigns:trigger',
                '--campaign-id' => $campaign->getId(),
            ]
        );

        self::assertSame(0, $exitCode, $applicationTester->getDisplay());

        /** @var Lead $contactA */
        $contactA = $this->contactRepository->getEntity($contacts[0]);
        /** @var Lead $contactB */
        $contactB = $this->contactRepository->getEntity($contacts[1]);
        /** @var Lead $contactC */
        $contactC = $this->contactRepository->getEntity($contacts[2]);
        /** @var Lead $contactD */
        $contactD = $this->contactRepository->getEntity($contacts[3]);
        /** @var Lead $contactE */
        $contactE = $this->contactRepository->getEntity($contacts[4]);

        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], $this->fieldData[1]['alias']]),
            $contactA->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], 'other', $this->fieldData[1]['alias']]),
            $contactB->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], 'other2', $this->fieldData[1]['alias']]),
            $contactC->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
        self::assertSame(
            implode('|', ['other3', $this->fieldData[0]['alias'], $this->fieldData[1]['alias']]),
            $contactD->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], $this->fieldData[1]['alias']]),
            $contactE->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
    }

    public function testSetSelectFieldWithRemovalWillOnlySet(): void
    {
        $fieldId  = $this->fieldId = $this->createField(false);
        $contacts = $this->contactIds = $this->createContacts();
        $campaign = $this->createCampaign(
            $contacts,
            $fieldId,
            [$fieldId.'-'.$this->fieldData[1]['alias']],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']],
            false
        );
        $this->campaignId = $campaign->getId();

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $applicationTester = new ApplicationTester($application);

        // Force Doctrine to re-fetch the entities otherwise the campaign won't know about any events.
        $this->em->clear();

        // Execute the campaign.
        $exitCode = $applicationTester->run(
            [
                'command'       => 'mautic:campaigns:trigger',
                '--campaign-id' => $campaign->getId(),
            ]
        );

        self::assertSame(0, $exitCode, $applicationTester->getDisplay());

        /** @var Lead $contactA */
        $contactA = $this->contactRepository->getEntity($contacts[0]);
        /** @var Lead $contactB */
        $contactB = $this->contactRepository->getEntity($contacts[1]);
        /** @var Lead $contactC */
        $contactC = $this->contactRepository->getEntity($contacts[2]);
        /** @var Lead $contactD */
        $contactD = $this->contactRepository->getEntity($contacts[3]);
        /** @var Lead $contactE */
        $contactE = $this->contactRepository->getEntity($contacts[4]);

        self::assertSame(
            $this->fieldData[1]['alias'],
            $contactA->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[1]['alias'],
            $contactB->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[1]['alias'],
            $contactC->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[1]['alias'],
            $contactD->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[1]['alias'],
            $contactE->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
    }

    public function testRemoveFromSelectFieldWithoutSetWillOnlyRemove(): void
    {
        $fieldId  = $this->fieldId = $this->createField(false);
        $contacts = $this->contactIds = $this->createContacts();
        $campaign = $this->createCampaign(
            $contacts,
            $fieldId,
            [],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']],
            false
        );
        $this->campaignId = $campaign->getId();

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $applicationTester = new ApplicationTester($application);

        // Force Doctrine to re-fetch the entities otherwise the campaign won't know about any events.
        $this->em->clear();

        // Execute the campaign.
        $exitCode = $applicationTester->run(
            [
                'command'       => 'mautic:campaigns:trigger',
                '--campaign-id' => $campaign->getId(),
            ]
        );

        self::assertSame(0, $exitCode, $applicationTester->getDisplay());

        /** @var Lead $contactA */
        $contactA = $this->contactRepository->getEntity($contacts[0]);
        /** @var Lead $contactB */
        $contactB = $this->contactRepository->getEntity($contacts[1]);
        /** @var Lead $contactC */
        $contactC = $this->contactRepository->getEntity($contacts[2]);
        /** @var Lead $contactD */
        $contactD = $this->contactRepository->getEntity($contacts[3]);
        /** @var Lead $contactE */
        $contactE = $this->contactRepository->getEntity($contacts[4]);

        self::assertNull(
            $contactA->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[0]['alias'],
            $contactB->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertSame(
            $this->fieldData[5]['alias'],
            $contactC->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertNull(
            $contactD->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
        self::assertNull(
            $contactE->getFieldValue(self::FIELD_NAME_SELECT, 'core')
        );
    }

    public function testCompletelyRemoveValuesFromMultiselect(): void
    {
        $fieldId  = $this->createField(true);
        $contacts = $this->createContacts();
        $campaign = $this->createCampaign(
            [$contacts[4]],
            $fieldId,
            [],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']],
            true
        );

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $applicationTester = new ApplicationTester($application);

        // Force Doctrine to re-fetch the entities otherwise the campaign won't know about any events.
        $this->em->clear();

        // Execute the campaign.
        $exitCode = $applicationTester->run(
            [
                'command'       => 'mautic:campaigns:trigger',
                '--campaign-id' => $campaign->getId(),
            ]
        );

        self::assertSame(0, $exitCode, $applicationTester->getDisplay());

        /** @var Lead $contactA */
        $contactA = $this->contactRepository->getEntity($contacts[4]);

        self::assertNull(
            $contactA->getFieldValue(self::FIELD_NAME_MULTISELECT, 'core')
        );
    }

    private function createField(bool $multiselect): int
    {
        $list = [];
        foreach ($this->fieldData as $setting) {
            $list[] = ['label' => $setting['name'], 'value' => $setting['alias']];
        }

        $payload = [
            'label'               => 'Manage multiselect',
            'alias'               => $multiselect ? self::FIELD_NAME_MULTISELECT : self::FIELD_NAME_SELECT,
            'type'                => $multiselect ? 'multiselect' : 'select',
            'isPubliclyUpdatable' => true,
            'isUniqueIdentifier'  => false,
            'properties'          => [
                'list' => $list,
            ],
        ];

        $this->client->request(Request::METHOD_POST, '/api/fields/contact/new', $payload);
        $clientResponse = $this->client->getResponse();
        try {
            $fieldResponse = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail($e->getMessage());
        }

        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        return $fieldResponse['field']['id'];
    }

    /**
     * @return array<int>
     */
    private function createContacts(): array
    {
        $this->client->request('POST', '/api/contacts/batch/new', $this->contacts);
        $clientResponse = $this->client->getResponse();
        try {
            $response = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(5, $response['statusCodes'], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][1], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][2], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][3], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][4], $clientResponse->getContent());

        return [
            $response['contacts'][0]['id'],
            $response['contacts'][1]['id'],
            $response['contacts'][2]['id'],
            $response['contacts'][3]['id'],
            $response['contacts'][4]['id'],
        ];
    }

    /**
     * @param array<int>    $contactIds
     * @param array<string> $valuesAdd
     * @param array<string> $valuesRemove
     */
    private function createCampaign(array $contactIds, int $fieldId, array $valuesAdd, array $valuesRemove, bool $multiselect): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('Test Update contact');

        $this->em->persist($campaign);

        foreach ($contactIds as $key => $contactId) {
            $campaignLead = new CampaignLead();
            $campaignLead->setCampaign($campaign);
            $campaignLead->setLead($this->em->getReference(Lead::class, $contactId));
            $campaignLead->setDateAdded(new \DateTime());
            $this->em->persist($campaignLead);
            $campaign->addLead($key, $campaignLead);
        }

        $event = new Event();
        $event->setCampaign($campaign);
        $event->setName('Update multiselect');
        $event->setType(
            $multiselect
            ? ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION
            : ActionSubscriber::MANAGE_SELECT_FIELD_ACTION
        );
        $event->setEventType('action');
        $event->setTriggerMode('immediate');
        $event->setProperties([
            'field'                       => $fieldId,
            UpdateSelectFieldType::ADD    => $valuesAdd,
            UpdateSelectFieldType::REMOVE => $valuesRemove,
        ]);

        $this->em->persist($event);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }
}
