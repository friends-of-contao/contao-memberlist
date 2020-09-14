<?php

/**
 * Contao Open Source CMS
 */

declare (strict_types = 1);

namespace Foc\Memberlist\EventListener\Initialize;

class System
{
    public function onKernelRequest(): void
    {
        $GLOBALS['TL_MODELS']['tl_member'] = 'Foc\Memberlist\Model\MemberlistMemberModel';
    }
}

