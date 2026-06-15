<?php
defined("B_PROLOG_INCLUDED") || die;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class fivecorners_crmblockcollapse extends CModule
{
    public $MODULE_ID          = "fivecorners.crmblockcollapse";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = array();
        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");

        $this->MODULE_VERSION      = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME         = Loc::getMessage("FCO_CBC_MODULE_NAME");
        $this->MODULE_DESCRIPTION  = Loc::getMessage("FCO_CBC_MODULE_DESC");
        $this->PARTNER_NAME        = Loc::getMessage("FCO_CBC_PARTNER_NAME") ?: "5 УГЛОВ";
        $this->PARTNER_URI         = Loc::getMessage("FCO_CBC_PARTNER_URI") ?: "https://www.5corners.ru";
    }

    public function DoInstall()
    {
        global $APPLICATION;
        try {
            $this->InstallDB();
            $this->InstallFiles();
            $this->InstallEvents();
        } catch (\Throwable $e) {
            $this->UnInstallEvents();
            $this->UnInstallFiles();
            $this->UnInstallDB();
            throw $e;
        }
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("FCO_CBC_INSTALL_TITLE"),
            __DIR__ . "/step.php"
        );
        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        $step = (int)($_REQUEST["step"] ?? 1);

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage("FCO_CBC_UNINSTALL_TITLE"),
                __DIR__ . "/unstep1.php"
            );
            return true;
        }

        $saveData = (($_REQUEST["save_data"] ?? "") === "Y");

        $this->UnInstallEvents();
        $this->UnInstallFiles();

        if (!$saveData) {
            // Канон: удалить настройки модуля, пока он ещё зарегистрирован,
            // и только потом UnInstallDB (там UnRegisterModule идёт последним).
            \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
            $this->UnInstallDB(); // deletes user data + calls UnRegisterModule
        } else {
            if (ModuleManager::isModuleInstalled($this->MODULE_ID)) {
                UnRegisterModule($this->MODULE_ID);
            }
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("FCO_CBC_UNINSTALL_TITLE"),
            __DIR__ . "/unstep.php"
        );
        return true;
    }

    public function InstallDB()
    {
        RegisterModule($this->MODULE_ID);
        return true;
    }

    public function UnInstallDB()
    {
        // D7 Connection API (не legacy $DB->Query) — диалект-нейтрально, PG-safe.
        // Чистим все per-user опции модуля (state_DEAL/state_LEAD/state_SMART_PROCESS_N).
        // convertToDbString() = '…forSql(escaped)…' — экранирование строкового ЗНАЧЕНИЯ.
        // (quote() здесь нельзя — он для ИДЕНТИФИКАТОРОВ, разбил бы MODULE_ID по точке.)
        $conn   = \Bitrix\Main\Application::getConnection();
        $helper = $conn->getSqlHelper();
        $conn->queryExecute(
            "DELETE FROM b_user_option WHERE CATEGORY = " . $helper->convertToDbString($this->MODULE_ID)
        );
        if (ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            UnRegisterModule($this->MODULE_ID);
        }
        return true;
    }

    public function InstallFiles()
    {
        // Что и куда копирует InstallFiles (канон — табличка для будущего разработчика):
        //
        //  Источник (install/)                  → Назначение (/local/)                                  Назначение файла
        //  ──────────────────────────────────────────────────────────────────────────────────────────────────────────
        //  admin/*                              → /local/admin/                                          admin-страница + lang
        //  js/crmblockcollapse/*                → /local/js/fivecorners.crmblockcollapse/                публичный collapse.js
        //  ajax/fivecorners_crmblockcollapse.php→ /local/ajax/                                           AJAX load/save
        //  admin_module_icon.png                → /local/images/fivecorners.crmblockcollapse/            бренд модуля в admin-меню
        //  images/section_icon.svg              → /local/images/fivecorners/logo.svg (shared, guard)     иконка раздела «5 УГЛОВ»*
        //
        //  * section-иконка раздела рисуется data-URI'ем из AdminMenu::SECTION_ICON_SVG_B64;
        //    файл копируется лишь для backward-compat других модулей семьи (см. AdminMenu).
        $docRoot = \Bitrix\Main\Application::getDocumentRoot();

        // Admin pages → /local/admin/
        CopyDirFiles(__DIR__ . "/admin", $docRoot . "/local/admin", true, true);

        // JS files → /local/js/fivecorners.crmblockcollapse/
        $jsDst = $docRoot . "/local/js/fivecorners.crmblockcollapse";
        if (!is_dir($jsDst)) {
            @mkdir($jsDst, 0755, true);
        }
        CopyDirFiles(__DIR__ . "/js/crmblockcollapse", $jsDst, true, true);

        // AJAX handler → /local/ajax/
        $ajaxDst = $docRoot . "/local/ajax";
        if (!is_dir($ajaxDst)) {
            @mkdir($ajaxDst, 0755, true);
        }
        $ajaxSrc = __DIR__ . "/ajax/fivecorners_crmblockcollapse.php";
        if (is_file($ajaxSrc)) {
            @copy($ajaxSrc, $ajaxDst . "/fivecorners_crmblockcollapse.php");
        }

        // Per-module icon
        $moduleImgDir = $docRoot . "/local/images/fivecorners.crmblockcollapse";
        if (!is_dir($moduleImgDir)) {
            @mkdir($moduleImgDir, 0755, true);
        }
        $adminIconSrc = __DIR__ . "/admin_module_icon.png";
        if (is_file($adminIconSrc)) {
            @copy($adminIconSrc, $moduleImgDir . "/admin_module_icon.png");
        }
        // Legacy cleanup: старое имя admin_icon.png (до канона Phase 7.2)
        @unlink($moduleImgDir . "/admin_icon.png");

        // Section icon (with file_exists guard — don't overwrite if already deployed)
        $sectionImgDir = $docRoot . "/local/images/fivecorners";
        if (!is_dir($sectionImgDir)) {
            @mkdir($sectionImgDir, 0755, true);
        }
        $sectionIconSrc = __DIR__ . "/images/section_icon.svg";
        if (is_file($sectionIconSrc) && !is_file($sectionImgDir . "/logo.svg")) {
            @copy($sectionIconSrc, $sectionImgDir . "/logo.svg");
        }

        return true;
    }

    public function UnInstallFiles()
    {
        $docRoot = \Bitrix\Main\Application::getDocumentRoot();

        // Remove admin page and its lang files
        @unlink($docRoot . "/local/admin/fc_crmblockcollapse_settings.php");
        @unlink($docRoot . "/local/admin/lang/ru/fc_crmblockcollapse_settings.php");
        @unlink($docRoot . "/local/admin/lang/en/fc_crmblockcollapse_settings.php");
        foreach (array('ru', 'en') as $langCode) {
            $langDir = $docRoot . "/local/admin/lang/" . $langCode;
            if (is_dir($langDir) && $this->isDirEmpty($langDir)) {
                @rmdir($langDir);
            }
        }
        $adminLangDir = $docRoot . "/local/admin/lang";
        if (is_dir($adminLangDir) && $this->isDirEmpty($adminLangDir)) {
            @rmdir($adminLangDir);
        }

        // Remove JS directory
        $this->removeDir($docRoot . "/local/js/fivecorners.crmblockcollapse");

        // Remove AJAX handler
        @unlink($docRoot . "/local/ajax/fivecorners_crmblockcollapse.php");
        $ajaxDir = $docRoot . "/local/ajax";
        if (is_dir($ajaxDir) && $this->isDirEmpty($ajaxDir)) {
            @rmdir($ajaxDir);
        }

        // Remove per-module images directory
        $this->removeDir($docRoot . "/local/images/fivecorners.crmblockcollapse");

        // Remove section icon with md5 guard (don't remove if another module changed it)
        $logoDeployed = $docRoot . "/local/images/fivecorners/logo.svg";
        $logoSrc      = __DIR__ . "/images/section_icon.svg";
        if (is_file($logoDeployed) && is_file($logoSrc)
            && md5_file($logoDeployed) === md5_file($logoSrc))
        {
            @unlink($logoDeployed);
        }
        $sectionImgDir = $docRoot . "/local/images/fivecorners";
        if (is_dir($sectionImgDir) && $this->isDirEmpty($sectionImgDir)) {
            @rmdir($sectionImgDir);
        }

        return true;
    }

    public function InstallEvents()
    {
        // Unregister first for idempotency (re-install safe)
        $this->UnInstallEvents();

        $em = EventManager::getInstance();

        // OnBuildGlobalMenu: legacy event, passes args by reference → Compatible (v1)
        $em->registerEventHandlerCompatible(
            "main", "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\AdminMenu",
            "onBuildGlobalMenu",
            10
        );

        // OnProlog for CRM page JS injection (public pages)
        $em->registerEventHandlerCompatible(
            "main", "OnProlog",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\Handler\\PageHandler",
            "onProlog",
            10
        );

        // OnProlog for admin icon CSS (admin pages — separate from PageHandler)
        $em->registerEventHandlerCompatible(
            "main", "OnProlog",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\AdminMenu",
            "onProlog",
            11
        );

        // Flush managed cache so handlers are visible immediately
        $em->clearLoadedHandlers();

        return true;
    }

    public function UnInstallEvents()
    {
        $em = EventManager::getInstance();

        $em->unRegisterEventHandler(
            "main", "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\AdminMenu",
            "onBuildGlobalMenu"
        );
        $em->unRegisterEventHandler(
            "main", "OnProlog",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\Handler\\PageHandler",
            "onProlog"
        );
        $em->unRegisterEventHandler(
            "main", "OnProlog",
            $this->MODULE_ID,
            "\\FiveCorners\\CrmBlockCollapse\\AdminMenu",
            "onProlog"
        );

        $em->clearLoadedHandlers();

        return true;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function isDirEmpty(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        $handle = opendir($dir);
        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }
}
