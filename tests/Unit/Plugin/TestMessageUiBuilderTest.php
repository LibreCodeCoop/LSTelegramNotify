<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Plugin\TestMessageUiBuilder;
use PHPUnit\Framework\TestCase;

final class TestMessageUiBuilderTest extends TestCase
{
    public function testBuildSettingCreatesGlobalTestControl(): void
    {
        $setting = (new TestMessageUiBuilder())->buildSetting();

        $this->assertSame('info', $setting['type']);
        $this->assertStringContainsString('Send test message', $setting['content']);
        $this->assertStringNotContainsString('data-survey-id=', $setting['content']);
        $this->assertStringContainsString('Save the Telegram settings before sending a test message.', $setting['content']);
    }

    public function testBuildSettingAddsSurveyContext(): void
    {
        $setting = (new TestMessageUiBuilder())->buildSetting(154438);

        $this->assertStringContainsString('data-survey-id="154438"', $setting['content']);
    }

    public function testBuildScriptPostsOnlyContextAndCsrfData(): void
    {
        $script = (new TestMessageUiBuilder())->buildScript('https://example.test/test');

        $this->assertStringContainsString('https://example.test/test', $script);
        $this->assertStringContainsString('payload.surveyId', $script);
        $this->assertStringContainsString('YII_CSRF_TOKEN', $script);
        $this->assertStringNotContainsString('AuthToken', $script);
        $this->assertStringNotContainsString('ChatId', $script);
    }
}
