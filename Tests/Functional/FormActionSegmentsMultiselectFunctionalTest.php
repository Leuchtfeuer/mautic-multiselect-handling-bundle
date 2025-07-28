<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormActionSegmentsMultiselectFunctionalTest extends MauticMysqlTestCase
{
    private const FIELD_NAME_MULTISELECT = 'test_multiselect_field';
    private const FIELD_NAME_SELECT      = 'test_select_field';

    /**
     * @var array<array<string, string>>
     */
    private array $fieldData = [
        [
            'name'  => '1 Field',
            'alias' => '1field',
        ],
        [
            'name'  => '2 Field',
            'alias' => '2field',
        ],
        [
            'name'  => '3 Field',
            'alias' => '3field',
        ],
        [
            'name'  => '4 Field',
            'alias' => '4field',
        ],
        [
            'name'  => '2 Other',
            'alias' => '2other',
        ],
        [
            'name'  => '3 Other',
            'alias' => '3other',
        ],
    ];

    /**
     * @var array<array<string, mixed>>
     */
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
            self::FIELD_NAME_MULTISELECT => ['1field', '4field', '2other'],
            //            self::FIELD_NAME_SELECT      => 'field1',
        ],
        [
            'email'                      => 'contact3@email.com',
            'firstname'                  => 'Arthur C.',
            'lastname'                   => 'Clarke',
            self::FIELD_NAME_MULTISELECT => ['1field', '2other'],
            //            self::FIELD_NAME_SELECT      => 'other2',
        ],
        [
            'email'                      => 'contact4@email.com',
            'firstname'                  => 'Jonathan',
            'lastname'                   => 'Dafis',
            self::FIELD_NAME_MULTISELECT => ['4field', '3other'],
            //            self::FIELD_NAME_SELECT      => 'field4',
        ],
        [
            'email'                      => 'contact5@email.com',
            'firstname'                  => 'Grzegorz',
            'lastname'                   => 'Brzeczyszczykiewicz',
            self::FIELD_NAME_MULTISELECT => ['4field', '3field'],
            //            self::FIELD_NAME_SELECT      => 'field3',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
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

    public function testFormContactMultiselect(): void
    {
        $fieldEntity = $this->createField(true);

        $contacts = $this->createContacts();
        $contact  = $contacts[0];

        $values = [];
        foreach ($fieldEntity->getProperties()['list'] as $value) {
            $values[] = $fieldEntity->getId().'-'.$value['value'];
        }
        $removedValues   = [];
        $removedValues[] = $values[0];
        $removedValues[] = $values[1];
        unset($values[0]); // remove first value to test multiselect handling
        unset($values[1]); // remove first value to test multiselect handling

        $formEntity = $this->createForm($fieldEntity->getId(), $values);

        $crawler = $this->client->request(Request::METHOD_GET, '/s/forms/preview/'.$formEntity->getId());
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());
        $form                         = $crawler->filter('form[id="mauticform_submissiontestform"]')->form();
        $values                       = $form->getValues();
        $values['mauticform[email]']  = 'contact1@email.com';
        $values['mauticform[return]'] = '';
        $form->setValues($values);
        $this->client->submit($form);

        $defaultValues                         = $form->getPhpValues();
        $defaultValues['mauticform']['email']  = 'contact1@email.com';
        $defaultValues['mauticform']['return'] = '';
        $this->client->request(Request::METHOD_POST, '/form/submit?formId='.$formEntity->getId(), $defaultValues);
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());

        $leadAfter = $this->em->getRepository(Lead::class)->find($contact->getId());
        self::assertNotNull($leadAfter, 'Lead should not be null after submission');
        $field = $leadAfter->getFields()['core'][self::FIELD_NAME_MULTISELECT] ?? null;
        self::assertNotNull($field, 'Field should not be null after submission');
        self::assertSame('3field|4field|2other|3other', $field['value'], 'Field value should match the expected value after submission');
        self::assertStringNotContainsString($removedValues[0], $field['value'], 'Field value should not contain the first removed value');
        self::assertStringNotContainsString($removedValues[1], $field['value'], 'Field value should not contain the second removed value');
    }

    private function createForm(int $fieldId, array $multiSelectAdd = [], array $multiSelectRemove = []): Form
    {
        $properties = [
            'field' => $fieldId,
        ];
        if (!empty($multiSelectAdd)) {
            $properties['multiselect_add'] = $multiSelectAdd;
        }
        if (!empty($multiSelectRemove)) {
            $properties['multiselect_remove'] = $multiSelectRemove;
        }
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
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        $response   = json_decode($clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $formEntity = $this->em->getRepository(Form::class)->find($response['form']['id']);

        $action = new Action();
        $action->setName('Manage segments');
        $action->setDescription('action description');
        $action->setType(FormSubscriber::ACTION_MULTISELECT_CONTACT);
        $action->setProperties($properties);
        $action->setForm($formEntity);
        $action->setOrder(1);
        $this->em->persist($action);
        $this->em->flush();

        $formEntity->addAction($action->getId(), $action);
        $this->em->persist($formEntity);
        $this->em->flush();

        return $formEntity;
    }

    private function createField(bool $multiselect): LeadField
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

        $field = $this->em->getRepository(LeadField::class)->find($fieldResponse['field']['id']);

        return $field;
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

        $contacts = [];
        foreach ($response['contacts'] as $contact) {
            $contacts[] = $this->em->getRepository(Lead::class)->find($contact['id']);
        }

        return $contacts;
    }
}
