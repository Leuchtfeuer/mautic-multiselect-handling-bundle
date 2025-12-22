<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Functional;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CampaignSegmentsFunctionalTest extends MauticMysqlTestCase
{
    private LeadModel $leadModel;

    private const FIELD_NAME = 'test_field';

    protected $useCleanupRollback = false;

    /**
     * @var array<int, array<string, string>>
     */
    private array $contacts = [
        [
            'email'              => 'contact2@email.com',
            'firstname'          => 'Robert A.',
            'lastname'           => 'Heinlein',
        ],
    ];

    /**
     * @var array<int, array<string, string>>
     */
    private array $segments = [
        [
            'name'        => 'Add 1',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'Add 2',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'Remove 1',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'Remove 2',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'Other',
            'description' => 'Segment created via API test',
        ],
    ];

    /**
     * @var array<int, array<int, mixed>>|null
     */
    private ?array $createdSegments = null;

    private ?int $contactId = null;

    private ?int $campaignId = null;

    private ?int $fieldId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leadModel = self::$container->get(LeadModel::class);
        $this->activatePlugin(true);
    }

    protected function beforeTearDown(): void
    {
        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);

        if (null !== $this->contactId) {
            $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$this->contactId.'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->contactId = null;
        }

        if (null !== $this->createdSegments) {
            foreach ($this->createdSegments as $segment) {
                $this->client->request(Request::METHOD_DELETE, '/api/segments/'.$segment['id'].'/delete');
                $clientResponse = $this->client->getResponse();
                self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            }
            $this->createdSegments = null;
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

        if (isset($this->segments[0]['alias'])) {
            unset($this->segments[0]['alias']);
        }

        if (isset($this->segments[1]['alias'])) {
            unset($this->segments[1]['alias']);
        }
    }

    private function activatePlugin(bool $isPublished=true): void
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

    public function testFunctionalMultiselectWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $fieldId = $this->fieldId = $this->testCreateSelectField($selectedSegments, true);

        $this->contacts[0][self::FIELD_NAME] = [$selectedSegments[0]['alias'], $selectedSegments[1]['alias']];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, false);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSelectWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $fieldId = $this->fieldId = $this->testCreateSelectField($selectedSegments, false);

        $this->contacts[0][self::FIELD_NAME] = $selectedSegments[1]['alias'];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, false);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(1, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
    }

    public function testFunctionalMultiselectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->fieldId = $this->testCreateSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]), true);

        $this->contacts[0][self::FIELD_NAME] = [$selectedSegments[0]['alias'], $selectedSegments[1]['alias'], $createdSegmentAlias];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, true);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(3, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSelectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->fieldId = $this->testCreateSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]), true);

        $this->contacts[0][self::FIELD_NAME] = $createdSegmentAlias;
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, true);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(1, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
    }

    public function testFunctionalUnderscoreMultiselectWithoutCreatingMissing(): void
    {
        $aliasMap                   = ['add2' => 'add_2'];
        $this->segments[0]['alias'] = 'add-1';
        $this->segments[1]['alias'] = 'add_2'; // Create segment with underscore, that later will be replaced with an empty string
        $selectedSegments           = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $fieldId = $this->fieldId = $this->testCreateSelectField($selectedSegments, true, $aliasMap);

        $this->contacts[0][self::FIELD_NAME] = [$selectedSegments[0]['alias'], $aliasMap[$selectedSegments[1]['alias']]];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, false);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalUnderscoreSelectWithoutCreatingMissing(): void
    {
        $aliasMap                   = ['add2' => 'add_2'];
        $this->segments[0]['alias'] = 'add-1';
        $this->segments[1]['alias'] = 'add2'; // Create segment with underscore, that later will be replaced with an empty string
        $selectedSegments           = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $fieldId = $this->fieldId = $this->testCreateSelectField($selectedSegments, false, $aliasMap);

        $this->contacts[0][self::FIELD_NAME] = $aliasMap[$selectedSegments[1]['alias']];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, false);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(1, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
    }

    public function testFunctionalUnderscoreMultiselectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'created-segment_name';
        $fieldId             = $this->fieldId = $this->testCreateSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]), true);

        $this->contacts[0][self::FIELD_NAME] = [$selectedSegments[0]['alias'], $selectedSegments[1]['alias'], $createdSegmentAlias];
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, true);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(3, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($this->leadModel->cleanAlias($createdSegmentAlias, '', 0, '-'), $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalUnderscoreSelectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createdSegments = $this->createSegments();
        unset($selectedSegments[4]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'created-segment_name';
        $fieldId             = $this->fieldId = $this->testCreateSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]), true);

        $this->contacts[0][self::FIELD_NAME] = $createdSegmentAlias;
        $contactId                           = $this->contactId = $this->createContact();
        $campaign                            = $this->createCampaign($contactId, $fieldId, true);
        $this->campaignId                    = $campaign->getId();

        $this->leadModel->addToLists(['id' => $contactId], [$segments[0]['id'], $segments[3]['id']]);

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

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contactId);

        self::assertCount(1, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($this->leadModel->cleanAlias($createdSegmentAlias, '', 0, '-'), $list->getAlias());
    }

    /**
     * @return array<int,array<int>>
     */
    private function createSegments(): array
    {
        $this->client->request('POST', '/api/segments/batch/new', $this->segments);
        $clientResponse = $this->client->getResponse();
        try {
            $response = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(5, $response['statusCodes']);
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][1], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][2], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][3], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][4], $clientResponse->getContent());

        return [
            $response['lists'][0],
            $response['lists'][1],
            $response['lists'][2],
            $response['lists'][3],
            $response['lists'][4],
        ];
    }

    private function testCreateSelectField(array $segments, bool $multiselect, array $aliasMap = []): int
    {
        $list = [];
        foreach ($segments as $segment) {
            $alias = $segment['alias'];

            if (isset($aliasMap[$alias])) {
                $alias = $aliasMap[$alias];
            }

            $list[] = ['label' => $segment['name'], 'value' => $alias];
        }

        $payload = [
            'label'               => 'Manage segments',
            'alias'               => self::FIELD_NAME,
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

    private function createContact(): int
    {
        $this->client->request('POST', '/api/contacts/batch/new', $this->contacts);
        $clientResponse = $this->client->getResponse();
        try {
            $response = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(1, $response['statusCodes']);
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());

        return (int) $response['contacts'][0]['id'];
    }

    private function createCampaign(int $contactId, int $fieldId, bool $createMissing): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('Test Update contact');

        $this->em->persist($campaign);

        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($this->em->getReference(Lead::class, $contactId));
        $campaignLead->setDateAdded(new \DateTime());
        $this->em->persist($campaignLead);
        $campaign->addLead(0, $campaignLead);

        $event = new Event();
        $event->setCampaign($campaign);
        $event->setName('Update multiselect');
        $event->setType(ActionSubscriber::MANAGE_SEGMENTS_ACTION);
        $event->setEventType('action');
        $event->setTriggerMode('immediate');
        $event->setProperties([
            SettingsType::FIELD    => $fieldId,
            SettingsType::CHECKBOX => $createMissing ? '1' : null,
        ]);

        $this->em->persist($event);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }
}
