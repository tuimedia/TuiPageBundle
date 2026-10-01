<?php

namespace Tui\PageBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tui\PageBundle\Tests\App\TestKernel;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    /**
     * @param array{tui?: array{search?: bool, search_index?: string, valid_languages?: string[]}} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, $options['tui'] ?? []);
    }

    /**
     * Boot the app with the given TestKernel options and an empty database.
     *
     * @param array{search?: bool, search_index?: string, valid_languages?: string[], default_access_roles?: bool} $options
     */
    protected function bootApp(array $options = []): void
    {
        $this->client = static::createClient(['tui' => $options]);
        $this->resetDatabase();
    }

    protected function resetDatabase(): void
    {
        $em = $this->entityManager();
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /**
     * @param string|null $as a TestKernel user to authenticate as (`admin` or `editor`), or null for anonymous
     *
     * @return array{int, mixed} status code and decoded JSON body (or the raw body if it isn't JSON)
     */
    protected function request(string $method, string $uri, string|array|null $body = null, ?string $as = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($as !== null) {
            $server += ['PHP_AUTH_USER' => $as, 'PHP_AUTH_PW' => 'pw'];
        }
        $this->client->request($method, $uri, [], [], $server, is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body);
        $response = $this->client->getResponse();
        $content = (string) $response->getContent();

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = $content;
        }

        return [$response->getStatusCode(), $decoded];
    }

    protected function rawResponse(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/' . $name), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Create a page through the API and return the response body.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    protected function createPage(array $page): array
    {
        [$status, $body] = $this->request('POST', '/api/pages', $page);
        self::assertSame(201, $status, 'Creating the page failed: ' . self::describe($body));

        return $body;
    }

    /**
     * A short description of a response body for assertion messages: the exception title of
     * an HTML error page, or the JSON.
     */
    protected static function describe(mixed $body): string
    {
        if (is_string($body) && preg_match('#<title>(.*?)</title>#s', $body, $m)) {
            return html_entity_decode($m[1]);
        }

        return (string) json_encode($body);
    }

    /**
     * A GET response turned back into a PUT body. Clients drop the null previousRevision of a
     * first revision, which the page schema only accepts as a string.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    protected static function asUpdate(array $page): array
    {
        if (($page['pageData']['previousRevision'] ?? null) === null) {
            unset($page['pageData']['previousRevision']);
        }

        return $page;
    }

    /**
     * Replace the values that change on every run (UUIDs, timestamps) so responses can be compared.
     */
    protected static function normalise(string $json): string
    {
        return (string) preg_replace(
            ['/"(id|revision|previousRevision)":"[0-9a-f-]{36}"/', '/"created":"[^"]+"/'],
            ['"$1":"UUID"', '"created":"DATE"'],
            $json,
        );
    }
}
