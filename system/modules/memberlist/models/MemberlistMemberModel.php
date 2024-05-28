<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package Core
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;


/**
 * Memberlist specific model methods
 * 
 * @package   Models
 * @author    Helmut Schottmüller <https://github.com/hschottm>
 * @copyright Helmut Schottmüller 2012
 */
class MemberlistMemberModel extends \Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_member';

	/**
	 * Count active members for a memberlist
	 * 
	 * @param array $arrFields    Member list fields
	 * @param array $arrMemberGroups    Member list groups
	 * @param array $additionaloptions    Additional options
	 * @param string $search    Name of a special search option
	 * @param string $for    Value of a special search option
	 * 
	 * @return int|0 The number of datasets or 0 if there are no members
	 */
	public static function countActiveMembers($arrFields, $arrMemberGroups, $additionaloptions, $search = '', $for = '')
	{
		if (!is_array($arrFields) || !is_array($arrMemberGroups) || empty($arrFields))
		{
			return 0;
		}

		$t = static::$strTable;
		$time = time();
		$intGroupLimit = (count($arrMemberGroups) - 1);
		$arrValues = array();
		$strWhere = '';

		// Search query
		if (strlen($search) && strlen($for) && $for != '*')
		{
			$strWhere .= $t.'.'.$search . " REGEXP ? AND ";
			$arrValues[] = $for;
		}

		$strWhere .= "(";

		// Filter groups
		for ($i=0; $i<=$intGroupLimit; $i++)
		{
			if ($i < $intGroupLimit)
			{
				$strWhere .= "$t.groups LIKE ? OR ";
				$arrValues[] = '%"' . $arrMemberGroups[$i] . '"%';
			}
			else
			{
				$strWhere .= "$t.groups LIKE ?) AND ";
				$arrValues[] = '%"' . $arrMemberGroups[$i] . '"%';
			}
		}

		// List active members only
		if (in_array('username', $arrFields))
		{
			$strWhere .= "($t.publicFields!='' OR $t.allowEmail=? OR $t.allowEmail=?) AND $t.disable!=1 AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>=?)";
			array_push($arrValues, 'email_member', 'email_all', $time, $time);
		}
		else
		{
			$strWhere .= "$t.publicFields!='' AND $t.disable!=1 AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>=?)";
			array_push($arrValues, $time, $time);
		}
		$additionaloptions[] = $strWhere;

		return static::countBy($additionaloptions, $arrValues);
	}

	/**
	 * Find active members for a memberlist
	 * 
	 * @param array $arrFields    Member list fields
	 * @param array $arrMemberGroups    Member list groups
	 * @param string $order    List order
	 * @param array $additionaloptions    Additional options
	 * @param int $limit    List limit
	 * @param int $offset    List offset
	 * @param string $search    Name of a special search option
	 * @param string $for    Value of a special search option
	 * 
	 * @return \Collection|null The collection or null if there are no members
	 */
	public static function findActiveMembers($arrFields, $arrMemberGroups, $order, $additionaloptions, $limit = 0, $offset = 0, $search = '', $for = '')
	{
		if (!is_array($arrFields) || !is_array($arrMemberGroups) || empty($arrFields))
		{
			return null;
		}

		$t = static::$strTable;
		$time = time();
		$intGroupLimit = (count($arrMemberGroups) - 1);
		$arrValues = array();
		$strWhere = '';

		// Search query
		if ($search && $for && $for !== '*')
		{
			$strWhere .= $t.'.'.Database::quoteIdentifier($search)." REGEXP ? AND ";
			$arrValues[] = $for;
		}

		$strWhere .= "(";

		// Filter groups
		for ($i=0; $i<=$intGroupLimit; $i++)
		{
			if ($i < $intGroupLimit)
			{
				$strWhere .= "$t.groups LIKE ? OR ";
				$arrValues[] = '%"' . $arrMemberGroups[$i] . '"%';
			}
			else
			{
				$strWhere .= "$t.groups LIKE ?) AND ";
				$arrValues[] = '%"' . $arrMemberGroups[$i] . '"%';
			}
		}

		// List active members only
		if (in_array('username', $arrFields))
		{
			$strWhere .= "($t.publicFields!='' OR $t.allowEmail=? OR $t.allowEmail=?) AND $t.disable!=1 AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>=?)";
			array_push($arrValues, 'email_member', 'email_all', $time, $time);
		}
		else
		{
			$strWhere .= "$t.publicFields!='' AND $t.disable!=1 AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>=?)";
			array_push($arrValues, $time, $time);
		}

		$additionaloptions[] = $strWhere;

		return static::findBy($additionaloptions, $arrValues, array('order'=>$order, 'limit' => $limit, 'offset' => $offset));
	}

	/**
	 * Find an active member by his/her e-mail-address and username
	 * 
	 * @param int $intId    The member id
	 * 
	 * @return \Model|null The model or null if there is no member
	 */
	public static function findActiveById($intId)
	{
		$time = time();
		$t = static::$strTable;

		$arrColumns = array("$t.id=? AND $t.login=1 AND ($t.start='' OR $t.start<$time) AND ($t.stop='' OR $t.stop>$time) AND $t.disable=''");

		return static::findOneBy($arrColumns, array($intId));
	}
}
