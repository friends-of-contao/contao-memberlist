<?php

/**
 * @copyright  Helmut Schottmüller 2013
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Memberlist
 * @license    LGPL
 * @filesource
 */

use Contao\ModuleMemberlist;

/**
 * Add palettes to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['palettes']['default'] = str_replace('login;', 'login;{profile_legend:hide},allowEmail,publicFields;', $GLOBALS['TL_DCA']['tl_member']['palettes']['default']);


/**
 * Add fields to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['allowEmail'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_member']['allowEmail'],
	'default'       => 'email_member',
	'exclude'       => true,
	'inputType'     => 'select',
	'options'       => array('email_member', 'email_all', 'email_nobody'),
	'reference'     => &$GLOBALS['TL_LANG']['tl_member'],
	'eval'          => array('feEditable'=>true, 'feGroup'=>'profile'),
	'sql'           => "varchar(32) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_member']['fields']['publicFields'] = array
(
	'label'              => &$GLOBALS['TL_LANG']['tl_member']['publicFields'],
	'exclude'            => true,
	'inputType'          => 'checkbox',
	'options_callback'   => array('tl_member_memberlist', 'getViewableMemberProperties'),
	'eval'               => array('multiple'=>true, 'feEditable'=>true, 'feGroup'=>'profile'),
	'sql'                => "blob NULL"
);


/**
 * Class tl_member_memberlist
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2013
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */
class tl_member_memberlist extends Backend
{

	/**
	 * Return all editable fields of table tl_member
	 * @return array
	 */
	public function getViewableMemberProperties()
	{
		$return = array();

		$this->loadLanguageFile('tl_member');
		$this->loadDataContainer('tl_member');

		foreach (array_keys($GLOBALS['TL_DCA']['tl_member']['fields']) as $field)
		{
			if ($field == 'username' || $field == 'password' || $field == 'newsletter' || $field == 'publicFields' || $field == 'allowEmail')
			{
				continue;
			}

			if (ModuleMemberlist::isViewable($field))
			{
				$return[$field] = $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0];
			}
		}

		return $return;
	}
}
