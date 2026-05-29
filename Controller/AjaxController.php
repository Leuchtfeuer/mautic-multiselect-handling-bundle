<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\FieldModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AjaxController extends CommonAjaxController
{
    public function __construct(
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
        private FieldModel $fieldModel,
    ) {
        parent::__construct($doctrine, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    public function getMultiselectOptionsAction(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $id = $request->get('id');
        if (!is_numeric($id) && !is_string($id) && !is_array($id) && null !== $id) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Invalid ID parameter']);
        }

        $fieldEntity = $this->fieldModel->getEntity($id);
        if (!$fieldEntity) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Field entity not found']);
        }
        if (!$fieldEntity->getType() || (!in_array($fieldEntity->getType(), ['multiselect', 'select']))) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Field is not a multiselect or select']);
        }
        if (!$fieldEntity->getProperties()) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Field properties are not set']);
        }
        if (!isset($fieldEntity->getProperties()['list'])) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Field properties list is not set']);
        }
        $list = $fieldEntity->getProperties()['list'];
        if (!is_array($list)) {
            return $this->sendJsonResponse(['success' => 0, 'message' => 'Field properties list is not an array']);
        }
        foreach ($list as $key => $value) {
            if (!is_array($value) || !isset($value['label'], $value['value'])) {
                continue;
            }
            if (!is_string($value['label']) || (!is_string($value['value']) && !is_int($value['value']))) {
                continue;
            }
            \assert(is_array($list[$key]));
            \assert(array_key_exists('label', $list[$key]));
            \assert(array_key_exists('value', $list[$key]));
            /** @var array{label: string, value: string|int} $item */
            $item          = $list[$key];
            $item['label'] = '('.$fieldEntity->getName().') '.$value['label'];
            $item['value'] = $fieldEntity->getId().'-'.$value['value'];
            $list[$key]    = $item;
        }

        return $this->sendJsonResponse(['success' => 1, 'data' => $list]);
    }
}
