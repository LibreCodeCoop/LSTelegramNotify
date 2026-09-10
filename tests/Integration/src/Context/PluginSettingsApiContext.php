<?php

declare(strict_types=1);

namespace LSTelegramNotify\Tests\Integration\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

final class PluginSettingsApiContext implements Context
{
    private Client $client;
    private CookieJar $cookieJar;
    private ?\PDO $pdo = null;
    private ?ResponseInterface $response = null;
    private string $csrfToken = '';

    /**
     * @var array<string, string>
     */
    private array $fields = [];

    #[BeforeScenario]
    public function resetScenarioState(): void
    {
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'base_uri' => rtrim($this->getEnv('LIMESURVEY_BASE_URL', 'http://127.0.0.1:8080'), '/') . '/',
            'cookies' => $this->cookieJar,
            'http_errors' => false,
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json, text/html;q=0.9, */*;q=0.8',
            ],
        ]);
        $this->pdo = null;
        $this->response = null;
        $this->csrfToken = '';
        $this->fields = [
            'PLUGIN_NAME' => $this->getEnv('LIMESURVEY_PLUGIN_NAME', 'LSTelegramNotify'),
        ];
        $this->ensurePluginIsRegistered();
    }

    #[Given('I am authenticated in LimeSurvey admin')]
    public function iAmAuthenticatedInLimeSurveyAdmin(): void
    {
        $loginPage = $this->client->get('index.php/admin/authentication/sa/login');
        $this->assertStatusCode(200, $loginPage->getStatusCode(), 'Could not open the LimeSurvey login page.');
        $this->csrfToken = $this->extractCsrfToken((string) $loginPage->getBody());

        $this->response = $this->client->post('index.php/admin/authentication/sa/login', [
            'form_params' => [
                'YII_CSRF_TOKEN' => $this->csrfToken,
                'authMethod' => 'Authdb',
                'user' => $this->getEnv('LIMESURVEY_ADMIN_USER', $this->getEnv('LIMESURVEY_STACK_ADMIN_USER', 'admin')),
                'password' => $this->getEnv('LIMESURVEY_ADMIN_PASSWORD', $this->getEnv('LIMESURVEY_STACK_ADMIN_PASSWORD', 'admin')),
                'loginlang' => 'en',
                'action' => 'login',
                'login_submit' => 'login',
            ],
        ]);

        $adminPage = $this->client->get('index.php/admin/index');
        $this->assertStatusCode(200, $adminPage->getStatusCode(), 'Could not open the LimeSurvey admin page after login.');
        $adminPageBody = (string) $adminPage->getBody();

        if (str_contains($adminPageBody, 'id="loginform"')) {
            throw new \RuntimeException('LimeSurvey admin login failed.');
        }

        $this->csrfToken = $this->extractCsrfToken($adminPageBody);
        $this->response = $adminPage;
    }

    #[Given('I create a minimal survey with a unique survey id')]
    #[Given('I use a unique survey id')]
    public function iCreateAMinimalSurveyWithAUniqueSurveyId(): void
    {
        do {
            $surveyId = random_int(200000000, 2147483647);
        } while ($this->surveyExists($surveyId));

        $this->createMinimalSurvey($surveyId);
        $this->fields['SURVEY_ID'] = (string) $surveyId;
    }

    #[When('I send a POST request to :path with form data:')]
    public function iSendAPostRequestToWithFormData(string $path, TableNode $body): void
    {
        $formParams = [];

        foreach ($body->getRowsHash() as $key => $value) {
            $this->assignFormValue(
                $formParams,
                $this->parseText($key),
                $this->parseText($value)
            );
        }

        if ($this->csrfToken !== '' && !array_key_exists('YII_CSRF_TOKEN', $formParams)) {
            $formParams['YII_CSRF_TOKEN'] = $this->csrfToken;
        }

        $this->response = $this->client->post(ltrim($this->parseText($path), '/'), [
            'allow_redirects' => false,
            'form_params' => $formParams,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    #[Then('the response code should be :statusCode')]
    public function theResponseCodeShouldBe(int $statusCode): void
    {
        $response = $this->requireResponse();
        $this->assertStatusCode($statusCode, $response->getStatusCode(), 'Unexpected response status code.');
    }

    #[Then('the JSON response should contain:')]
    public function theJsonResponseShouldContain(TableNode $expectedRows): void
    {
        $payload = $this->decodeJsonResponse();

        foreach ($expectedRows->getRowsHash() as $key => $expectedValue) {
            $normalizedKey = $this->parseText($key);

            if (!array_key_exists($normalizedKey, $payload)) {
                throw new \RuntimeException(sprintf('JSON response does not contain key "%s".', $normalizedKey));
            }

            $expected = $this->normalizeExpectedValue($this->parseText($expectedValue));
            $actual = $payload[$normalizedKey];

            if ($actual !== $expected) {
                throw new \RuntimeException(sprintf(
                    'Unexpected JSON value for key "%s". Expected %s, got %s.',
                    $normalizedKey,
                    $this->exportValue($expected),
                    $this->exportValue($actual)
                ));
            }
        }
    }

    #[Then('the survey plugin setting :settingName for :surveyId should equal :expectedValue')]
    public function theSurveyPluginSettingForShouldEqual(string $settingName, string $surveyId, string $expectedValue): void
    {
        $statement = $this->getDatabaseConnection()->prepare(
            'SELECT ps.`value` '
            . 'FROM `lime_plugin_settings` ps '
            . 'INNER JOIN `lime_plugins` p ON p.`id` = ps.`plugin_id` '
            . 'WHERE p.`name` = :pluginName '
            . '  AND ps.`model` = :model '
            . '  AND ps.`model_id` = :surveyId '
            . '  AND ps.`key` = :settingName '
            . 'LIMIT 1'
        );
        $statement->execute([
            'pluginName' => $this->fields['PLUGIN_NAME'],
            'model' => 'Survey',
            'surveyId' => (int) $this->parseText($surveyId),
            'settingName' => $this->parseText($settingName),
        ]);

        $storedValue = $statement->fetchColumn();

        if ($storedValue === false) {
            throw new \RuntimeException(sprintf(
                'Could not find persisted setting "%s" for survey %s.',
                $settingName,
                $this->parseText($surveyId)
            ));
        }

        $actualValue = $this->decodeStoredSettingValue((string) $storedValue);
        $expected = $this->parseText($expectedValue);

        if ($actualValue !== $expected) {
            throw new \RuntimeException(sprintf(
                'Unexpected persisted value for setting "%s". Expected %s, got %s.',
                $settingName,
                $this->exportValue($expected),
                $this->exportValue($actualValue)
            ));
        }
    }

    private function requireResponse(): ResponseInterface
    {
        if ($this->response === null) {
            throw new \RuntimeException('No HTTP response is available yet.');
        }

        return $this->response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(): array
    {
        $body = $this->getResponseBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Response body is not valid JSON: %s', $body));
        }

        return $decoded;
    }

    private function getResponseBody(): string
    {
        $body = $this->requireResponse()->getBody();
        $body->rewind();

        return $body->getContents();
    }

    private function extractCsrfToken(string $html): string
    {
        $patterns = [
            '/<input[^>]*name="YII_CSRF_TOKEN"[^>]*value="([^"]+)"/i',
            '/<input[^>]*value="([^"]+)"[^>]*name="YII_CSRF_TOKEN"/i',
            '/"csrfToken"\s*:\s*"([^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return html_entity_decode($matches[1], ENT_QUOTES);
            }
        }

        throw new \RuntimeException('Could not extract the LimeSurvey CSRF token from the HTML response.');
    }

    /**
     * @param array<string, mixed> $formParams
     */
    private function assignFormValue(array &$formParams, string $key, string $value): void
    {
        if (!str_contains($key, '[')) {
            $formParams[$key] = $value;
            return;
        }

        preg_match_all('/([^\[\]]+)/', $key, $matches);
        $segments = $matches[1];

        if ($segments === []) {
            $formParams[$key] = $value;
            return;
        }

        $cursor = &$formParams;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($index === $lastIndex) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }
    }

    private function parseText(string $text): string
    {
        return (string) preg_replace_callback('/<([A-Z0-9_]+)>/', function (array $matches): string {
            $field = $matches[1];

            if (!array_key_exists($field, $this->fields)) {
                throw new \RuntimeException(sprintf('Unknown placeholder <%s>.', $field));
            }

            return $this->fields[$field];
        }, $text);
    }

    private function normalizeExpectedValue(string $value): bool|string|null
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }

    private function decodeStoredSettingValue(string $storedValue): string
    {
        $decoded = json_decode($storedValue, true);

        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
            return $decoded;
        }

        return $storedValue;
    }

    private function ensurePluginIsRegistered(): void
    {
        $connection = $this->getDatabaseConnection();
        $pluginName = $this->fields['PLUGIN_NAME'];
        $pluginType = $this->getEnv('LIMESURVEY_STACK_PLUGIN_DB_TYPE', 'user');
        $pluginTarget = $this->getPluginTarget();
        $version = $this->resolvePluginVersion($pluginTarget);

        $select = $connection->prepare('SELECT `id` FROM `lime_plugins` WHERE `name` = :name LIMIT 1');
        $select->execute(['name' => $pluginName]);
        $pluginId = $select->fetchColumn();

        if ($pluginId === false) {
            $insert = $connection->prepare(
                'INSERT INTO `lime_plugins` (`name`, `plugin_type`, `active`, `priority`, `version`, `load_error`, `load_error_message`) '
                . 'VALUES (:name, :pluginType, 1, 0, :version, 0, "")'
            );
            $insert->execute([
                'name' => $pluginName,
                'pluginType' => $pluginType,
                'version' => $version,
            ]);

            return;
        }

        $update = $connection->prepare(
            'UPDATE `lime_plugins` '
            . 'SET `plugin_type` = :pluginType, `active` = 1, `priority` = 0, `version` = :version, `load_error` = 0, `load_error_message` = "" '
            . 'WHERE `id` = :id'
        );
        $update->execute([
            'id' => $pluginId,
            'pluginType' => $pluginType,
            'version' => $version,
        ]);
    }

    private function getPluginTarget(): string
    {
        return rtrim($this->getEnv('LIMESURVEY_STACK_PLUGIN_TARGET', '/var/www/html/plugins/LSTelegramNotify'), '/');
    }

    private function resolvePluginVersion(string $pluginTarget): string
    {
        $configFile = $pluginTarget . '/config.xml';

        if (!is_file($configFile)) {
            return '0.0.0';
        }

        $config = simplexml_load_file($configFile);

        if ($config === false || !isset($config->metadata->version)) {
            return '0.0.0';
        }

        return (string) $config->metadata->version;
    }

    private function getDatabaseConnection(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        try {
            $this->pdo = new \PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $this->getEnv('LIMESURVEY_DB_HOST', $this->getEnv('LIMESURVEY_STACK_DB_HOST', 'lime-db')),
                    $this->getEnv('LIMESURVEY_DB_PORT', $this->getEnv('LIMESURVEY_STACK_DB_PORT', '3306')),
                    $this->getEnv('LIMESURVEY_DB_NAME', $this->getEnv('LIMESURVEY_STACK_DB_NAME', 'limesurvey'))
                ),
                $this->getEnv('LIMESURVEY_DB_USER', $this->getEnv('LIMESURVEY_STACK_DB_USERNAME', 'limesurvey')),
                $this->getEnv('LIMESURVEY_DB_PASSWORD', $this->getEnv('LIMESURVEY_STACK_DB_PASSWORD', 'limesurvey')),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $exception) {
            throw new \RuntimeException('Could not connect to the LimeSurvey integration database.', 0, $exception);
        }

        return $this->pdo;
    }

    private function surveyExists(int $surveyId): bool
    {
        $statement = $this->getDatabaseConnection()->prepare(
            'SELECT 1 FROM `lime_surveys` WHERE `sid` = :surveyId LIMIT 1'
        );
        $statement->execute(['surveyId' => $surveyId]);

        return $statement->fetchColumn() !== false;
    }

    private function createMinimalSurvey(int $surveyId): void
    {
        $connection = $this->getDatabaseConnection();
        $timestamp = gmdate('Y-m-d H:i:s');
        $title = sprintf('Integration survey %d', $surveyId);

        $insertSurvey = $connection->prepare(
            'INSERT INTO `lime_surveys` (`sid`, `owner_id`, `gsid`, `admin`, `active`, `adminemail`, `anonymized`, `format`, `template`, `language`, `datestamp`, `datecreated`, `lastmodified`) '
            . 'VALUES (:sid, :ownerId, 1, :adminName, "N", :adminEmail, "N", "G", "default", "en", "Y", :dateCreated, :lastModified)'
        );
        $insertSurvey->execute([
            'sid' => $surveyId,
            'ownerId' => 1,
            'adminName' => 'Administrator',
            'adminEmail' => 'admin@example.com',
            'dateCreated' => $timestamp,
            'lastModified' => $timestamp,
        ]);

        $insertLanguageSettings = $connection->prepare(
            'INSERT INTO `lime_surveys_languagesettings` (`surveyls_survey_id`, `surveyls_language`, `surveyls_title`) '
            . 'VALUES (:surveyId, "en", :title)'
        );
        $insertLanguageSettings->execute([
            'surveyId' => $surveyId,
            'title' => $title,
        ]);
    }

    private function getEnv(string $name, string $default): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    private function assertStatusCode(int $expected, int $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf('%s Expected %d, got %d.', $message, $expected, $actual));
        }
    }

    private function exportValue(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return var_export($value, true);
        }

        return $encoded;
    }
}
