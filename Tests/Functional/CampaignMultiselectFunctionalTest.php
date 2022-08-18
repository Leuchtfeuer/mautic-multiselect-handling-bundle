<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Tests\Functional;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use MauticPlugin\MauticMultiselectHandlingBundle\EventListener\ActionSubscriber;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CampaignMultiselectFunctionalTest extends MauticMysqlTestCase
{
    private LeadRepository $contactRepository;

    protected $useCleanupRollback = false;

    private array $contacts = [
        [
            'email'     => 'contact1@email.com',
            'firstname' => 'Isaac',
            'lastname'  => 'Asimov',
        ],
        [
            'email'              => 'contact2@email.com',
            'firstname'          => 'Robert A.',
            'lastname'           => 'Heinlein',
            'manage_multiselect' => ['field1', 'field4', 'other'],
        ],
        [
            'email'              => 'contact3@email.com',
            'firstname'          => 'Arthur C.',
            'lastname'           => 'Clarke',
            'manage_multiselect' => ['field1', 'other2'],
        ],
        [
            'email'              => 'contact4@email.com',
            'firstname'          => 'Jonathan',
            'lastname'           => 'Dafis',
            'manage_multiselect' => ['field4', 'other3'],
        ],
        [
            'email'              => 'contact5@email.com',
            'firstname'          => 'Grzegorz',
            'lastname'           => 'Brzeczyszczykiewicz',
            'manage_multiselect' => ['field4', 'field3'],
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->contactRepository = $this->em->getRepository(Lead::class);
    }

    public function testApplyFieldChanges(): void
    {
        $fieldId  = $this->createMultiselectField();
        $contacts = $this->createContacts();
        $campaign = $this->createCampaign(
            $contacts,
            $fieldId,
            [$fieldId.'-'.$this->fieldData[0]['alias'], $fieldId.'-'.$this->fieldData[1]['alias']],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']]
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
            $contactA->getFieldValue('manage_multiselect', 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], 'other', $this->fieldData[1]['alias']]),
            $contactB->getFieldValue('manage_multiselect', 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], 'other2', $this->fieldData[1]['alias']]),
            $contactC->getFieldValue('manage_multiselect', 'core')
        );
        self::assertSame(
            implode('|', ['other3', $this->fieldData[0]['alias'], $this->fieldData[1]['alias']]),
            $contactD->getFieldValue('manage_multiselect', 'core')
        );
        self::assertSame(
            implode('|', [$this->fieldData[0]['alias'], $this->fieldData[1]['alias']]),
            $contactE->getFieldValue('manage_multiselect', 'core')
        );

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);

        foreach ($contacts as $contactId) {
            $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$contactId.'/delete', []);
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

    public function testCompletelyRemoveValues(): void
    {
        $fieldId  = $this->createMultiselectField();
        $contacts = $this->createContacts();
        $campaign = $this->createCampaign(
            [$contacts[4]],
            $fieldId,
            [],
            [$fieldId.'-'.$this->fieldData[2]['alias'], $fieldId.'-'.$this->fieldData[3]['alias']]
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
            $contactA->getFieldValue('manage_multiselect', 'core')
        );

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);

        foreach ($contacts as $contactId) {
            $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$contactId.'/delete', []);
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

    private function createMultiselectField(): int
    {
        $list = [];
        foreach ($this->fieldData as $setting) {
            $list[] = ['label' => $setting['name'], 'value' => $setting['alias']];
        }

        $payload = [
            'label'               => 'Manage multiselect',
            'alias'               => 'manage_multiselect',
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

    /**
     * @return array<int>
     */
    private function createContacts(): array
    {
        $this->client->request('POST', '/api/contacts/batch/new', $this->contacts);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

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
    private function createCampaign(array $contactIds, int $fieldId, array $valuesAdd, array $valuesRemove): Campaign
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
        $event->setType(ActionSubscriber::MANAGE_FIELD_ACTION);
        $event->setEventType('action');
        $event->setTriggerMode('immediate');
        $event->setProperties([
            'field'              => $fieldId,
            'multiselect_add'    => $valuesAdd,
            'multiselect_remove' => $valuesRemove,
        ]);

        $this->em->persist($event);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }
}
