<?php

/**
 * Contao Open Source CMS
 */

/**
 * @file ApiController.php
 * @class ApiController
 * @author Sascha Weidner
 */

namespace Foc\Memberlist\Controller\FrontendModule;

use Contao\Date;
use Contao\Image;
use Contao\Email;
use Contao\Widget;
use Contao\Input;
use Contao\System;
use Contao\Template;
use Contao\ArrayUtil;
use Contao\BackendUser;
use Contao\FilesModel;
use Contao\Pagination;
use Contao\Controller;
use Contao\StringUtil;
use Contao\ModuleModel;
use Contao\Environment;
use Contao\FormTextArea;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Foc\Memberlist\Model\MemberlistMemberModel;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;

class Memberlist extends AbstractFrontendModuleController
{

    /**
     * @var Template
     */
    private $Template;

	/**
	 * @var Security
	 */
	private $security = null;


	/**
	 * @var SessionInterface
	 */
	private $session = null;

	/**
	 * @var FrontendUser
	 */
	private $User = null;

	public function __construct(Security $security, SessionInterface $session)
	{
		$this->security = $security;
        $this->session = $session;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->Template = $template;

        if (($this->User = $this->security->getUser()) instanceof BackendUser) {
			$this->Template->name = 'be_wildcard';

			$this->Template->wildcard = '### MEMBERLIST ###';
			$this->Template->title = $model->headline;
			$this->Template->id = $model->id;
			$this->Template->link = $model->name;
			$this->Template->href = 'contao/main.php?do=modules&amp;act=edit&amp;id=' . $model->id;

			return $this->Template->getResponse();
        }
        
		$this->arrMlGroups = StringUtil::deserialize($model->ml_groups, true);
		$this->arrMlFields = StringUtil::deserialize($model->ml_fields, true);

		if (count($this->arrMlGroups) < 1 || count($this->arrMlFields) < 1)
		{
			return new Response('');
		}

        Controller::loadDataContainer('tl_member');
        Controller::loadLanguageFile('tl_member');

        if (Input::get('show')) {
            $this->listSingleMember(Input::get('show'));
        } else {
            $this->listAllMembers();
        }

        // die('<pre>' . __METHOD__ . ":\n" . print_r($this->Template, true) . "\n#################################\n\n" . '</pre>');

        return $this->Template->getResponse();
    }

    /**
     * List all members
     */
    protected function listAllMembers()
    {
        $arrSortedFields = [];

        // Sort fields
        foreach ($this->arrMlFields as $field) {
            $arrSortedFields[$field] = $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0];
        }

        natcasesort($arrSortedFields);

        $strOptions = '';
        // Add searchable fields to drop-down menu
        foreach ($arrSortedFields as $k => $v) {
            $strOptions .= '  <option value="' . $k . '"' . (($k == Input::get('search')) ? ' selected="selected"' : '') . '>' . $v . '</option>' . "\n";
        }

        $this->Template->search_fields = $strOptions;

        $order_by = Input::get('order_by') ? Input::get('order_by') . ' ' . Input::get('sort') : 'username';
        // Split results
        $page = Input::get('page') ? Input::get('page') : 1;
        $per_page = Input::get('per_page') ? Input::get('per_page') : $this->perPage;

        // Limit
        $limit = 0;
        $offset = 0;
        if ($per_page) {
            $limit = $per_page;
            $offset = (($page - 1) * $per_page);
        }

        $additionaloptions = [];
        // HOOK: Custom member list options
        if (isset($GLOBALS['TL_HOOKS']['setMemberlistOptions']) && is_array($GLOBALS['TL_HOOKS']['setMemberlistOptions'])) {
            foreach ($GLOBALS['TL_HOOKS']['setMemberlistOptions'] as $callback) {
                $ImportedClass = System::importStatic($callback[0]);
                $additionaloptions  = $ImportedClass->{$callback[1]}($this);
            }
        }

        $memberCollection = MemberlistMemberModel::findActiveMembers($this->arrMlFields, $this->arrMlGroups, $order_by, $additionaloptions, $limit, $offset, Input::get('search'), Input::get('for'));
        $total = MemberlistMemberModel::countActiveMembers($this->arrMlFields, $this->arrMlGroups, $additionaloptions, Input::get('search'), Input::get('for'));

        // Prepare URL
        $strUrl = preg_replace('/\?.*$/', '', Environment::get('request'));
        $this->Template->url = $strUrl;
        $blnQuery = false;

        // Add GET parameters
        foreach (preg_split('/&(amp;)?/', $_SERVER['QUERY_STRING']) as $fragment) {
            if (strlen($fragment) && strncasecmp($fragment, 'order_by', 8) !== 0 && strncasecmp($fragment, 'sort', 4) !== 0 && strncasecmp($fragment, 'page', 4) !== 0) {
                $strUrl .= (!$blnQuery ? '?' : '&amp;') . $fragment;
                $blnQuery = true;
            }
        }

        $strVarConnector = $blnQuery ? '&amp;' : '?';

        // Prepare table
        $arrTh = [];
        $arrTd = [];

        // THEAD
        for ($i = 0; $i < count($this->arrMlFields); $i++) {
            $class = '';
            $sort = 'asc';
            $strField = strlen($label = $GLOBALS['TL_DCA']['tl_member']['fields'][$this->arrMlFields[$i]]['label'][0]) ? $label : $this->arrMlFields[$i];

            if (Input::get('order_by') == $this->arrMlFields[$i]) {
                $sort = (Input::get('sort') == 'asc') ? 'desc' : 'asc';
                $class = ' sorted ' . Input::get('sort');
            }

            $arrTh[] = array(
                'link' => $strField,
                'href' => (StringUtil::ampersand($strUrl) . $strVarConnector . 'order_by=' . $this->arrMlFields[$i]) . '&amp;sort=' . $sort,
                'title' => StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['list_orderBy'], $strField)),
                'class' => $class . (($i == 0) ? ' col_first' : '')
            );
        }

        $start = -1;
        if ($memberCollection) {
            $lim = $memberCollection->count();

            // TBODY
            while ($memberCollection->next()) {
                $publicFields = StringUtil::deserialize($memberCollection->publicFields, true);
                $class = 'row_' . ++$start . (($start == 0) ? ' row_first' : '') . ((($start + 1) == $lim) ? ' row_last' : '') . ((($start % 2) == 0) ? ' even' : ' odd');

				$arrMlFields = $this->arrMlFields;
				foreach ($arrMlFields as $k=>$v)
				{
					try {
						$Related = $memberCollection->getRelated($v);
						if($Related !== null) {
							$memberCollection->{$v} = $Related->fetchAll();
							unset($arrMlFields[$v]);
						}
					} catch(\Exception $e) {}
				}
				foreach ($arrMlFields as $k=>$v)
				{
					$value = '-';

					if ($v == 'username' || in_array($v, $publicFields))
					{
						$value = $this->formatValue($v, $memberCollection->$v);
					}


					$arrData = $memberCollection->row();
					unset($arrData['publicFields']);

					$arrTd[$class][$k] = array
					(
						'raw' => $arrData,
						'content' => $value,
						'class' => 'col_' . $k . (($k == 0) ? ' col_first' : ''),
						'id' => $memberCollection->id,
						'field' => $v
					);
				}
            }
        }

        $this->Template->col_last = 'col_' . ++$k;
        $this->Template->thead = $arrTh;
        $this->Template->tbody = $arrTd;

        // Pagination
        $objPagination = new Pagination($total, $per_page);
        $this->Template->pagination = $objPagination->generate("\n  ");
        $this->Template->per_page = $per_page;

        // Template variables
        $this->Template->action = Environment::get('indexFreeRequest');
        $this->Template->search_label = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['search']);
        $this->Template->per_page_label = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['list_perPage']);
        $this->Template->fields_label = $GLOBALS['TL_LANG']['MSC']['all_fields'][0];
        $this->Template->keywords_label = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->search = Input::get('search');
        $this->Template->for = Input::get('for');
        $this->Template->order_by = Input::get('order_by');
        $this->Template->sort = Input::get('sort');
    }


    /**
     * List a single member
     * @param integer
     */
    protected function listSingleMember($id)
    {
        global $objPage;

        $time = time();
        $this->Template->setName('mod_memberlist_detail');
        $this->Template->record = [];

        // Get member
        $objMember = MemberlistMemberModel::findActiveById($id);

        // die('<pre>' . __METHOD__ . ":\n" . print_r($objMember, true) . "\n#################################\n\n" . '</pre>');

        // No member found or group not allowed
        if (null == $objMember || count(array_intersect(StringUtil::deserialize($objMember->groups, true), $this->arrMlGroups)) < 1) {
            $this->Template->invalid = $GLOBALS['TL_LANG']['MSC']['invalidUserId'];

            // die('<pre>' . __METHOD__ . ":\n" . print_r('ASD', true) . "\n#################################\n\n" . '</pre>');
            // Do not index the page
            $objPage->noSearch = 1;
            $objPage->cache = 0;

            // Send 404 header
            header('HTTP/1.1 404 Not Found');
            return;
        }

        // Default variables
        $this->Template->action = Environment::get('indexFreeRequest');
        $this->Template->referer = 'javascript:history.go(-1)';
        $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
        $this->Template->publicProfile = sprintf($GLOBALS['TL_LANG']['MSC']['publicProfile'], $objMember->username);
        $this->Template->noPublicInfo = $GLOBALS['TL_LANG']['MSC']['noPublicInfo'];
        $this->Template->sendEmail = $GLOBALS['TL_LANG']['MSC']['sendEmail'];
        $this->Template->submit = $GLOBALS['TL_LANG']['MSC']['sendMessage'];
        $this->Template->loginToSend = $GLOBALS['TL_LANG']['MSC']['loginToSend'];
        $this->Template->emailDisabled = $GLOBALS['TL_LANG']['MSC']['emailDisabled'];

        // Confirmation message
        if ($_SESSION['TL_EMAIL_SENT']) {
            $this->Template->confirm = $GLOBALS['TL_LANG']['MSC']['messageSent'];
            $_SESSION['TL_EMAIL_SENT'] = false;
        }

        // Check personal message settings
        switch ($objMember->allowEmail) {
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
        if (!strlen($objMember->email)) {
            $this->Template->allowEmail = 1;
        }

        // Handle personal messages
        if ($this->Template->allowEmail > 1) {
            $arrField = array(
                'name'      => 'message',
                'label'     => $GLOBALS['TL_LANG']['MSC']['message'],
                'inputType' => 'textarea',
                'eval'      => array('mandatory' => true, 'required' => true, 'rows' => 4, 'cols' => 40, 'decodeEntities' => true)
            );

            $arrWidget = Widget::getAttributesFromDca($arrField, $arrField['name'], '');
            $objWidget = new FormTextArea($arrWidget);

            // Validate widget
            if ($this->Input->post('FORM_SUBMIT') == 'tl_send_email') {
                $objWidget->validate();

                if (!$objWidget->hasErrors()) {
                    $this->sendPersonalMessage($objMember, $objWidget);
                }
            }

            $this->Template->widget = $objWidget;
            $this->Template->submit = $GLOBALS['TL_LANG']['MSC']['sendMessage'];
        }

        $arrFields = StringUtil::deserialize($objMember->publicFields);

        // Add public fields
        if (is_array($arrFields) && count($arrFields)) {
            $count = -1;

            foreach ($arrFields as $k => $v) {
                $class = 'row_' . ++$count . (($count == 0) ? ' row_first' : '') . (($count >= (count($arrFields) - 1)) ? ' row_last' : '') . ((($count % 2) == 0) ? ' even' : ' odd');

                $arrFields[$k] = array(
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
    protected function sendPersonalMessage($objMember, Widget $objWidget)
    {
        $objEmail = new Email();

        $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
        $objEmail->fromName = 'Contao mailer';
        $objEmail->text = $objWidget->value;

        // Add reply to
        if (FE_USER_LOGGED_IN) {
            $this->import('FrontendUser', 'User');
            $replyTo = $this->User->email;

            // Add name
            if (strlen($this->User->firstname)) {
                $replyTo = $this->User->firstname . ' ' . $this->User->lastname . ' <' . $replyTo . '>';
            }

            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['subjectFeUser'], $this->User->username, Environment::get('host'));
            $objEmail->text .= "\n\n---\n\n" . sprintf($GLOBALS['TL_LANG']['MSC']['sendersProfile'], Environment::get('base') . preg_replace('/show=[0-9]+/', 'show=' . $this->User->id, Environment::get('request')));

            $objEmail->replyTo($replyTo);
        } else {
            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['subjectUnknown'], Environment::get('host'));
        }

        // Send e-mail
        $objEmail->sendTo($objMember->email);
        $_SESSION['TL_EMAIL_SENT'] = true;

        Controller::reload();
    }


    /**
     * Format a value
     * @param string
     * @param mixed
     * @param boolean
     * @return mixed
     */
    protected function formatValue($k, $value, $blnListSingle = false)
    {
        $value = StringUtil::deserialize($value);

        // HOOK: Custom member list field output
        if (isset($GLOBALS['TL_HOOKS']['memberListFormatValue']) && is_array($GLOBALS['TL_HOOKS']['memberListFormatValue'])) {
            foreach ($GLOBALS['TL_HOOKS']['memberListFormatValue'] as $callback) {
                $ImportedClass = System::importStatic($callback[0]);
                $res = $ImportedClass->{$callback[1]}($k, $value, $blnListSingle);
                if ($res !== false) return $res;
            }
        }

        // Avatar
        if (strcmp($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['inputType'], 'avatar') == 0) {
            $objFile = FilesModel::findByUuid($value);
            if ($objFile === null && $GLOBALS['TL_CONFIG']['avatar_fallback_image']) {
                $objFile = FilesModel::findByUuid($GLOBALS['TL_CONFIG']['avatar_fallback_image']);
            }

            if ($objFile !== null) {
                $value = '<img src="' . TL_FILES_URL . Image::get(
                    $objFile->path,
                    $arrImage[0],
                    $arrImage[1],
                    $arrImage[2]
                ) . '" width="' . $arrImage[0] . '" height="' . $arrImage[1] . '" alt="' . $strAlt . '" class="avatar">';
            } else {
                $value = "-";
            }
            return $value;
        }

        // Return if empty
        if ($value == '') {
            if (isset($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'][''])) {
                return $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'][''];
            }

            return '-';
        }

        // Array
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        // Date
        elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'date') {
            $value = Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $value);
        }

        // Time
        elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'time') {
            $value = Date::parse($GLOBALS['TL_CONFIG']['timeFormat'], $value);
        }

        // Date and time
        elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'datim') {
            $value = Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $value);
        }

        // URLs
        elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'url' && preg_match('@^(https?://|ftp://)@i', $value)) {
            $value = '<a href="' . $value . '"' . LINK_NEW_WINDOW . '>' . $value . '</a>';
        }

        // E-mail addresses
        elseif ($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['eval']['rgxp'] == 'email') {
            $value = StringUtil::encodeEmail($value);
            $value = '<a href="&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $value . '">' . $value . '</a>';
        }

        // Reference
        elseif (is_array($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'])) {
            $value = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['reference'][$value];
        }

        // Associative array
        elseif (ArrayUtil::isAssoc($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options'])) {
            if ($blnListSingle) {
                $value = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options'][$value];
            } else {
                $value = '<span class="value">[' . $value . ']</span> ' . $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['options'][$value];
            }
        }

        return strlen($value) ? $value :  '-';
    }
}
