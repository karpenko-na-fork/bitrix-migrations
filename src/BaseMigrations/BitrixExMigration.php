<?php

namespace InformUnity\Migrations;

use Arrilot\BitrixMigrations\BaseMigrations\BitrixMigration;
use Arrilot\BitrixMigrations\Exceptions\MigrationException;
use Bitrix\Main\Loader;
use Bitrix\Iblock;
use Arrilot\BitrixMigrations\Constructors\HighloadBlock;
use Bitrix\Main\SystemException;

class IBlockMigration extends BitrixMigration
{
    protected $hlBlocksIds = [];

    /**
     * Добавляем поля
     *
     * @param $hlBlockId
     * @param $hlFields
     * @throws MigrationException
     */
    protected function addUserFields($hlBlockId, $hlFields)
    {
        $ufe = new \CUserFieldEnum();

        $entityId = is_numeric($hlBlockId) ? 'HLBLOCK_' . $hlBlockId : $hlBlockId;

        $count = \CUserTypeEntity::GetList(['ID' => 'ASC'], ['ENTITY_ID' => $entityId])->AffectedRowsCount();
        $sort = $count * 10 + 10;

        foreach ($hlFields as $fieldName => $fieldValue) {
            $aUserField = array(
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_' . strtoupper($fieldName),
                /*
                 * address - Адрес
                 * resourcebooking - Бронирование ресурсов
                 * disk_version - Версия файла (Диск)
                 * video - Видео
                 * boolean - Да/Нет
                 * date - Дата
                 * datetime - Дата со временем
                 * money - Деньги
                 * vote - Опрос
                 * mail_message - Письмо (email)
                 * iblock_section - Привязка к разделам инф. блоков
                 * employee - Привязка к сотруднику
                 * crm_status - Привязка к справочникам CRM
                 * crm - Привязка к элементам CRM
                 * hlblock - Привязка к элементам highload-блоков
                 * iblock_element - Привязка к элементам инф. блоков
                 * url_preview - Содержимое ссылки
                 * enumeration - Список
                 * url - Ссылка
                 * string - Строка
                 * file - Файл
                 * disk_file - Файл (Диск)
                 * integer - Целое число
                 * double - Число
                 * string_formatted
                 */
                'USER_TYPE_ID' => $fieldValue[1],
                'SORT' => !empty($fieldValue[2]['SORT']) ? $fieldValue[2]['SORT'] : $sort,
                'MULTIPLE' => !empty($fieldValue[2]['MULTIPLE']) ? $fieldValue[2]['MULTIPLE'] : 'N',
                'MANDATORY' => $fieldValue[0],
                /*
                * Показывать в фильтре списка. Возможные значения:
                * не показывать = N, точное совпадение = I,
                * поиск по маске = E, поиск по подстроке = S
                */
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'N',
                'SETTINGS' => array(
                    // 'LEAD' => 'Y' - Лид
                    // 'CONTACT' => 'Y' - Контакт
                    // 'COMPANY' => 'Y' - Компания
                    // 'DEAL' => 'Y' - Сделка
                    // 'VISIBLE' => 'Y' - Показывать в карточке
                    // 'SHOW_ADD_FORM => 'Y' - Показывать в форме добавления
                    // 'SHOW_EDIT_FORM => 'Y' - Показывать в форме редактирования
                    // 'ADD_READ_ONLY_FIELD => 'Y' - Только для чтения (форма добавления)
                    // 'EDIT_READ_ONLY_FIELD => 'Y' - Только для чтения (форма редактирования)
                    // 'SHOW_FIELD_PREVIEW => 'Y' - Показать поле при формировании ссылки на элемент списка

                    // 'PATTERN'=> '#VALUE#' - Шаблон вывода
                    // 'REGEXP'=> '' - Регулярное выражение

                    // 'DEFAULT_VALUE' => '' - значение по умолчанию
                ),
            );

            if (isset($fieldValue[2]) && is_array($fieldValue[2])) {
                if (isset($fieldValue[2]['EDIT_FORM_LABEL']['ru']) && !isset($fieldValue[2]['EDIT_FORM_LABEL']['en'])) {
                    $fieldValue[2]['EDIT_FORM_LABEL']['en'] = $fieldValue[2]['EDIT_FORM_LABEL']['ru'];
                }
                if (isset($fieldValue[2]['EDIT_FORM_LABEL']) && !isset($fieldValue[2]['LIST_COLUMN_LABEL'])) {
                    $fieldValue[2]['LIST_COLUMN_LABEL'] = $fieldValue[2]['EDIT_FORM_LABEL'];
                }
                if (isset($fieldValue[2]['EDIT_FORM_LABEL']) && !isset($fieldValue[2]['LIST_FILTER_LABEL'])) {
                    $fieldValue[2]['LIST_FILTER_LABEL'] = $fieldValue[2]['EDIT_FORM_LABEL'];
                }
                // Привязка к элементам HL-блока
                if (isset($fieldValue[2]['SETTINGS']['HLBLOCK_CODE'])) {
                    // Узнаем ID HL-блока по коду
                    if (isset($this->hlBlocksIds[$fieldValue[2]['SETTINGS']['HLBLOCK_CODE']])) {
                        $fieldValue[2]['SETTINGS']['HLBLOCK_ID'] = $this->hlBlocksIds[$fieldValue[2]['SETTINGS']['HLBLOCK_CODE']];
                    } else {
                        $hlBlock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
                            'filter' => ['=NAME' => $fieldValue[2]['SETTINGS']['HLBLOCK_CODE']]
                        ])->fetch();
                        if ($hlBlock) {
                            $this->hlBlocksIds[$fieldValue[2]['SETTINGS']['HLBLOCK_CODE']] = $hlBlock['ID'];
                            $fieldValue[2]['SETTINGS']['HLBLOCK_ID'] = $hlBlock['ID'];
                        }
                    }
                    unset($fieldValue[2]['SETTINGS']['HLBLOCK_CODE']);
                    // Узнаем ID пользовательского поля по коду
                    if (isset($fieldValue[2]['SETTINGS']['HLFIELD_ID'])) {
                        // Если код не ID, то ищем это поле и узнаем его идентификатор
                        if (!empty($fieldValue[2]['SETTINGS']['HLFIELD_ID']) && $fieldValue[2]['SETTINGS']['HLFIELD_ID'] != 'ID') {
                            $arFieldRes = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'HLBLOCK_' . $fieldValue[2]['SETTINGS']['HLBLOCK_ID'], 'FIELD_NAME' => $fieldValue[2]['SETTINGS']['HLFIELD_ID']]);
                            if ($arField = $arFieldRes->Fetch()) {
                                $fieldValue[2]['SETTINGS']['HLFIELD_ID'] = $arField['ID'];
                            }
                        }
                    }
                }

                // Привязка к элементам инфоблока-блока
                if (isset($fieldValue[2]['SETTINGS']['IBLOCK_TYPE_ID']) && isset($fieldValue[2]['SETTINGS']['IBLOCK_ID']) && !is_numeric($fieldValue[2]['SETTINGS']['IBLOCK_ID'])) {
                    $fieldValue[2]['SETTINGS']['IBLOCK_ID'] = $this->findIblock($fieldValue[2]['SETTINGS']['IBLOCK_TYPE_ID'], $fieldValue[2]['SETTINGS']['IBLOCK_ID']);
                }

                $aUserField = array_merge($aUserField, $fieldValue[2]);
            }

            $ufId = $this->addUF($aUserField);

            if ($aUserField['USER_TYPE_ID'] == 'enumeration' && isset($fieldValue[3])) {
                $uf_sort = 10;
                $arAddEnum = [];
                foreach ($fieldValue[3] as $i => $item) {
                    if (!isset($item['XML_ID'])) $item['XML_ID'] = 'X' . ($i + 1);
                    if (!isset($item['SORT'])) $item['SORT'] = $uf_sort * ($i + 1);
                    $arAddEnum['n' . $i] = $item;
                }
                $ufe->SetEnumValues($ufId, $arAddEnum);
            }

            $sort += 10;
        }
    }

    protected function updateUserFields($hlBlockId, $hlFields)
    {
        $ute = new \CUserTypeEntity();
        $entityId = is_numeric($hlBlockId) ? 'HLBLOCK_' . $hlBlockId : $hlBlockId;
        foreach ($hlFields as $fieldName => $fieldValue) {
            $ufId = $this->getUFIdByCode($entityId, 'UF_' . strtoupper($fieldName));

            if ($ufId && isset($fieldValue) && is_array($fieldValue)) {
                if (isset($fieldValue['EDIT_FORM_LABEL']['ru']) && !isset($fieldValue['EDIT_FORM_LABEL']['en'])) {
                    $fieldValue['EDIT_FORM_LABEL']['en'] = $fieldValue['EDIT_FORM_LABEL']['ru'];
                }
                if (isset($fieldValue['EDIT_FORM_LABEL']) && !isset($fieldValue['LIST_COLUMN_LABEL'])) {
                    $fieldValue['LIST_COLUMN_LABEL'] = $fieldValue['EDIT_FORM_LABEL'];
                }
                if (isset($fieldValue['EDIT_FORM_LABEL']) && !isset($fieldValue['LIST_FILTER_LABEL'])) {
                    $fieldValue['LIST_FILTER_LABEL'] = $fieldValue['EDIT_FORM_LABEL'];
                }
                $ute->Update($ufId, $fieldValue);
            }
        }
    }

    protected function changeUserFieldEnum($hlBlockId, $hlFieldName, $hlFieldEnum)
    {
        global $APPLICATION;
        $ufe = new \CUserFieldEnum();
        $entityId = is_numeric($hlBlockId) ? 'HLBLOCK_' . $hlBlockId : $hlBlockId;
        $uf = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_' . strtoupper($hlFieldName)])->Fetch();
        if (!empty($uf['ID']) && isset($hlFieldEnum)) {
            //$ufe->DeleteFieldEnum($uf['ID']);
            $enumQuery = $ufe->getList([], ['USER_FIELD_ID' => $uf['ID']]);
            $arAddEnum = [];
            while ($enum = $enumQuery->getNext()) {
                $enum['DEL'] = 'Y';
                $arAddEnum[$enum['ID']] = $enum;
                $i++;
            }
            $uf_sort = 10;
            $j = 0;
            foreach ($hlFieldEnum as $item) {
                if (!isset($item['XML_ID'])) $item['XML_ID'] = 'X' . ($j + 1);
                if (!isset($item['SORT'])) $item['SORT'] = $uf_sort * ($j + 1);
                $find = false;
                foreach ($arAddEnum as $id => $arAEnum) {
                    if (strncmp($id, "n", 1) !== 0 && mb_strtoupper($arAEnum['XML_ID'], 'UTF-8') == mb_strtoupper($item['XML_ID'], 'UTF-8') || mb_strtoupper($arAEnum['VALUE'], 'UTF-8') == mb_strtoupper($item['VALUE'], 'UTF-8')) {
                        //$item['ID']=$arAEnum['ID'];
                        $arAddEnum[$id] = $item;
                        $find = true;
                        break;
                    }
                }
                if ($find == false) {
                    $arAddEnum['n' . $j] = $item;
                    $j++;
                }
            }
            $ufe->SetEnumValues($uf['ID'], $arAddEnum);
            if ($ex = $APPLICATION->GetException()) {
                $strError = $ex->GetString();
                echo $strError;
            }
        }
    }

    protected function deleteUfFields($hlBlockId, $UfFields)
    {
        $obUserField = new \CUserTypeEntity;
        $createdFields = array_keys($UfFields);
        $entityId = is_numeric($hlBlockId) ? 'HLBLOCK_' . $hlBlockId : $hlBlockId;
        foreach ($createdFields as $createdField) {
            $id = $obUserField->GetList([], ["ENTITY_ID" => $entityId, "FIELD_NAME" => 'UF_' . strtoupper($createdField)])->fetch()['ID'];
            if ($id) {
                $obUserField->Delete($id);
            }
        }
    }

    protected function addHL()
    {
        Loader::includeModule("highloadblock");

        $hlb = new Highloadblock;
        $hlb->constructDefault($this->hlName, $this->tblName);
        foreach ($this->langTitles as $lang => $title) {
            $hlb->setLang($lang, $title);
        }

        $hlBlockId = $hlb->add();

        if (!$hlBlockId) {
            throw new MigrationException('Ошибка при добавлении инфоблока ' . $hlb->LAST_ERROR);
        }

        return $hlBlockId;
    }

    protected function deleteHL()
    {
        Loader::includeModule("highloadblock");
        Highloadblock::delete($this->tblName);
    }

    protected function addIblock($typeCode, $code, $name)
    {
        $arIblock = \CIBlock::GetList(
            array(),
            array(
                'TYPE' => $typeCode,
                'SITE_ID' => SITE_ID,
                'CODE' => $code,
                'CHECK_PERMISSIONS' => 'N'
            )
        )->fetch();
        if (empty($arIblock)) {
            $ib = new \CIBlock;
            $arFields = array(
                "ACTIVE" => 'Y',
                "NAME" => !empty($name) ? $name : $code,
                "CODE" => $code,
                "API_CODE" => str_replace('_', '', $code),
                "IBLOCK_TYPE_ID" => $typeCode,
                "SITE_ID" => array("s1"),
                "BIZPROC" => 'Y',
                "WORKFLOW" => 'N'
            );
            $ID = $ib->Add($arFields);
            return $ID;
        } else {
            return $arIblock['ID'];
        }
    }

    protected function findIblock($typeCode, $code)
    {
        $arIblock = \CIBlock::GetList(
            array(),
            array(
                'TYPE' => $typeCode,
                'SITE_ID' => SITE_ID,
                'CODE' => $code,
                'CHECK_PERMISSIONS' => 'N'
            )
        )->fetch();
        if (!empty($arIblock)) {
            return $arIblock['ID'];
        } else {
            return false;
        }
    }

    protected function addPropertyFields($IBlockId, $IBlockFields)
    {
        $ufe = new \CIBlockPropertyEnum();

        $count = \CIBlockProperty::GetList(['sort' => 'asc', 'name' => 'asc'], ['IBLOCK_ID' => $IBlockId])->AffectedRowsCount();
        $sort = $count * 10 + 10;

        foreach ($IBlockFields as $fieldName => $fieldValue) {
            $typeAr = explode(':', $fieldValue[1]);
            var_dump($typeAr);
            $aUserField = array(
                'IBLOCK_ID' => $IBlockId,
                'CODE' => 'UF_' . strtoupper($fieldName),
                'MULTIPLE' => !empty($fieldValue[2]['MULTIPLE']) ? $fieldValue[2]['MULTIPLE'] : 'N',

                // Базовые типы
                // S - строка,
                // N - число,
                // F - файл,
                // L - список,
                // E - привязка к элементам,
                // G - привязка к группам.
                'PROPERTY_TYPE' => $typeAr[0],
                // Пользовательские типы
                // S:HTML - HTML/текст
                // S:video - Видео
                // S:Date - Дата
                // S:DateTime - Дата/Время
                // S:Money - Деньги
                // S:bpMaterial - Материалы
                // S:map_yandex - Привязка к Яндекс.Карте
                // S:map_google - Привязка к карте Google Maps
                // S:UserID - Привязка к пользователю
                // G:SectionAuto - Привязка к разделам с автозаполнением
                // S:employee - Привязка к сотруднику
                // S:TopicID - Привязка к теме форума
                // E:SKU - Привязка к товарам (SKU)
                // S:FileMan - Привязка к файлу (на сервере)
                // S:ECrm - Привязка к элементам CRM
                // E:EList - Привязка к элементам в виде списка
                // S:ElementXmlID - Привязка к элементам по XML_ID
                // E:EAutocomplete - Привязка к элементам с автозаполнением
                // S:directory - Справочник
                // N:Sequence - Счетчик
                // S:DiskFile - Файл (Диск)
                'USER_TYPE' => $typeAr[1] ?? '',

                'SORT' => $sort,
                'IS_REQUIRED' => $fieldValue[0],
                'NAME' => $fieldValue[2]['EDIT_FORM_LABEL'],
                'FILTRABLE' => 'N',
                'SEARCHABLE' => 'N',
                'FEATURES' => [
                    [
                        'MODULE_ID' => 'iblock',
                        'FEATURE_ID' => Iblock\Model\PropertyFeature::FEATURE_ID_LIST_PAGE_SHOW,
                        'IS_ENABLED' => 'Y'
                    ],
                    [
                        'MODULE_ID' => 'iblock',
                        'FEATURE_ID' => Iblock\Model\PropertyFeature::FEATURE_ID_DETAIL_PAGE_SHOW,
                        'IS_ENABLED' => 'Y'
                    ]
                ],
                'ACTIVE' => 'Y',
                //'LIST_TYPE' => 'L'//$fieldValue['LIST_TYPE'] // L, C
            );

            if ($typeAr[0] == 'E') {
                if (!empty($fieldValue[4])) {
                    $ib = explode('.', $fieldValue[4]);
                    if (!empty($ib[0]) && !empty($ib[1])) {
                        $iblock_id = $this->findIblock($ib[0], $ib[1]);
                        if (intval($iblock_id) > 0) {
                            $aUserField['LINK_IBLOCK_ID'] = $iblock_id;
                        }
                    }
                }
            }

            $addInList = false;
            if (isset($fieldValue[2]['IU_ADD_IN_LIST'])) {
                $addInList = $fieldValue[2]['IU_ADD_IN_LIST'];
                unset($fieldValue[2]['IU_ADD_IN_LIST']);
            }

            if (isset($fieldValue[2]) && is_array($fieldValue[2])) {
                $aUserField = array_merge($aUserField, $fieldValue[2]);
            }

            // вот это устарело, не надо так делать, лучше в $fieldValue[2] добавлять поле 'USER_TYPE_SETTINGS'
            if ($typeAr[1] == 'ECrm' && isset($fieldValue[5])) {
                $aUserField['USER_TYPE_SETTINGS'] = $fieldValue[5];
            }

            $ufId = $this->addIblockElementProperty($aUserField);

            if ($ufId > 0) {
                if ($addInList == true) {
                    $listField = new \CListPropertyField($IBlockId, "PROPERTY_" . $ufId, $aUserField["NAME"], $aUserField["SORT"]);
                    $listField->SetSettings(["SHOW_ADD_FORM" => 'Y', "SHOW_EDIT_FORM" => 'Y']);
                }

                if ($fieldValue[1] == 'L') {
                    $uf_sort = 10;

                    foreach ($fieldValue[3] as $i => $item) {
                        if (!isset($item['XML_ID'])) $item['XML_ID'] = 'X' . ($i + 1);
                        if (!isset($item['SORT'])) $item['SORT'] = $uf_sort * ($i + 1);
                        if (!isset($item['DEF'])) $item['DEF'] = 'N';
                        $ufe->Add([
                            'PROPERTY_ID' => $ufId,
                            'VALUE' => $item['VALUE'],
                            'XML_ID' => $item['XML_ID'],
                            'DEF' => $item['DEF'],
                        ]);
                    }
                }
            }
            $sort += 10;
        }
    }

    protected function deletePropertyFields($IBlockId, $IBlockFields)
    {
        foreach ($IBlockFields as $fieldName => $fieldValue) {
            $ufId = $this->getIblockPropIdByCode('UF_' . strtoupper($fieldName), $IBlockId);
            $this->deleteIblockElementPropertyByCode($IBlockId, 'UF_' . strtoupper($fieldName));

            $addInList = $fieldValue[2]['IU_ADD_IN_LIST'] ?? false;
            if ($ufId && $ufId > 0 && $addInList == true) {
                $listField = new \CListPropertyField($IBlockId, "PROPERTY_" . $ufId, '', '');
                if ($listField) {
                    $listField->Delete();
                }
            }
        }
    }

    protected function actualizeListUrl($iblockId) {
        // вот эта штука прописывает УРЛ для редактирования элемента из сущности (сделки, например)
        global $DB;
        $url = '/services/lists/'.$iblockId.'/element/#section_id#/#element_id#/';
        $DB->Query("INSERT INTO b_lists_url (IBLOCK_ID, URL) values (".$iblockId.", '".$DB->ForSQL($url)."')");
    }
}