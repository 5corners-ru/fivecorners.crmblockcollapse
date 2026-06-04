<?php
defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . "/index.php");
?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
    <tr>
        <td align="center" width="100%" style="text-align:center;">
            <div class="adm-info-message-wrap adm-info-message-green">
                <div class="adm-info-message">
                    <div class="adm-info-message-title" style="text-align:center;">
                        <?= Loc::getMessage("FCO_CBC_INSTALL_DONE") ?>
                    </div>
                    <div class="adm-info-message-body" style="text-align:center;">
                        <br>
                        <a href="/local/admin/fc_crmblockcollapse_settings.php?lang=<?= LANGUAGE_ID ?>"
                           class="adm-btn">
                            <?= Loc::getMessage("FCO_CBC_INSTALL_GO") ?>
                        </a>
                        &nbsp;
                        <input type="submit" name="inst"
                               value="<?= Loc::getMessage("FCO_CBC_INSTALL_BACK") ?>">
                    </div>
                </div>
            </div>
        </td>
    </tr>
</table>
