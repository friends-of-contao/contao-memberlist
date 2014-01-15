contao_memberlist
=================

Contao Mitgliederlisten-Erweiterung

Diese Erweiterung pflegt die Mitgliederlisten-Erweiterung, die seit Contao 2.9 nicht mehr Teil des Contao Core ist.
memberlist stellt ein Modul Mitgliederliste zur Verfügung, mit dem sich Daten von Contao-Mitgliedern im Frontend ausgaben lassen.
Damit die Mitglieder die volle Kontrolle haben, welche Daten ausgegeben werden dürfen, gibt es in jedem Mitglieder-Datensatz eine zusätzliche Kategorie "Öffentliches Profil", in dem für jedes einzelne Feld festgelegt werden kann, ob der Inhalt in einer Mitgliederliste ausgegeben werden darf oder nicht.

Damit Contao-Erweiterungen, die eigene Feldtypen definieren die Möglichkeit haben ihren Inhalt in der Mitgliederliste auszugeben, gibt es seit der Version memberlist 1.3.1 einen Contao Hook, über den sich Erweiterungen in die Ausgabe "einklinken" können:

memberListFormatValue
---------------------
Der "memberListFormatValue"-Hook wird beim formatieren von Ausgabewerten für die Mitgliederliste ausgeführt. Er übergibt den Namen des Contao Mitgliedsfeldes, den Wert, der diesem Feld zugeordnet ist, ein Collection-Objekt mit den Mitgliedsdaten und einen booleschen Wert, der angibt, ob es sich bei der Ausgabe um einen einzelnen Mitgliedseintrag handelt, oder um einen Listeneintrag. Als Rückgabewert erwartet die Funktion einen String-Wert für die Frontendausgabe des Mitgliedsfeldes, oder wenn es sich nicht um das Feld handelt den booleschen Wert false, damit die reguläre Mitgliederlisten-Ausgabe durchgeführt wird.

```
// config.php
$GLOBALS['TL_HOOKS']['memberListFormatValue'][] = array('MyClass', 'formatValue');
 
// MyClass.php
public function formatValue($k, $value, $objMember, $blnListSingle=false)
{
  // Avatar
  if (strcmp($GLOBALS['TL_DCA']['tl_member']['fields'][$k]['inputType'], 'avatar') == 0)
  {
    $objFile = \FilesModel::findByUuid($value);
    if ($objFile === null && $GLOBALS['TL_CONFIG']['avatar_fallback_image']) {
      $objFile = \FilesModel::findByUuid($GLOBALS['TL_CONFIG']['avatar_fallback_image']);
    }

    $strAlt = $objMember->firstname . " " . $objMember->lastname;
    if ($objFile !== null) {
      $value = '<img src="' . TL_FILES_URL . \Image::get(
        $objFile->path,
        $arrImage[0],
        $arrImage[1],
        $arrImage[2]
        ) . '" width="' . $arrImage[0] . '" height="' . $arrImage[1] . '" alt="' . $strAlt . '" class="avatar">';
    }
    else
    {
      $value = "-";
    }
    return $value;
  }
  return false;
}
```
