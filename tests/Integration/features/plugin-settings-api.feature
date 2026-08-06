Feature: Save survey plugin settings through the plugin helper API
  In order to persist survey-specific Telegram settings reliably
  As a LimeSurvey administrator
  I want the LSTelegramNotify save API to return clear results and store the posted values

  Background:
    Given I am authenticated in LimeSurvey admin

  Scenario: Persist survey-specific Telegram settings
    Given I create a minimal survey with a unique survey id
    When I send a POST request to "/index.php/admin/pluginhelper/sa/ajax/plugin/<PLUGIN_NAME>/method/saveSurveyPluginSettings" with form data:
      | sid                                  | <SURVEY_ID>                          |
      | plugin[<PLUGIN_NAME>][Enable]        | 1                                    |
      | plugin[<PLUGIN_NAME>][SendMessage]   | 1                                    |
      | plugin[<PLUGIN_NAME>][ParseMode]     | HTML                                 |
      | plugin[<PLUGIN_NAME>][DefaultText]   | Survey <SURVEY_ID> -> {{G01Q02.answer}} |
    Then the response code should be 200
    And the JSON response should contain:
      | success | true |
    And the survey plugin setting "Enable" for "<SURVEY_ID>" should equal "1"
    And the survey plugin setting "SendMessage" for "<SURVEY_ID>" should equal "1"
    And the survey plugin setting "ParseMode" for "<SURVEY_ID>" should equal "HTML"
    And the survey plugin setting "DefaultText" for "<SURVEY_ID>" should equal "Survey <SURVEY_ID> -> {{G01Q02.answer}}"

  Scenario: Reject a save without a survey id
    When I send a POST request to "/index.php/admin/pluginhelper/sa/ajax/plugin/<PLUGIN_NAME>/method/saveSurveyPluginSettings" with form data:
      | plugin[<PLUGIN_NAME>][DefaultText] | Missing survey id |
    Then the response code should be 400
    And the JSON response should contain:
      | success | false              |
      | message | Missing survey id. |
