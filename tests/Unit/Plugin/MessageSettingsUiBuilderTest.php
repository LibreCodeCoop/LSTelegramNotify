<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Plugin\MessageSettingsUiBuilder;
use PHPUnit\Framework\TestCase;

final class MessageSettingsUiBuilderTest extends TestCase
{
    public function testBuildScriptBindsVisibilityToSendMessageSetting(): void
    {
        $script = (new MessageSettingsUiBuilder())->buildScript();

        $this->assertStringContainsString('SendMessage', $script);
        $this->assertStringContainsString("filter(':checkbox')", $script);
        $this->assertStringContainsString('input[type="checkbox"][name$="[SendMessage]"]', $script);
        $this->assertStringContainsString("['ParseMode', 'DefaultText']", $script);
        $this->assertStringContainsString('.toggle(enabled)', $script);
        $this->assertStringContainsString('change.lsTelegramNotifyMessageSettings', $script);
        $this->assertStringContainsString('shown.bs.tab.lsTelegramNotifyMessageSettings', $script);
        $this->assertStringContainsString('pjax:scriptcomplete.lsTelegramNotifyMessageSettings', $script);
    }

    public function testBuildScriptSupportsGlobalAndSurveySettingNames(): void
    {
        $script = (new MessageSettingsUiBuilder())->buildScript();

        $this->assertStringContainsString("'[name=\"' + settingName + '\"], '", $script);
        $this->assertStringContainsString("'[name$=\"[' + settingName + ']\"]'", $script);
    }

    public function testBuildScriptUsesStableLimeSurveySettingContainers(): void
    {
        $script = (new MessageSettingsUiBuilder())->buildScript();

        $this->assertStringContainsString('.mb-3, .form-group', $script);
        $this->assertStringContainsString("closest('.row')", $script);
    }
}
