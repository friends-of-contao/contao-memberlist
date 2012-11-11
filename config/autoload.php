<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package Memberlist
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Models
	'Contao\MemberlistMemberModel' => 'system/modules/memberlist/models/MemberlistMemberModel.php',

	// Modules
	'Contao\ModuleMemberlist'      => 'system/modules/memberlist/modules/ModuleMemberlist.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'mod_memberlist'        => 'system/modules/memberlist/templates',
	'mod_memberlist_detail' => 'system/modules/memberlist/templates',
));
