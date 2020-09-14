<?php

namespace Foc\Memberlist\ContaoManager;

use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

use Contao\CoreBundle\ContaoCoreBundle; 
use Foc\Memberlist\FocMemberlistBundle;

/**
 * Plugin for the Contao Manager.
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(FocMemberlistBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];

    }
}