<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
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
        $this->leadModel = static::getContainer()->get(LeadModel::class);
        $this->client->followRedirects(false);
    }

    protected function beforeTearDown(): void
    {
        $tablePrefix = static::getContainer()->getParameter('mautic.db_table_prefix');

        // Cleanup
        self::ensureKernelShutdown();
        $this->setUpSymfony($this->configParams);
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if (null === $user) {
            return;
        }
        $this->loginUser($user);

        if (null !== $this->created['contact']) {
            $this->assertIsArray($this->created['contact']);
            $this->assertArrayHasKey('id', $this->created['contact']);
            $this->assertIsInt($this->created['contact']['id']);
            $contactId = (int) $this->created['contact']['id'];
            $this->client->request(Request::METHOD_DELETE, "/api/contacts/$contactId/delete", []);
            $clientResponse = $this->client->getResponse();
            $this->assertNotFalse($clientResponse->getContent());
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->created['contact'] = null;
        }
        $this->assertIsIterable($this->created['segments']);
        foreach ($this->created['segments'] as $segment) {
            $this->assertIsArray($segment);
            $this->assertArrayHasKey('id', $segment);
            $segmentId = $segment['id'];
            $this->assertIsInt($segmentId);
            $this->client->request(Request::METHOD_DELETE, "/api/segments/$segmentId/delete", []);
            $clientResponse = $this->client->getResponse();
            $this->assertNotFalse($clientResponse->getContent());
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        }
        $this->created['segments'] = [];

        if (null !== $this->created['form']) {
            $this->assertIsInt($this->created['form']);
            $formId = $this->created['form'];
            $this->client->request(Request::METHOD_DELETE, "/api/forms/$formId/delete", []);
            $clientResponse = $this->client->getResponse();
            $this->assertNotFalse($clientResponse->getContent());
            self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
            $this->created['form'] = null;
        }

        if (null !== $this->created['contact_field']) {
            $this->assertIsInt($this->created['contact_field']);
            $contactFieldId = $this->created['contact_field'];
            $this->client->request(Request::METHOD_DELETE, "/api/fields/contact/$contactFieldId/delete", []);
            $clientResponse = $this->client->getResponse();
            $this->assertNotFalse($clientResponse->getContent());
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

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'Leuchtfeuermultiselecthandling']);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerMultiselectHandlingBundle']);
            $integration = new Integration();
            $integration->setName('Leuchtfeuermultiselecthandling');
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
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $fieldId = $this->created['contact_field'] = $this->testCreateMultiselectField($selectedSegments);
        $contact = $this->created['contact'] = $this->createContact();
        $formId  = $this->created['form'] = $this->createForm($fieldId, false, true);

        $this->assertIsArray($contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsInt($contact['id']);

        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $form = $formCrawler->form([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => [$segments[0]['alias'], $segments[2]['alias']],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $lists              = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(3, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[2]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalMultiselectWithCreatingMissing(): void
    {
        $segments = $this->createSegments();
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);
        $selectedSegments          = $segments;
        $this->created['segments'] = $segments;
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $formId              = $this->created['form'] = $this->createForm($fieldId, true, true);

        $this->leadModel->addToLists(['id' => $contact['id']], [$segments[0]['id'], $segments[1]['id'], $segments[3]['id']]);

        $crawler     = $this->client->request(Request::METHOD_GET, '/form/'.$formId);
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        self::assertCount(1, $formCrawler, (string) $this->client->getResponse()->getContent());
        $form = $formCrawler->form();
        $form->disableValidation();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $this->assertIsString($contact['fields']['core']['email']['value']);
        $form->setValues([
            'mauticform[email]'           => $contact['fields']['core']['email']['value'],
            'mauticform[select_segments]' => [$segments[0]['alias'], $segments[2]['alias'], $createdSegmentAlias],
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $this->assertIsInt($contact['id']);
        $lists = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(4, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[2]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalMultiselectWithoutCreatingMissingButMissingSegment(): void
    {
        $segments = $this->createSegments();
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);
        $selectedSegments          = $segments;
        $this->created['segments'] = $segments;
        unset($selectedSegments[1]);
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateMultiselectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $this->assertIsString($contact['fields']['core']['email']['value']);
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
        $this->assertNotFalse($clientResponse->getContent());
        $this->markTestSkipped('This test is not yet implemented');
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId.'?mauticError=Errors%3A%3Cbr%20%2F%3E%3Col%3E%3Cli%3EGiven%20list%20does%20not%20exist.%3C%2Fli%3E%3C%2Fol%3E#submission', $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $this->assertIsInt($contact['id']);
        $lists = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(3, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[3]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithoutCreatingMissing(): void
    {
        $segments = $this->createSegments();
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertIsArray($segments[1]);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);
        $selectedSegments          = $segments;
        $this->created['segments'] = $segments;
        unset($selectedSegments[1]);
        $fieldId = $this->created['contact_field'] = $this->testCreateSingleSelectField($selectedSegments);
        $contact = $this->created['contact'] = $this->createContact();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $this->assertIsString($contact['fields']['core']['email']['value']);
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
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $this->assertIsInt($contact['id']);
        $lists = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(2, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[0]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithCreatingMissing(): void
    {
        $segments = $this->createSegments();
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);
        $selectedSegments          = $segments;
        $this->created['segments'] = $segments;
        unset($selectedSegments[1]);
        $createdSegmentName  = 'Created segment name';
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => $createdSegmentName, 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $this->assertIsString($contact['fields']['core']['email']['value']);
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
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId, $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $this->assertIsInt($contact['id']);
        $lists = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(2, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($createdSegmentName, $list->getName());
        self::assertSame($createdSegmentAlias, $list->getAlias());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
    }

    public function testFunctionalSingleSelectWithoutCreatingMissingButMissingSegment(): void
    {
        $segments = $this->createSegments();
        $this->assertIsArray($segments);
        $this->assertCount(4, $segments);
        $this->assertIsArray($segments[0]);
        $this->assertArrayHasKey('id', $segments[0]);
        $this->assertIsInt($segments[0]['id']);
        $this->assertArrayHasKey('alias', $segments[0]);
        $this->assertIsString($segments[0]['alias']);
        $this->assertIsArray($segments[1]);
        $this->assertArrayHasKey('id', $segments[1]);
        $this->assertIsInt($segments[1]['id']);
        $this->assertArrayHasKey('alias', $segments[1]);
        $this->assertIsString($segments[1]['alias']);
        $this->assertIsArray($segments[2]);
        $this->assertArrayHasKey('id', $segments[2]);
        $this->assertIsInt($segments[2]['id']);
        $this->assertArrayHasKey('alias', $segments[2]);
        $this->assertIsString($segments[2]['alias']);
        $this->assertIsArray($segments[3]);
        $this->assertArrayHasKey('id', $segments[3]);
        $this->assertIsInt($segments[3]['id']);
        $this->assertArrayHasKey('alias', $segments[3]);
        $this->assertIsString($segments[3]['alias']);
        $selectedSegments          = $segments;
        $this->created['segments'] = $segments;
        unset($selectedSegments[1]);
        $createdSegmentAlias = 'createdsegmentname';
        $fieldId             = $this->created['contact_field'] = $this->testCreateSingleSelectField(array_merge($selectedSegments, [['name' => 'Created segment name', 'alias' => $createdSegmentAlias]]));
        $contact             = $this->created['contact'] = $this->createContact();
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('fields', $contact);
        $this->assertArrayHasKey('id', $contact);
        $this->assertIsArray($contact['fields']);
        $this->assertArrayHasKey('core', $contact['fields']);
        $this->assertIsArray($contact['fields']['core']);
        $this->assertArrayHasKey('email', $contact['fields']['core']);
        $this->assertIsArray($contact['fields']['core']['email']);
        $this->assertArrayHasKey('value', $contact['fields']['core']['email']);
        $this->assertIsString($contact['fields']['core']['email']['value']);
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
        $this->assertNotFalse($clientResponse->getContent());
        $this->markTestSkipped('This test is not yet implemented');
        self::assertSame(Response::HTTP_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertSame('https://localhost/form/'.$formId.'?mauticError=Errors%3A%3Cbr%20%2F%3E%3Col%3E%3Cli%3EGiven%20list%20does%20not%20exist.%3C%2Fli%3E%3C%2Fol%3E#submission', $clientResponse->headers->get('Location'));
        $this->client->followRedirect();

        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->leadModel->getLeadListRepository();
        $this->assertIsInt($contact['id']);
        $lists = $leadListRepository->getLeadLists($contact['id']);
        $this->assertIsArray($lists);
        self::assertCount(3, $lists);
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[3]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[1]['id'], $list->getId());
        $list = array_pop($lists);
        $this->assertInstanceOf(LeadList::class, $list);
        assert($list instanceof LeadList);
        self::assertSame($segments[0]['id'], $list->getId());
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
                        SettingsType::CHECKBOX => $createMissing ? '1' : '0',
                    ],
                    'order'       => 1,
                ],
            ],
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $response = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('form', $response);
        $this->assertIsArray($response['form']);
        $this->assertArrayHasKey('id', $response['form']);
        $this->assertIsInt($response['form']['id']);

        return $response['form']['id'];
    }

    /**
     * @param array<array<int|string, mixed>> $segments
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
        $this->assertNotFalse($clientResponse->getContent());
        $fieldResponse  = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertIsArray($fieldResponse);
        $this->assertArrayHasKey('field', $fieldResponse);
        $this->assertIsArray($fieldResponse['field']);
        $this->assertArrayHasKey('id', $fieldResponse['field']);
        $this->assertIsInt($fieldResponse['field']['id']);

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
        $this->assertNotFalse($clientResponse->getContent());
        $fieldResponse  = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $this->assertIsArray($fieldResponse);
        $this->assertArrayHasKey('field', $fieldResponse);
        $this->assertIsArray($fieldResponse['field']);
        $this->assertArrayHasKey('id', $fieldResponse['field']);
        $this->assertIsInt($fieldResponse['field']['id']);

        return $fieldResponse['field']['id'];
    }

    /**
     * @return array<array<int,mixed>>
     */
    private function createSegments(): array
    {
        $this->client->request('POST', '/api/segments/batch/new', $this->segments);
        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('statusCodes', $response);
        $this->assertIsArray($response['statusCodes']);
        $this->assertArrayHasKey('lists', $response);
        $this->assertIsArray($response['lists']);
        $this->assertArrayHasKey(0, $response['lists']);
        $this->assertArrayHasKey(1, $response['lists']);
        $this->assertArrayHasKey(2, $response['lists']);
        $this->assertArrayHasKey(3, $response['lists']);

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
     * @return array<mixed, mixed>
     */
    private function createContact(): array
    {
        $this->client->request('POST', '/api/contacts/batch/new', $this->contacts);
        $clientResponse = $this->client->getResponse();
        $this->assertNotFalse($clientResponse->getContent());
        $response       = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('statusCodes', $response);
        $this->assertIsArray($response['statusCodes']);
        $this->assertArrayHasKey('contacts', $response);
        $this->assertIsArray($response['contacts']);
        $this->assertArrayHasKey(0, $response['contacts']);
        $this->assertIsArray($response['contacts'][0]);

        self::assertEquals(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        self::assertCount(1, $response['statusCodes']);
        self::assertEquals(Response::HTTP_CREATED, $response['statusCodes'][0], $clientResponse->getContent());

        return $response['contacts'][0];
    }
}
