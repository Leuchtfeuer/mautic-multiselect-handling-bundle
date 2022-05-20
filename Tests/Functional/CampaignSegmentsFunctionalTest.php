<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Tests\Functional;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMultiselectHandlingBundle\EventListener\ActionSubscriber;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CampaignSegmentsFunctionalTest extends MauticMysqlTestCase
{
    private LeadModel $leadModel;

    protected $useCleanupRollback = false;

    private array $contacts = [
        [
            'email'              => 'contact2@email.com',
            'firstname'          => 'Robert A.',
            'lastname'           => 'Heinlein',
        ],
    ];

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->leadModel = self::$container->get(LeadModel::class);
    }

    public function testFunctionalWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createSegments();
        unset($selectedSegments[4]);
        $fieldId = $this->testCreateMultiselectField($selectedSegments);

        $this->contacts[0]['manage_segments'] = [$selectedSegments[0]['alias'], $selectedSegments[1]['alias']];
        $contact                              = $this->createContact();
        $campaign                             = $this->createCampaign($contact['id'], $fieldId, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[3]['id']]);

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
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);
        $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$contact['id'].'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        foreach ($segments as $segment) {
            $this->client->request(Request::METHOD_DELETE, '/api/segments/'.$segment['id'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        }

        $this->client->request(Request::METHOD_DELETE, '/api/campaigns/'.$campaign->getId().'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$fieldId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
    }

    public function testFunctionalWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createSegments();
        unset($selectedSegments[4]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));

        $this->contacts[0]['manage_segments'] = [$selectedSegments[0]['alias'], $selectedSegments[1]['alias'], $createdSegmentAlias];
        $contact                              = $this->createContact();
        $campaign                             = $this->createCampaign($contact['id'], $fieldId, true);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[3]['id']]);

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
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(3, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);
        $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$contact['id'].'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        foreach ($segments as $segment) {
            $this->client->request(Request::METHOD_DELETE, '/api/segments/'.$segment['id'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        }

        $this->client->request(Request::METHOD_DELETE, '/api/campaigns/'.$campaign->getId().'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$fieldId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
    }

    /**
     * @return mixed[]
     */
    private function createSegments(): array
    {
        $this->client->request('POST', '/api/segments/batch/new', $this->segments);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

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

    private function testCreateMultiselectField(array $segments)
    {
        $list = [];
        foreach ($segments as $segment) {
            $list[] = ['label' => $segment['name'], 'value' => $segment['alias']];
        }

        $payload = [
            'label'               => 'Manage segments',
            'alias'               => 'manage_segments',
            'type'                => 'multiselect',
            'isPubliclyUpdatable' => true,
            'isUniqueIdentifier'  => false,
            'properties'          => [
                'list' => $list,
            ],
        ];

        $this->client->request(Request::METHOD_POST, '/api/fields/contact/new', $payload);
        $clientResponse = $this->client->getResponse();
        $fieldResponse  = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        return $fieldResponse['field']['id'];
    }

    private function createContact(): array
    {
        $this->client->request('POST', '/api/contacts/batch/new', $this->contacts);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(1, $response['statusCodes']);
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());

        return $response['contacts'][0];
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
            SettingsType::CHECKBOX => $createMissing ? '1' : '0',
        ]);

        $this->em->persist($event);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }
}
