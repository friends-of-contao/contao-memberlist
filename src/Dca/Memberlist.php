<?php

namespace Foc\Memberlist\Dca;

use Contao\Backend;

/**
 * Class tl_member_memberlist
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2013
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */
class Memberlist
{

    /**
     * Return all editable fields of table tl_member
     * @return array
     */
    public function getViewableMemberProperties()
    {
        $return = array();

        Backend::loadLanguageFile('tl_member');
        Backend::loadDataContainer('tl_member');

        foreach ($GLOBALS['TL_DCA']['tl_member']['fields'] as $k => $v) {
            if (in_array($k, ['username', 'password', 'newsletter', 'publicFields', 'allowEmail'])) {
                continue;
            }

            if ($v['eval']['feViewable']) {
                $return[$k] = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['label'][0];
            }
        }

        return $return;
    }
}
