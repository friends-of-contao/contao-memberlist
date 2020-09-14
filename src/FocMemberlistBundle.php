<?php

namespace Foc\Memberlist;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Foc\Memberlist\DependencyInjection\MemberlistExtension;

class FocMemberlistBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new MemberlistExtension();
    }
}