<?php
namespace FiveCorners\CrmBlockCollapse;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class PageHeader
{
    public static function addStyles(\CMain $application): void
    {
        $application->AddHeadString('<link rel="preconnect" href="https://fonts.googleapis.com">');
        $application->AddHeadString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>');
        $application->AddHeadString('<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">');

        $application->AddHeadString(<<<'STYLE'
<style>
:root {
  --Marina-White: #ffffff;
  --Marina-Text: #333333;
  --Marina-Oslo-Gray: #868D95;
  --mib-height: 60px;
  --mib-background: rgba(255, 255, 255, 0.72);
  --mib-radius: 10px;
  --mib-item-active-color: #1058d0;
  --mib-item-font-family: "Open Sans";
  --mib-item-font-size: 15px;
  --mib-item-font-weight: 600;
  --mib-item-color: var(--Marina-Text);
}
.fco-demo-wrap {
  margin: -10px -10px 0;
  background: transparent;
  min-height: calc(100vh - 100px);
  display: flex;
  flex-direction: column;
}
.fco-custom-header-sticky {
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 16px 24px 0;
  flex-shrink: 0;
}
.fco-custom-header {
  height: var(--mib-height);
  background: var(--mib-background);
  border-radius: var(--mib-radius);
  border: 1px solid rgba(255, 255, 255, 0.55);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04);
  backdrop-filter: blur(18px) saturate(180%);
  -webkit-backdrop-filter: blur(18px) saturate(180%);
}
.fco-custom-header__wrapper {
  display: flex;
  height: 100%;
  padding: 0 20px;
  gap: 30px;
  align-items: center;
}
.fco-custom-header__list {
  display: flex;
  margin: 0;
  padding: 0;
  list-style: none;
  gap: 24px;
}
.fco-custom-header__list--user {
  margin-left: auto;
  padding-left: 24px;
  border-left: 1px solid rgba(0, 0, 0, 0.08);
  gap: 16px;
}
.fco-custom-header__item {
  position: relative;
  display: inline-flex;
  height: var(--mib-height);
  box-sizing: border-box;
}
.fco-custom-header__link {
  display: flex;
  align-items: center;
  color: var(--mib-item-color);
  font-family: var(--mib-item-font-family), sans-serif;
  font-size: var(--mib-item-font-size);
  font-weight: var(--mib-item-font-weight);
  text-decoration: none;
  gap: 6px;
  transition: color 180ms ease;
  cursor: pointer;
  white-space: nowrap;
}
.fco-custom-header__link:hover { color: #1058d0; }
.fco-custom-header__link span { display: block; }
.fco-custom-header__item--divider {
  width: 1px;
  height: 28px;
  align-self: center;
  background: rgba(0, 0, 0, 0.1);
  display: inline-flex;
  margin: 0 4px;
  pointer-events: none;
}
.fco-custom-header__item--version { align-items: center; }
.fco-version-badge {
  font-family: var(--mib-item-font-family), sans-serif;
  font-size: 12px;
  font-weight: 400;
  color: var(--Marina-Oslo-Gray);
  white-space: nowrap;
  letter-spacing: 0.02em;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.3;
}
.fco-version-badge__label {
  font-size: 10px;
  text-transform: lowercase;
  color: var(--Marina-Oslo-Gray);
}
.fco-version-badge__value {
  font-size: 13px;
  font-weight: 600;
  color: #333;
}
.fco-custom-icon {
  position: relative;
  display: flex;
  flex-shrink: 0;
}
.fco-custom-icon__item {
  width: 26px;
  height: 26px;
  background-color: currentColor;
  -webkit-mask-size: contain;
  mask-size: contain;
  -webkit-mask-position: center;
  mask-position: center;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
}
.fco-custom-icon--headphones .fco-custom-icon__item {
  -webkit-mask-image: url("data:image/svg+xml;charset=UTF-8,%3csvg width='29' height='28' viewBox='0 0 29 28' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M26.2845 19.2523C26.284 20.8478 25.7386 22.3953 24.7387 23.6386C23.7388 24.8818 22.3443 25.7463 20.786 26.089L20.0417 23.856C20.7233 23.7437 21.3715 23.4817 21.9396 23.0887C22.5077 22.6957 22.9816 22.1815 23.327 21.5833H20.4512C19.8324 21.5833 19.2389 21.3375 18.8013 20.8999C18.3637 20.4623 18.1179 19.8688 18.1179 19.25V14.5833C18.1179 13.9645 18.3637 13.371 18.8013 12.9334C19.2389 12.4958 19.8324 12.25 20.4512 12.25H23.8789C23.5942 9.99471 22.4963 7.92082 20.7912 6.41745C19.0861 4.91409 16.891 4.08459 14.6179 4.08459C12.3447 4.08459 10.1496 4.91409 8.44453 6.41745C6.73945 7.92082 5.64156 9.99471 5.35686 12.25H8.78452C9.40336 12.25 9.99686 12.4958 10.4344 12.9334C10.872 13.371 11.1179 13.9645 11.1179 14.5833V19.25C11.1179 19.8688 10.872 20.4623 10.4344 20.8999C9.99686 21.3375 9.40336 21.5833 8.78452 21.5833H5.28452C4.66569 21.5833 4.07219 21.3375 3.63461 20.8999C3.19702 20.4623 2.95119 19.8688 2.95119 19.25V13.4167C2.95119 6.97317 8.17436 1.75 14.6179 1.75C21.0614 1.75 26.2845 6.97317 26.2845 13.4167V19.2523Z' fill='%23333333'/%3e%3c/svg%3e");
  mask-image: url("data:image/svg+xml;charset=UTF-8,%3csvg width='29' height='28' viewBox='0 0 29 28' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M26.2845 19.2523C26.284 20.8478 25.7386 22.3953 24.7387 23.6386C23.7388 24.8818 22.3443 25.7463 20.786 26.089L20.0417 23.856C20.7233 23.7437 21.3715 23.4817 21.9396 23.0887C22.5077 22.6957 22.9816 22.1815 23.327 21.5833H20.4512C19.8324 21.5833 19.2389 21.3375 18.8013 20.8999C18.3637 20.4623 18.1179 19.8688 18.1179 19.25V14.5833C18.1179 13.9645 18.3637 13.371 18.8013 12.9334C19.2389 12.4958 19.8324 12.25 20.4512 12.25H23.8789C23.5942 9.99471 22.4963 7.92082 20.7912 6.41745C19.0861 4.91409 16.891 4.08459 14.6179 4.08459C12.3447 4.08459 10.1496 4.91409 8.44453 6.41745C6.73945 7.92082 5.64156 9.99471 5.35686 12.25H8.78452C9.40336 12.25 9.99686 12.4958 10.4344 12.9334C10.872 13.371 11.1179 13.9645 11.1179 14.5833V19.25C11.1179 19.8688 10.872 20.4623 10.4344 20.8999C9.99686 21.3375 9.40336 21.5833 8.78452 21.5833H5.28452C4.66569 21.5833 4.07219 21.3375 3.63461 20.8999C3.19702 20.4623 2.95119 19.8688 2.95119 19.25V13.4167C2.95119 6.97317 8.17436 1.75 14.6179 1.75C21.0614 1.75 26.2845 6.97317 26.2845 13.4167V19.2523Z' fill='%23333333'/%3e%3c/svg%3e");
}
.fco-page-body { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.fco-demo-content { padding: 24px; }
</style>
STYLE);
    }

    public static function renderOpen(string $moduleVersion, string $moduleId): void
    {
        $safeVersion  = htmlspecialchars($moduleVersion, ENT_QUOTES, 'UTF-8');
        $brandUrl     = 'https://www.5corners.ru/?utm_source=self&utm_medium=modules&utm_campaign=on-premis&utm_term=' . rawurlencode($moduleId);
        $brandUrlSafe = htmlspecialchars($brandUrl, ENT_QUOTES, 'UTF-8');
        $versionLabel = htmlspecialchars(Loc::getMessage('FCO_CBC_VERSION_LABEL') ?: 'версия', ENT_QUOTES, 'UTF-8');
        ?>
<div class="fco-demo-wrap">
    <div class="fco-custom-header-sticky">
        <section class="fco-custom-header">
            <div class="fco-custom-header__wrapper">

                <!-- Правая утилитарная панель (весь контент справа) -->
                <ul class="fco-custom-header__list fco-custom-header__list--user" style="margin-left:auto;border-left:none;">

                    <li class="fco-custom-header__item fco-custom-header__item--version">
                        <span class="fco-version-badge">
                            <span class="fco-version-badge__label"><?= $versionLabel ?></span>
                            <span class="fco-version-badge__value"><?= $safeVersion ?></span>
                        </span>
                    </li>

                    <li class="fco-custom-header__item--divider" aria-hidden="true"></li>

                    <li class="fco-custom-header__item">
                        <a href="#" class="fco-custom-header__link">
                            <div class="fco-custom-icon fco-custom-icon--headphones">
                                <i class="fco-custom-icon__item"></i>
                            </div>
                        </a>
                    </li>

                    <li class="fco-custom-header__item--divider" aria-hidden="true"></li>

                    <li class="fco-custom-header__item">
                        <a href="<?= $brandUrlSafe ?>" target="_blank" rel="noopener noreferrer" class="fco-custom-header__link">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M7.62276 14.3626L7.5998 14.3177L0.896851 10.158L3.2842 8.44915L5.25836 0.894287L7.64571 2.60312L15.6112 2.10846L14.693 4.85159L17.6313 12.1142H14.67L14.6471 12.1366L8.51802 17.1058L7.62276 14.3626Z" fill="#333333"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M7.62264 14.2951V14.34L12.2596 12.0916L13.1319 12.1141H14.647H14.6699L14.5781 11.4395L13.9354 7.09997L14.6929 4.87398H14.6699L14.6011 4.8515L9.5968 3.99708L7.6456 2.60303V2.62551L7.04876 3.70478L5.23529 7.07748L3.37591 8.40408L3.28409 8.47153L6.88807 12.0916L7.62264 14.2951Z" fill="#F5002C"/>
                            </svg>
                            <span>5 УГЛОВ</span>
                        </a>
                    </li>

                </ul>
            </div>
        </section>
    </div>

    <div class="fco-page-body">
        <div class="fco-demo-content">
        <?php
    }

    public static function renderClose(string $moduleId): void
    {
        $safeModuleId = htmlspecialchars($moduleId, ENT_QUOTES, 'UTF-8');
        ?>
        </div><!-- /fco-demo-content -->
    </div><!-- /fco-page-body -->
</div><!-- /fco-demo-wrap -->

<script>
BX.ready(function () {
    var headphonesLink = document.querySelector('.fco-custom-icon--headphones') ? document.querySelector('.fco-custom-icon--headphones').closest('a') : null;
    if (headphonesLink) {
        headphonesLink.addEventListener('click', function (e) {
            e.preventDefault();
            var url = new URL(window.location.href);
            url.searchParams.set('module_id', '<?= $safeModuleId ?>');
            history.replaceState({}, '', url.toString());
            var widgetBtn = document.querySelector('.b24-widget-button-openline_livechat')
                         || document.querySelector('.b24-widget-button-inner-container');
            if (widgetBtn) { widgetBtn.click(); }
            setTimeout(function () {
                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('module_id');
                history.replaceState({}, '', cleanUrl.toString());
            }, 500);
        });
    }
});
</script>
        <?php
    }
}
?>
