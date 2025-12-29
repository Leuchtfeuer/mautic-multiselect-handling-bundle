<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormSubscriber;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormActionSegmentsFunctionalTest extends MauticMysqlTestCase
{
    private LeadModel $leadModel;

    protected $useCleanupRollback = false;

    /**
     * @var array<array<string,mixed>>
     */
    private array $contacts = [
        [
            'email'     => 'contact@email.com',
            'firstname' => 'Robert A.',
            'lastname'  => 'Heinlein',
            'points'    => 0,
        ],
    ];

    /**
     * @var array<array<string,string>>
     */
    private array $segments = [
        [
            'name'        => 'API segment A',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'API segment B',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'API segment C',
            'description' => 'Segment created via API test',
        ],
        [
            'name'        => 'API segment D',
            'description' => 'Segment created via API test',
        ],
    ];

    /**
     * @var array<string,mixed>
     */
    private array $created = [
        'segments'      => [],
        'contact'       => null,
        'contact_field' => null,
        'form'          => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePlugin(true);
        $this->leadModel = self::$container->get(LeadModel::class);
        $this->client->followRedirects(false);
    }

    protected function beforeTearDown(): void
    {
        $tablePrefix = self::$container->getParameter('mautic.db_table_prefix');

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);

        if (null !== $this->created['contact']) {
            $this->client->request(Request::METHOD_DELETE, '/api/contacts/'.$this->created['contact']['id'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->created['contact'] = null;
        }

        foreach ($this->created['segments'] as $segment) {
            $this->client->request(Request::METHOD_DELETE, '/api/segments/'.$segment['id'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        }
        $this->created['segments'] = [];

        if (null !== $this->created['form']) {
            $this->client->request(Request::METHOD_DELETE, '/api/forms/'.$this->created['form'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->created['form'] = null;
        }

        if (null !== $this->created['contact_field']) {
            $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$this->created['contact_field'].'/delete', []);
            $clientResponse = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->created['contact_field'] = null;
        }

        if ($this->connection->createSchemaManager()->tablesExist("{$tablePrefix}form_results_1_submission")) {
            $this->connection->executeQuery("DROP TABLE {$tablePrefix}form_results_1_submission");
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

    public function testFunctionalMultiSelectWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->created['segments'] = $this->createSegments();
        unset($selectedSegments[1]);
        $fieldId = $this->created['contact_field'] = $this->testCreateMultiselectField($selectedSegments);
        $contact = $this->created['contact'] = $this->createContact();
        $formId  = $this->created['form'] = $this->createForm($fieldId, false, true);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => [$segments[0]['alias'], $segments[2]['alias']],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(3, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[2]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalMultiselectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->created['segments'] = $this->createSegments();
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, true, true);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => [$segments[0]['alias'], $segments[2]['alias'], $createdSegmentAlias],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(4, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[2]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalMultiselectWithoutCreatingMissingButMissingSegment(): void
    {
        $selectedSegments    = $segments = $this->created['segments'] = $this->createSegments();
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, false, true);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => [$segments[0]['alias'], $segments[2]['alias'], $createdSegmentAlias],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->em->clear();

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[2]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->created['segments'] = $this->createSegments();
        unset($selectedSegments[1]);
        $fieldId = $this->created['contact_field'] = $this->testCreateSingleSelectField($selectedSegments);
        $contact = $this->created['contact'] = $this->createContact();
        $formId  = $this->created['form'] = $this->createForm($fieldId, false, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => $segments[0]['alias'],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->created['segments'] = $this->createSegments();
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, true, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => $createdSegmentAlias,
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
    }

    public function testFunctionalUnderscoreSingleSelectWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->created['segments'] = $this->createSegments();
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'created_segment-name';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, true, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => $createdSegmentAlias,
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(2, $lists);
        $list = array_pop($lists);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($this->leadModel->cleanAlias($createdSegmentAlias, '', 0, '-'), $list->getAlias());
        $list = array_pop($lists);
        self::assertSame($segments[1]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithoutCreatingMissingButMissingSegment(): void
    {
        $selectedSegments    = $segments = $this->created['segments'] = $this->createSegments();
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, false, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => $createdSegmentAlias,
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(0, $lists);
    }

    public function testFunctionalSingleSelectWithMissingSegmentsIgnored(): void
    {
        $selectedSegments    = $segments = $this->created['segments'] = $this->createSegments();
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $formId              = $this->created['form'] = $this->createForm($fieldId, false, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => $segments[2]['alias'],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(1, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[2]['id'], $list->getId());
    }

    private function createForm(int $fieldId, bool $createMissing, bool $multiSelect): int
    {
        $payload = [
            'name'        => 'Submission test form',
            'description' => 'Form created via submission test',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'     => 'Email',
                    'type'      => 'email',
                    'alias'     => 'email',
                    'leadField' => 'email',
                ],
                [
                    'label'      => 'Select segments',
                    'type'       => $multiSelect ? 'checkboxgrp' : 'radiogrp',
                    'alias'      => 'select_segments',
                    'leadField'  => 'manage_segments',
                    'properties' => [
                        'syncList' => '1',
                    ],
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'actions' => [
                [
                    'name'        => 'Manage segments',
                    'description' => 'action description',
                    'type'        => FormSubscriber::ACTION,
                    'properties'  => [
                        SettingsType::FIELD    => $fieldId,
                        SettingsType::CHECKBOX => $createMissing ? '1' : null,
                    ],
                    'order'       => 1,
                ],
            ],
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $response = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $response['form']['id'];
    }

    /**
     * @param array<array<string,string>> $segments
     */
    private function testCreateMultiselectField(array $segments): int
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

    /**
     * @param array<array<string,string>> $segments
     */
    private function testCreateSingleSelectField(array $segments): int
    {
        $list = [];
        foreach ($segments as $segment) {
            $list[] = ['label' => $segment['name'], 'value' => $segment['alias']];
        }

        $payload = [
            'label'               => 'Manage segments',
            'alias'               => 'manage_segments',
            'type'                => 'select',
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
     * @return mixed[]
     */
    private function createSegments(): array
    {
        $this->client->request('POST', '/api/segments/batch/new', $this->segments);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(4, $response['statusCodes']);
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][1], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][2], $clientResponse->getContent());
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][3], $clientResponse->getContent());

        return [
            $response['lists'][0],
            $response['lists'][1],
            $response['lists'][2],
            $response['lists'][3],
        ];
    }

    /**
     * @return array<mixed>
     */
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
}
