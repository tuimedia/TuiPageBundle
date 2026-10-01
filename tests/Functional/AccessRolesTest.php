<?php

namespace Tui\PageBundle\Tests\Functional;

/**
 * An app that leaves access_roles out of its config gets the documented defaults: reads and
 * export are open, while writes and history need ROLE_ADMIN.
 */
class AccessRolesTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->bootApp(['default_access_roles' => true]);
    }

    public function testCreatingNeedsAdmin(): void
    {
        $page = self::fixture('page.json');

        [$status] = $this->request('POST', '/api/pages', $page);
        self::assertSame(401, $status, 'Anonymous');

        [$status] = $this->request('POST', '/api/pages', $page, as: 'editor');
        self::assertSame(403, $status, 'ROLE_USER');

        [$status, $body] = $this->request('POST', '/api/pages', $page, as: 'admin');
        self::assertSame(201, $status, self::describe($body));
    }

    public function testReadsAndExportAreOpen(): void
    {
        $this->request('POST', '/api/pages', self::fixture('page.json'), as: 'admin');

        [$status] = $this->request('GET', '/api/pages');
        self::assertSame(200, $status, 'list');

        [$status] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(200, $status, 'retrieve');

        [$status] = $this->request('GET', '/api/translations/hello-world/fr?state=live');
        self::assertSame(201, $status, 'export');
    }

    public function testHistoryAndOtherWritesNeedAdmin(): void
    {
        [, $page] = $this->request('POST', '/api/pages', self::fixture('page.json'), as: 'admin');
        [, $xliff] = $this->request('GET', '/api/translations/hello-world/fr?state=live');

        $requests = [
            'history' => ['GET', '/api/pages/hello-world/history?state=live', null, 200],
            'edit' => ['PUT', '/api/pages/hello-world?state=live', self::asUpdate($page), 200],
            'import' => ['PUT', '/api/translations/hello-world?state=live&destination=original', $xliff, 201],
            'delete' => ['DELETE', '/api/pages/hello-world?state=live', null, 204],
        ];

        foreach ($requests as $check => [$method, $uri, $body, $adminStatus]) {
            [$status] = $this->request($method, $uri, $body);
            self::assertSame(401, $status, "$check, anonymous");

            [$status] = $this->request($method, $uri, $body, as: 'editor');
            self::assertSame(403, $status, "$check, ROLE_USER");

            [$status, $response] = $this->request($method, $uri, $body, as: 'admin');
            self::assertSame($adminStatus, $status, "$check, ROLE_ADMIN: " . self::describe($response));
        }
    }
}
