<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticContactSegmentsBundle\EventListener\FormSubscriber;
use MauticPlugin\MauticContactSegmentsBundle\Form\Type\SettingsType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactSegmentsFunctionalTest extends MauticMysqlTestCase
{
    private LeadModel $leadModel;

    protected $useCleanupRollback = false;

    private array $contacts = [
        [
            'email'     => 'contact@email.com',
            'firstname' => 'Robert A.',
            'lastname'  => 'Heinlein',
            'points'    => 0,
        ],
    ];

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->leadModel = self::$container->get(LeadModel::class);
        $this->formModel = self::$container->get(FormModel::class);

        $this->client->followRedirects(false);
    }

    protected function tearDown(): void
    {
        $tablePrefix = self::$container->getParameter('mautic.db_table_prefix');

        parent::tearDown();

        if ($this->connection->getSchemaManager()->tablesExist("{$tablePrefix}form_results_1_submission")) {
            $this->connection->executeQuery("DROP TABLE {$tablePrefix}form_results_1_submission");
        }
    }

    public function testFunctionalWithoutCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createSegments();
        unset($selectedSegments[1]);
        $fieldId = $this->testCreateMultiselectField($selectedSegments);
        $contact = $this->createContact();
        $formId  = $this->createForm($fieldId, false);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->setValues([
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

        $this->client->request(Request::METHOD_DELETE, '/api/forms/'.$formId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$fieldId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
    }

    public function testFunctionalWithCreatingMissing(): void
    {
        $selectedSegments = $segments = $this->createSegments();
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->createContact();
        $formId              = $this->createForm($fieldId, true);

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

        $this->client->request(Request::METHOD_DELETE, '/api/forms/'.$formId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$fieldId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
    }

    public function testFunctionalWithoutCreatingMissingButMissingSegment(): void
    {
        $selectedSegments = $segments = $this->createSegments();
        unset($selectedSegments[1]);
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->createContact();
        $formId              = $this->createForm($fieldId, false);

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
        self::assertSame('https://localhost/form/'.$formId.'?mauticError=Errors%3A%3Cbr%20%2F%3E%3Col%3E%3Cli%3EGiven%20list%20does%20not%20exist.%3C%2Fli%3E%3C%2Fol%3E#submission', $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        /** @var LeadList[] $lists */
        $lists = $leadListRepository->getLeadLists($contact['id']);

        self::assertCount(3, $lists);
        $list = array_pop($lists);
        self::assertSame($segments[3]['id'], $list->getId());
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

        $this->client->request(Request::METHOD_DELETE, '/api/forms/'.$formId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->client->request(Request::METHOD_DELETE, '/api/fields/contact/'.$fieldId.'/delete', []);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
    }

    private function createForm(int $fieldId, bool $createMissing): int
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
                    'type'       => 'checkboxgrp',
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
                        SettingsType::CHECKBOX => $createMissing ? '1' : '0',
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
