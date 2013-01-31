<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Memberlist
 * @license    LGPL
 * @filesource
 */


/**
 * Class ModuleMemberlist
 *
 * @copyright  Leo Feyer 2008-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Controller
 */
class ModuleMemberlist extends Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_memberlist';

	/**
	 * Groups
	 * @var array
	 */
	protected $arrMlGroups = array();

	/**
	 * Fields
	 * @var array
	 */
	protected $arrMlFields = array();


	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### MEMBERLIST ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		$this->arrMlGroups = deserialize($this->ml_groups, true);
		$this->arrMlFields = deserialize($this->ml_fields, true);

		if (count($this->arrMlGroups) < 1 || count($this->arrMlFields) < 1)
		{
			return '';
		}

		return parent::generate();
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		$this->import('String');
		$this->loadDataContainer('tl_member');
		$this->loadLanguageFile('tl_member');

		if ($this->Input->get('show'))
		{
			$this->listSingleMember($this->Input->get('show'));
		}
		else
		{
			$this->listAllMembers();
		}
	}


	/**
	 * List all members
	 */
	protected function listAllMembers()
	{
		$time = time();
		$arrFields = $this->arrMlFields;
		$intGroupLimit = (count($this->arrMlGroups) - 1);
		$arrValues = array();
		$strWhere = '';

		// Search query
		if ($this->Input->get('search') && $this->Input->get('for') != '' && $this->Input->get('for') != '*')
		{
			$strWhere .= $this->Input->get('search') . " REGEXP ? AND ";
			$arrValues[] = $this->Input->get('for');
		}

		$strOptions = '';
		$arrSortedFields = array();

		// Sort fields
		foreach ($arrFields as $field)
		{
			$arrSortedFields[$field] = $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0];
		}

		natcasesort($arrSortedFields);

		// Add searchable fields to drop-down menu
		foreach ($arrSortedFields as $k=>$v)
		{
			$strOptions .= '  <option value="' . $k . '"' . (($k == $this->Input->get('search')) ? ' selected="selected"' : '') . '>' . $v . '</option>' . "\n";
		}

		$this->Template->search_fields = $strOptions;
		$strWhere .= "(";

		// Filter groups
		for ($i=0; $i<=$intGroupLimit; $i++)
		{
			if ($i < $intGroupLimit)
			{
				$strWhere .= "groups LIKE ? OR ";
				$arrValues[] = '%"' . $this->arrMlGroups[$i] . '"%';
			}
			else
			{
				$strWhere .= "groups LIKE ?) AND ";
				$arrValues[] = '%"' . $this->arrMlGroups[$i] . '"%';
			}
		}

		// List active members only
		if (in_array('username', $arrFields))
		{
			$strWhere .= "(publicFields!='' OR allowEmail=? OR allowEmail=?) AND disable!=1 AND (start='' OR start<=?) AND (stop='' OR stop>=?)";
			array_push($arrValues, 'email_member', 'email_all', $time, $time);
		}
		else
		{
			$strWhere .= "publicFields!='' AND disable!=1 AND (start='' OR start<=?) AND (stop='' OR stop>=?)";
			array_push($arrValues, $time, $time);
		}

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['memberlistQuery']) && is_array($GLOBALS['TL_HOOKS']['memberlistQuery']))
		{
			foreach ($GLOBALS['TL_HOOKS']['memberlistQuery'] as $callback)
			{
				$this->import($callback[0]);
				$this->$callback[0]->$callback[1]($this, $strWhere, $arrValues);
			}
		}

		// Get total number of members
		$objTotal = $this->Database->prepare("SELECT COUNT(*) AS count FROM tl_member WHERE " . $strWhere)
						 ->execute($arrValues);

		// Split results
		$page = $this->Input->get('page') ? $this->Input->get('page') : 1;
		$per_page = $this->Input->get('per_page') ? $this->Input->get('per_page') : $this->perPage;
		$order_by = "";
		if (strlen($this->ml_sort) && (!strlen($this->Input->get('order_by'))) && (in_array($this->ml_sort, $this->arrMlFields))) 
		{
			$order_by = $this->ml_sort;
		}
		else
		{
			$order_by = $this->Input->get('order_by') ? $this->Input->get('order_by') . ' ' . $this->Input->get('sort') : 'username';
		}


		// Begin query
		$objMemberStmt = $this->Database->prepare("SELECT id, username, publicFields, " . implode(', ', $this->arrMlFields) . " FROM tl_member WHERE " . $strWhere . " ORDER BY " . $order_by);

		// Limit
		if ($per_page)
		{
			$objMemberStmt->limit($per_page, (($page - 1) * $per_page));
		}

		$objMember = $objMemberStmt->execute($arrValues);

		// Prepare URL
		if ($GLOBALS['TL_CONFIG']['disableAlias'] == true)
		{
			$strUrl = preg_replace('/\&.*$/', '', $this->Environment->request);
			$this->Template->url = $strUrl . '&amp;';
			$blnQuery = true;
		}
		else
		{
			$strUrl = preg_replace('/\?.*$/', '', $this->Environment->request);
			$this->Template->url = $strUrl . '?';
			$blnQuery = false;
		}

		// Add GET parameters
		foreach (preg_split('/&(amp;)?/', $_SERVER['QUERY_STRING']) as $fragment)
		{
			if (strlen($fragment) && strncasecmp($fragment, 'order_by', 8) !== 0 && strncasecmp($fragment, 'sort', 4) !== 0 && strncasecmp($fragment, 'page', 4) !== 0 && strncasecmp($fragment, 'id', 2) !== 0)
			{
				$strUrl .= (!$blnQuery ? '?' : '&amp;') . $fragment;
				$blnQuery = true;
			}
		}

		$strVarConnector = $blnQuery ? '&amp;' : '?';

		// Prepare table
		$arrTh = array();
		$arrTd = array();

		// THEAD
		for ($i=0; $i<count($arrFields); $i++)
		{
			$class = '';
			$sort = 'asc';
			$strField = strlen($label = $GLOBALS['TL_DCA']['tl_member']['fields'][$arrFields[$i]]['label'][0]) ? $label : $arrFields[$i];

			if ($this->Input->get('order_by') == $arrFields[$i])
			{
				$sort = ($this->Input->get('sort') == 'asc') ? 'desc' : 'asc';
				$class = ' sorted ' . $this->Input->get('sort');
			}

			$arrTh[] = array
			(
				'link' => $strField,
				'href' => (ampersand($strUrl) . $strVarConnector . 'order_by=' . $arrFields[$i]) . '&amp;sort=' . $sort,
				'title' => specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['list_orderBy'], $strField)),
				'class' => $class . (($i == 0) ? ' col_first' : '')
			);
		}

		$start = -1;
		$limit = $objMember->numRows;

		// TBODY
		while ($objMember->next())
		{
			$publicFields = deserialize($objMember->publicFields, true);
			$class = 'row_' . ++$start . (($start == 0) ? ' row_first' : '') . ((($start + 1) == $limit) ? ' row_last' : '') . ((($start % 2) == 0) ? ' even' : ' odd');

			foreach ($arrFields as $k=>$v)
			{
				$value = '-';

				if ($v == 'username' || in_array($v, $publicFields))
				{
					$value = $this->formatValue($v, $objMember->$v);
				}

				$arrData = $objMember->row();
				unset($arrData['publicFields']);

				$arrTd[$class][$k] = array
				(
					'raw' => $arrData,
					'content' => $value,
					'class' => 'col_' . $k . (($k == 0) ? ' col_first' : ''),
					'id' => $objMember->id,
					'field' => $v
				);
			}
		}

		$this->Template->col_last = 'col_' . ++$k;
		$this->Template->thead = $arrTh;
		$this->Template->tbody = $arrTd;

		// Pagination
		$objPagination = new Pagination($objTotal->count, $per_page);
		$this->Template->pagination = $objPagination->generate("\n  ");
		$this->Template->per_page = $per_page;

		// Template variables
		$this->Template->action = $this->getIndexFreeRequest();
		$this->Template->search_label = specialchars($GLOBALS['TL_LANG']['MSC']['search']);
		$this->Template->per_page_label = specialchars($GLOBALS['TL_LANG']['MSC']['list_perPage']);
		$this->Template->fields_label = $GLOBALS['TL_LANG']['MSC']['all_fields'][0];
		$this->Template->keywords_label = $GLOBALS['TL_LANG']['MSC']['keywords'];
		$this->Template->search = $this->Input->get('search');
		$this->Template->for = $this->Input->get('for');
		$this->Template->order_by = $this->Input->get('order_by');
		$this->Template->sort = $this->Input->get('sort');
		$this->Template->total_members = $objTotal->count;
	}


	/**
	 * List a single member
	 * @param integer
	 */
	protected function listSingleMember($id)
	{
		global $objPage;

		$time = time();
		$this->Template = new FrontendTemplate('mod_memberlist_detail');
		$this->Template->record = array();

		// Get member
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id=? AND disable!=1 AND (start='' OR start<=$time) AND (stop='' OR stop>=$time)")
									->limit(1)
									->execute($id);

		// No member found or group not allowed
		if ($objMember->numRows < 1 || count(array_intersect(deserialize($objMember->groups, true), $this->arrMlGroups)) < 1)
		{
  			$this->Template->invalid = $GLOBALS['TL_LANG']['MSC']['invalidUserId'];

			// Do not index the page
			$objPage->noSearch = 1;
			$objPage->cache = 0;

			// Send 404 header
			header('HTTP/1.1 404 Not Found');
			return;
		}

		// Default variables
		$this->Template->action = $this->getIndexFreeRequest();
		$this->Template->referer = 'javascript:history.go(-1)';
		$this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
		$this->Template->publicProfile = sprintf($GLOBALS['TL_LANG']['MSC']['publicProfile'], $objMember->username);
		$this->Template->noPublicInfo = $GLOBALS['TL_LANG']['MSC']['noPublicInfo'];
		$this->Template->sendEmail = $GLOBALS['TL_LANG']['MSC']['sendEmail'];
		$this->Template->submit = $GLOBALS['TL_LANG']['MSC']['sendMessage'];
		$this->Template->loginToSend = $GLOBALS['TL_LANG']['MSC']['loginToSend'];
		$this->Template->emailDisabled = $GLOBALS['TL_LANG']['MSC']['emailDisabled'];

		// Confirmation message
		if ($_SESSION['TL_EMAIL_SENT'])
		{
			$this->Template->confirm = $GLOBALS['TL_LANG']['MSC']['messageSent'];
			$_SESSION['TL_EMAIL_SENT'] = false;
		}

		// Check personal message settings
		switch ($objMember->allowEmail)
		{
			case 'email_all':
				$this->Template->allowEmail = 3;
				break;

			case 'email_member':
				$this->Template->allowEmail = FE_USER_LOGGED_IN ? 3 : 2;
				break;

			default:
				$this->Template->allowEmail = 1;
				break;
		}

		// No e-mail address given
		if (!strlen($objMember->email))
		{
			$this->Template->allowEmail = 1;
		}

		// Handle personal messages
		if ($this->Template->allowEmail > 1)
		{
			$arrFields = array(
				array
				(
					'name'      => 'email',
					'label'     => $GLOBALS['TL_LANG']['MSC']['email'],
					'inputType' => 'text',
					'default'   => $objMember->email,
					'eval'      => array('mandatory'=>true, 'required'=>true, 'maxlength'=>255, 'rgxp'=>'email', 'decodeEntities'=>true)
				),
				array
				(
					'name'      => 'message',
					'label'     => $GLOBALS['TL_LANG']['MSC']['message'],
					'inputType' => 'textarea',
					'eval'      => array('mandatory'=>true, 'required'=>true, 'rows'=>4, 'cols'=>40, 'decodeEntities'=>true)
				),
				array
				(
					'id' => 'registration',
					'label' => $GLOBALS['TL_LANG']['MSC']['securityQuestion'],
					'inputType' => 'captcha',
					'question' => '',
					'type' => 'captcha',
					'mandatory' => true,
					'required' => true,
					'name'      => 'captcha'
				)
			);

			$hasErrors = false;
			$widgets = array();
			foreach ($arrFields as $arrField)
			{
				$arrWidget = $this->prepareForWidget($arrField, $arrField['name'], $arrField['default']);
				$objWidget = null;
				switch ($arrField['inputType'])
				{
					case 'textarea':
						$objWidget = new FormTextArea($arrWidget);
						break;
					case 'text':
						$objWidget = new FormTextField($arrWidget);
						break;
					case 'captcha':
						$objWidget = new FormCaptcha($arrWidget);
						break;
				}

				// Validate widget
				if ($this->Input->post('FORM_SUBMIT') == 'tl_send_email')
				{
					$objWidget->validate();

					if ($objWidget->hasErrors())
					{
						$hasErrors = true;
					}
				}
				array_push($widgets, $objWidget);
			}
			if ($this->Input->post('FORM_SUBMIT') == 'tl_send_email' && !$hasErrors)
			{
				if (!$hasErrors) $this->sendPersonalMessage($objMember, $widgets[0]->value, $widgets[1]->value);
			}
			
			$this->Template->widgets = $widgets;
			$this->Template->submit = $GLOBALS['TL_LANG']['MSC']['sendMessage'];
		}

		$arrFields = deserialize($objMember->publicFields);

		// Add public fields
		if (is_array($arrFields) && count($arrFields))
		{
			$count = -1;

			foreach ($arrFields as $k=>$v)
			{
				$class = 'row_' . ++$count . (($count == 0) ? ' row_first' : '') . (($count >= (count($arrFields) - 1)) ? ' row_last' : '') . ((($count % 2) == 0) ? ' even' : ' odd');

				$arrFields[$k] = array
				(
					'raw' => $objMember->row(),
					'content' => $this->formatValue($v, $objMember->$v, true),
					'class' => $class,
					'label' => (strlen($label = $GLOBALS['TL_DCA']['tl_member']['fields'][$v]['label'][0]) ? $label : $v),
					'field' => $v
				);
			}

			$this->Template->record = $arrFields;
		}
	}


	/**
	 * Send a personal message
	 * @param object
	 * @param object
	 */
	protected function sendPersonalMessage(Database_Result $objMember, $email, $text)
	{
		$objEmail = new Email();

		$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
		$objEmail->fromName = 'Contao mailer';
		$objEmail->text = $text;

		// Add reply to
		if (FE_USER_LOGGED_IN)
		{
			$this->import('FrontendUser', 'User');
			$replyTo = strlen($email) ? $email : $this->User->email;

			// Add name
			if (strlen($this->User->firstname))
			{
				$replyTo = $this->User->firstname . ' ' . $this->User->lastname . ' <' . $replyTo . '>';
			}

			$objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['subjectFeUser'], $this->User->username, $this->Environment->host);
			$objEmail->text .= "\n\n---\n\n" . sprintf($GLOBALS['TL_LANG']['MSC']['sendersProfile'], $this->Environment->base . preg_replace('/show=[0-9]+/', 'show=' . $this->User->id, $this->Environment->request));

			$objEmail->replyTo($replyTo);
		}
		else
		{
			$objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['subjectUnknown'], $this->Environment->host);
		}

		// Send e-mail
		$objEmail->sendTo($objMember->email);
		$_SESSION['TL_EMAIL_SENT'] = true;

		$this->reload();
	}


	/**
	 * Format a value
	 * @param string
	 * @param mixed
	 * @param boolean
	 * @return mixed
	 */
	protected function formatValue($k, $value, $blnListSingle=false)
	{
		$value = deserialize($value);

		// Return if empty
		if ($value == '')
		{
			if (isset($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference']['']))
			{
				return $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'][''];
			}

			return '-';
		}

		// Array
		if (is_array($value))
		{
			$value = implode(', ', $value);
		}

		// Date
		elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'date')
		{
			$value = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $value);
		}

		// Time
		elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'time')
		{
			$value = $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $value);
		}

		// Date and time
		elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'datim')
		{
			$value = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $value);
		}

		// URLs
		elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'url' && preg_match('@^(https?://|ftp://)@i', $value))
		{
			$value = '<a href="' . $value . '"' . LINK_NEW_WINDOW . '>' . $value . '</a>';
		}

		// E-mail addresses
		elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'email')
		{
			$value = $this->String->encodeEmail($value);
			$value = '<a href="&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $value . '">' . $value . '</a>';
		}

		// Reference
		elseif (is_array($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference']))
		{
			$value = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'][$value];
		}

		// Associative array
		elseif (array_is_assoc($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options']))
		{
			if ($blnListSingle)
			{
				$value = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options'][$value];
			}
			else
			{
				$value = '<span class="value">[' . $value . ']</span> ' . $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options'][$value];
			}
		}

		return strlen($value) ? $value :  '-';
	}
}

?>