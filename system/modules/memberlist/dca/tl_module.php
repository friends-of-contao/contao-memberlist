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
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['memberlist'] = '{title_legend},name,headline,type;{config_legend},ml_groups,ml_fields,perPage;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';


/**
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['ml_groups'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['ml_groups'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'foreignKey'    => 'tl_member_group.name',
	'eval'          => array('mandatory'=>true, 'multiple'=>true),
	'sql'           => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['ml_fields'] = array
(
	'label'              => &$GLOBALS['TL_LANG']['tl_module']['ml_fields'],
	'exclude'            => true,
	'inputType'          => 'checkboxWizard',
	'options_callback'   => array('tl_module_memberlist', 'getViewableMemberProperties'),
	'eval'               => array('multiple'=>true),
	'sql'                => "blob NULL"
);


/**
 * Class tl_module_memberlist
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Helmut Schottmüller 2013
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */
class tl_module_memberlist extends Backend
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
