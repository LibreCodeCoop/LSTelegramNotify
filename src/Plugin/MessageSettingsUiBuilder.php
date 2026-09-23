<?php

namespace LibreCodeCoop\LSTelegramNotify\Plugin;

final class MessageSettingsUiBuilder
{
    public function buildScript(): string
    {
        return <<<'JS'
var findLSTelegramNotifySettingField = function (settingName) {
    return $(
        '[name="' + settingName + '"], ' +
        '[name$="[' + settingName + ']"]'
    ).first();
};

var findLSTelegramNotifySettingContainer = function ($field) {
    if (!$field.length) {
        return $();
    }

    var $container = $field.closest('.mb-3, .form-group');

    if ($container.length) {
        return $container.first();
    }

    return $field.closest('.row').first();
};

var updateLSTelegramNotifyMessageSettingsVisibility = function () {
    var $sendMessage = findLSTelegramNotifySettingField('SendMessage')
        .filter(':checkbox')
        .first();

    if (!$sendMessage.length) {
        return;
    }

    var enabled = $sendMessage.is(':checked');

    ['ParseMode', 'DefaultText'].forEach(function (settingName) {
        findLSTelegramNotifySettingContainer(
            findLSTelegramNotifySettingField(settingName)
        ).toggle(enabled);
    });
};

$(updateLSTelegramNotifyMessageSettingsVisibility);
$(document)
    .off(
        'change.lsTelegramNotifyMessageSettings',
        'input[type="checkbox"][name$="[SendMessage]"], input[type="checkbox"][name="SendMessage"]'
    )
    .on(
        'change.lsTelegramNotifyMessageSettings',
        'input[type="checkbox"][name$="[SendMessage]"], input[type="checkbox"][name="SendMessage"]',
        updateLSTelegramNotifyMessageSettingsVisibility
    )
    .off('shown.bs.tab.lsTelegramNotifyMessageSettings')
    .on(
        'shown.bs.tab.lsTelegramNotifyMessageSettings',
        '[data-bs-toggle="tab"], [data-toggle="tab"]',
        updateLSTelegramNotifyMessageSettingsVisibility
    )
    .off('pjax:scriptcomplete.lsTelegramNotifyMessageSettings')
    .on(
        'pjax:scriptcomplete.lsTelegramNotifyMessageSettings',
        updateLSTelegramNotifyMessageSettingsVisibility
    );
JS;
    }
}
