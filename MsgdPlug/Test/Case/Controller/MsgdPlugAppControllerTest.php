<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/Lib/Utility/MsgdLoggerUtility.php';
require_once dirname(__DIR__, 3) . '/Lib/Utility/MsgdSanitizerUtility.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdUserDTO.php';
require_once dirname(__DIR__, 3) . '/Controller/MsgdPlugAppController.php';

if (class_exists('App')) {
    App::uses('CakePlugin', 'Core');
    App::uses('CakeRequest', 'Network');
    App::uses('CakeResponse', 'Network');
    App::uses('Controller', 'Controller');
    App::uses('AuthComponent', 'Controller/Component');
}

/**
 * Test suite for MsgdPlugAppController.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Test.Case.Controller
 */
final class MsgdPlugAppControllerTest extends TestCase
{
    /**
     * Creates a controller test double.
     *
     * @param MockObject|null $authMock Authentication component mock.
     *
     * @return MsgdPlugAppController
     */
    private function createController(?MockObject $authMock = null): MsgdPlugAppController
    {
        /** @var MsgdPlugAppController&MockObject $controller */
        $controller = $this->getMockBuilder(MsgdPlugAppController::class)
            ->onlyMethods([])
            ->getMock();

        $controller->request = new CakeRequest();
        $controller->response = new CakeResponse();
        $controller->Auth = $authMock ?? $this->createAuthMock();

        return $controller;
    }

    /**
     * Creates an authentication component mock.
     *
     * @param mixed $user Authentication result.
     *
     * @return MockObject
     */
    private function createAuthMock(mixed $user = null): MockObject
    {
        $authMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['user', 'deny'])
            ->getMock();

        $authMock->method('user')->willReturn($user);
        $authMock->method('deny');

        return $authMock;
    }

    /**
     * Returns valid authenticated user data.
     *
     * @return array<string, mixed>
     */
    private function validUser(): array
    {
        return [
            'id' => 1,
            'org_id' => 10,
            'email' => 'test@example.com',
            'disabled' => false,
            'Role' => [
                'perm_site_admin' => false,
                'perm_sharing_group' => true,
                'perm_sync' => true,
            ],
            'Organisation' => [
                'id' => 10,
                'name' => 'Test Organisation',
                'uuid' => '11111111-1111-4111-8111-111111111111',
            ],
        ];
    }

    /**
     * Verifies that beforeFilter configures security options.
     *
     * @return void
     */
    public function testBeforeFilterConfiguresSecurity(): void
    {
        $controller = new MsgdPlugAppController();
        $controller->request = new CakeRequest();
        $controller->response = new CakeResponse();
        $controller->Auth = $this->createAuthMock($this->validUser());

        $security = new stdClass();
        $security->csrfCheck = false;
        $security->validatePost = true;
        $controller->Security = $security;

        $controller->beforeFilter();

        $this->assertTrue($controller->Security->csrfCheck);
        $this->assertFalse($controller->Security->validatePost);
    }

    /**
     * Verifies that beforeFilter configures the expected response headers.
     *
     * @return void
     */
    public function testBeforeFilterConfiguresSecurityHeaders(): void
    {
        $controller = $this->createController();
        $controller->Security = null;

        $controller->beforeFilter();

        $headers = $controller->response->header();

        $this->assertSame(
            'nosniff',
            $headers['X-Content-Type-Options']
        );
        $this->assertSame(
            'SAMEORIGIN',
            $headers['X-Frame-Options']
        );
        $this->assertSame(
            '1; mode=block',
            $headers['X-XSS-Protection']
        );
        $this->assertSame(
            'no-store, no-cache, must-revalidate, max-age=0',
            $headers['Cache-Control']
        );
        $this->assertSame(
            'no-cache',
            $headers['Pragma']
        );
    }

    /**
     * Verifies that getCurrentUser returns a normalized DTO.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testGetCurrentUserReturnsDto(): void
    {
        $controller = $this->createController(
            $this->createAuthMock($this->validUser())
        );

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('getCurrentUser');
        $method->setAccessible(true);

        $user = $method->invoke($controller);

        $this->assertInstanceOf(MsgdUserDTO::class, $user);
        $this->assertSame(1, $user->id);
        $this->assertSame(10, $user->orgId);
        $this->assertSame('test@example.com', $user->email);
    }

    /**
     * Verifies that getCurrentUser returns null without authentication.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testGetCurrentUserReturnsNullWithoutAuth(): void
    {
        $controller = $this->createController();
        $controller->Auth = null;

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('getCurrentUser');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller));
    }

    /**
     * Verifies that getCurrentUser returns null for an invalid authentication payload.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testGetCurrentUserReturnsNullForInvalidAuthPayload(): void
    {
        $controller = $this->createController(
            $this->createAuthMock('invalid')
        );

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('getCurrentUser');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller));
    }

    /**
     * Verifies that a valid authenticated AJAX request passes validation.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testValidateRequestReturnsTrueForValidAjaxUser(): void
    {
        $controller = $this->createController(
            $this->createAuthMock($this->validUser())
        );

        $controller->request->addDetector('ajax', [
            'env' => 'HTTP_X_REQUESTED_WITH',
            'value' => 'XMLHttpRequest',
        ]);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('validateRequest');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller));

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /**
     * Verifies that an unauthenticated request fails validation.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testValidateRequestReturnsFalseWithoutValidUser(): void
    {
        $controller = $this->createController(
            $this->createAuthMock()
        );

        $controller->request->addDetector('ajax', [
            'env' => 'HTTP_X_REQUESTED_WITH',
            'value' => 'XMLHttpRequest',
        ]);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('validateRequest');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller));

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /**
     * Verifies that a disabled user fails validation.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testValidateRequestReturnsFalseForDisabledUser(): void
    {
        $user = $this->validUser();
        $user['disabled'] = true;

        $controller = $this->createController(
            $this->createAuthMock($user)
        );

        $controller->request->addDetector('ajax', [
            'env' => 'HTTP_X_REQUESTED_WITH',
            'value' => 'XMLHttpRequest',
        ]);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('validateRequest');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller));

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /**
     * Verifies that appendNextToken adds the request token.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testAppendNextTokenAddsToken(): void
    {
        $controller = $this->createController();
        $controller->request->params['_Token']['key'] = 'next-token';

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('appendNextToken');
        $method->setAccessible(true);

        $payload = $method->invoke($controller, [
            'status' => 'success',
        ]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('next-token', $payload['nextToken']);
    }

    /**
     * Verifies that appendNextToken leaves the payload unchanged without a token.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testAppendNextTokenDoesNotAddMissingToken(): void
    {
        $controller = $this->createController();

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('appendNextToken');
        $method->setAccessible(true);

        $payload = [
            'status' => 'success',
        ];

        $this->assertSame(
            $payload,
            $method->invoke($controller, $payload)
        );
    }

    /**
     * Verifies that buildJsonResponse returns a JSON response.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testBuildJsonResponseReturnsJsonResponse(): void
    {
        $controller = $this->createController();

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('buildJsonResponse');
        $method->setAccessible(true);

        $response = $method->invoke(
            $controller,
            [
                'status' => 'success',
                'value' => '<test>',
            ],
            201
        );

        $this->assertInstanceOf(CakeResponse::class, $response);
        $this->assertSame(201, $response->statusCode());
        $this->assertSame('application/json', $response->type());

        $decoded = json_decode($response->body(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame('<test>', $decoded['value']);
    }

    /**
     * Verifies that buildJsonResponse returns an internal error for serialization failures.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function testBuildJsonResponseHandlesSerializationFailure(): void
    {
        $controller = $this->createController();

        $reflection = new ReflectionClass(MsgdPlugAppController::class);
        $method = $reflection->getMethod('buildJsonResponse');
        $method->setAccessible(true);

        $response = $method->invoke(
            $controller,
            [
                'status' => NAN,
            ]
        );

        $this->assertSame(500, $response->statusCode());
        $this->assertSame(
            '{"status":"error","message":"Internal serialization error."}',
            $response->body()
        );
    }
}
