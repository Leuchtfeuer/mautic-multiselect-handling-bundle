<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\LeuchtfeuerMultiselectIntegration;

class ConfigSupport extends LeuchtfeuerMultiselectIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
