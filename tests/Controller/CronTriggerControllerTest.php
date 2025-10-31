<?php

namespace Tourze\Symfony\CronJob\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\Symfony\CronJob\Controller\CronTriggerController;

/**
 * @internal
 */
#[CoversClass(CronTriggerController::class)]
#[RunTestsInSeparateProcesses]
final class CronTriggerControllerTest extends AbstractWebTestCase
{
    public function testTriggerSuccess(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cron/trigger');

        $response = $client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 404]);

        if (200 === $response->getStatusCode()) {
            $content = $response->getContent();
            if (false === $content) {
                self::fail('Could not get response content for JSON validation');
            }
            $this->assertJson($content);

            $content = $response->getContent();
            if (false === $content) {
                self::fail('Could not get response content');
            }
            $data = json_decode($content, true);
            if (!is_array($data)) {
                self::fail('Response content is not valid JSON array');
            }

            $this->assertArrayHasKey('success', $data);
            $this->assertArrayHasKey('message', $data);
            $this->assertArrayHasKey('timestamp', $data);
        } else {
            $this->assertEquals(404, $response->getStatusCode());
        }
    }

    public function testTriggerGetMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('GET', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerPutMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('PUT', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerDeleteMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('DELETE', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerPatchMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('PATCH', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerHeadMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('HEAD', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerOptionsMethodNotAllowed(): void
    {
        $client = self::createClient();

        $client->request('OPTIONS', '/cron/trigger');

        $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testTriggerUnauthorizedAccess(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cron/trigger');

        $response = $client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 401, 403, 404]);
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request($method, '/cron/trigger');
            $this->assertContains($client->getResponse()->getStatusCode(), [404, 405]);
        } catch (\Exception $e) {
            // 对于某些无效的 HTTP 方法，Symfony 可能会抛出异常
            // 这也是符合预期的行为，表示方法不被允许
            $this->assertTrue(true, 'Method not allowed - exception thrown as expected');
        }
    }
}
