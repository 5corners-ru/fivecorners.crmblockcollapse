<?php
defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . "/index.php");
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="id"        value="fivecorners.crmblockcollapse">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step"      value="2">

    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td align="center" width="100%" style="text-align:center;">
                <div class="adm-info-message-wrap adm-info-message-red">
                    <div class="adm-info-message">
                        <div class="adm-info-message-title" style="text-align:center;">
                            <?= Loc::getMessage("FCO_CBC_UNINSTALL_CONFIRM") ?>
                        </div>
                        <div class="adm-info-message-body" style="text-align:center;">
                            <br>
                            <label>
                                <input type="checkbox" name="save_data" value="Y" checked>
                                <?= Loc::getMessage("FCO_CBC_UNINSTALL_SAVE_DATA") ?>
                            </label>
                            <br><br>
                            <input type="submit" class="adm-btn-save"
                                   value="<?= Loc::getMessage("FCO_CBC_UNINSTALL_DO") ?>">
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</form>
