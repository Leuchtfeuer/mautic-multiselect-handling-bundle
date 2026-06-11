<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Tests;

use Doctrine\ORM\EntityManager;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait PluginActivationTrait
{
    private function activatePlugin(bool $isPublished = true): void
    {
        if (!$this instanceof \PHPUnit\Framework\TestCase) {
            throw new \RuntimeException('PluginActivationTrait can only be used in PHPUnit test classes');
        }

        /** @var KernelBrowser $client */
        $client = $this->client ?? throw new \RuntimeException('Property $client not found');
        /** @var EntityManager $em */
        $em = $this->em ?? throw new \RuntimeException('Property $em not found');

        $client->request('GET', '/s/plugins/reload');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $integration = $em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerMultiselect']);
        if (empty($integration)) {
            $plugin      = $em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerMultiselectHandlingBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerMultiselect');
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $em->persist($integration);
        $em->flush();
    }
}
