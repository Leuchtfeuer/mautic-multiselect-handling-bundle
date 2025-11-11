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
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldActionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormActionUpdateContactSelectFieldFunctionalTest extends MauticMysqlTestCase
{
    private const FIELD_NAME_SELECT = 'test_select_field';

    protected $useCleanupRollback = false;

    /**
     * @var array<array<string, string>>
     */
    private const FIELD_OPTIONS = [
        ['name' => '1 Field', 'alias' => '1field'],
        ['name' => '2 Field', 'alias' => '2field'],
        ['name' => '3 Field', 'alias' => '3field'],
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

    public function testFormContactSelect(): void
    {
        $fieldEntity = $this->createField();

        $selectValue = $fieldEntity->getId().'-'.self::FIELD_OPTIONS[1]['alias'];
        $formEntity  = $this->createForm($fieldEntity->getId(), $selectValue);

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

        $leadAfter = $this->em->getRepository(Lead::class)->findOneBy(['email' => 'contact1@email.com']);
        self::assertNotNull($leadAfter, 'Lead should not be null after submission');
        $field = $leadAfter->getFields()['core'][self::FIELD_NAME_SELECT] ?? null;
        self::assertNotNull($field, 'Field should not be null after submission');
        self::assertSame('2field', $field['value'], 'Field value should match the expected value after submission');
    }

    private function createForm(int $fieldId, string $selectValue = ''): Form
    {
        $properties = [
            'field' => $fieldId,
        ];
        if (!empty($selectValue)) {
            $properties[UpdateSelectFieldActionType::FIELD_SELECT_VALUE] = $selectValue;
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
        $action->setName('Set select field value');
        $action->setDescription('action description');
        $action->setType(FormSubscriber::ACTION_UPDATE_SELECT_CONTACT_FIELD);
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

    private function createField(): LeadField
    {
        $list = [];
        foreach (self::FIELD_OPTIONS as $setting) {
            $list[] = ['label' => $setting['name'], 'value' => $setting['alias']];
        }

        $payload = [
            'label'               => 'Manage select',
            'alias'               => self::FIELD_NAME_SELECT,
            'type'                => 'select',
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
}
