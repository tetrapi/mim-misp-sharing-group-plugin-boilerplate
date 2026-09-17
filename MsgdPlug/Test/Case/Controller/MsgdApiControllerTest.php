<?php

declare(strict_types=1);

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/Lib/Utility/MsgdSanitizerUtility.php';
require_once dirname(__DIR__, 3) . '/Lib/Utility/MsgdLoggerUtility.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdUserDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdCheckBlueprintDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdGetBlueprintRulesGroupsDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdGetSharingGroupsDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdProcessGroupsDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdProcessResultDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/DTO/MsgdSharingGroupDTO.php';
require_once dirname(__DIR__, 3) . '/Lib/Enum/MsgdPluginConfigEnum.php';
require_once dirname(__DIR__, 3) . '/Lib/Service/MsgdSharingGroupService.php';
require_once dirname(__DIR__, 3) . '/Lib/Service/MsgdApiControllerService.php';
require_once dirname(__DIR__, 3) . '/Controller/MsgdPlugAppController.php';
require_once dirname(__DIR__, 3) . '/Controller/MsgdApiController.php';

if (class_exists('App')) {
    App::uses('CakePlugin', 'Core');
    App::uses('CakeRequest', 'Network');
    App::uses('CakeResponse', 'Network');
    App::uses('Controller', 'Controller');
    App::uses('AuthComponent', 'Controller/Component');
}

/**
 * Test suite for MsgdApiController.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Test.Case.Controller
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
final class MsgdApiControllerTest extends TestCase
{
    private const UUID_1 = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const UUID_2 = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a22';

    private const GROUP_ID_1 = 10;
    private const GROUP_ID_2 = 20;

    /**
     * Creates a partially mocked MsgdApiController instance.
     *
     * @param MockObject $serviceMock Controller service mock.
     * @param string $method HTTP request method.
     * @param bool $isRequestValid Mock result for request validation.
     * @param bool $useIds Mock result for identifier mode.
     *
     * @return MsgdApiController
     */
    private function createController(
        MockObject $serviceMock,
        string $method = 'GET',
        bool $isRequestValid = true,
        bool $useIds = false
    ): MsgdApiController {
        $_SERVER['REQUEST_METHOD'] = $method;

        /** @var MsgdApiController&MockObject $controller */
        $controller = $this->getMockBuilder(MsgdApiController::class)
            ->onlyMethods(['validateRequest'])
            ->getMock();

        $controller->method('validateRequest')->willReturn($isRequestValid);

        $controller->request = new CakeRequest();
        $controller->response = new CakeResponse();

        $authMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['user'])
            ->getMock();

        $authMock->method('user')->willReturn($this->validUser());

        $controller->Auth = $authMock;

        $serviceMock->method('isUsingIds')->willReturn($useIds);

        $reflection = new ReflectionClass(MsgdApiController::class);
        $property = $reflection->getProperty('msgdService');
        $property->setAccessible(true);
        $property->setValue($controller, $serviceMock);

        return $controller;
    }

    /**
     * Creates a controller service mock.
     *
     * @return MockObject
     */
    private function createServiceMock(): MockObject
    {
        return $this->getMockBuilder(MsgdApiControllerService::class)
            ->disableOriginalConstructor()
            ->getMock();
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
     * Decodes a JSON response body.
     *
     * @param CakeResponse $response Controller response.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(CakeResponse $response): array
    {
        $decoded = json_decode($response->body(), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Tests unauthorized access for checkUserPermission.
     *
     * @return void
     */
    public function testCheckUserPermissionReturns403WhenUnauthorized(): void
    {
        $serviceMock = $this->createServiceMock();
        $controller = $this->createController($serviceMock, 'GET', false);

        $response = $controller->checkUserPermission();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Unauthorized access.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests successful permission verification.
     *
     * @return void
     */
    public function testCheckUserPermissionReturns200(): void
    {
        $serviceMock = $this->createServiceMock();
        $serviceMock->expects($this->once())
            ->method('hasSharingGroupAccess')
            ->with($this->isInstanceOf(MsgdUserDTO::class), false)
            ->willReturn(true);

        $controller = $this->createController($serviceMock);

        $response = $controller->checkUserPermission();

        $this->assertSame(200, $response->statusCode());

        $payload = $this->decodeResponse($response);

        $this->assertSame('success', $payload['status']);
        $this->assertTrue($payload['allowed']);
    }

    /**
     * Tests forbidden permission verification.
     *
     * @return void
     */
    public function testCheckUserPermissionReturns403OnForbiddenException(): void
    {
        $serviceMock = $this->createServiceMock();
        $serviceMock->expects($this->once())
            ->method('hasSharingGroupAccess')
            ->with($this->isInstanceOf(MsgdUserDTO::class), false)
            ->willThrowException(new ForbiddenException('Access denied.'));

        $controller = $this->createController($serviceMock);

        $response = $controller->checkUserPermission();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Access denied.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests internal errors during permission verification.
     *
     * @return void
     */
    public function testCheckUserPermissionReturns500OnThrowable(): void
    {
        $serviceMock = $this->createServiceMock();
        $serviceMock->expects($this->once())
            ->method('hasSharingGroupAccess')
            ->with($this->isInstanceOf(MsgdUserDTO::class), false)
            ->willThrowException(new RuntimeException('Service failure.'));

        $controller = $this->createController($serviceMock);

        $response = $controller->checkUserPermission();

        $this->assertSame(500, $response->statusCode());

        $payload = $this->decodeResponse($response);

        $this->assertSame('error', $payload['status']);
        $this->assertFalse($payload['allowed']);
        $this->assertSame(
            'Failed to verify user permissions.',
            $payload['message']
        );
    }

    /**
     * Tests successful blueprint rules group retrieval.
     *
     * @return void
     */
    public function testGetBlueprintRulesGroupsReturns200(): void
    {
        $serviceMock = $this->createServiceMock();
        $groups = [
            ['id' => 1, 'name' => 'Group 1'],
        ];

        $serviceMock->expects($this->once())
            ->method('getSharingGroupsByGeneratedBlueprintGroup')
            ->with($this->isInstanceOf(MsgdUserDTO::class), self::GROUP_ID_1)
            ->willReturn($groups);

        $controller = $this->createController($serviceMock);
        $controller->request->query = [
            'group' => self::GROUP_ID_1,
        ];

        $response = $controller->getBlueprintRulesGroups();

        $this->assertSame(200, $response->statusCode());

        $payload = $this->decodeResponse($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame($groups, $payload['groups']);
    }

    /**
     * Tests invalid blueprint rules group parameters.
     *
     * @return void
     */
    public function testGetBlueprintRulesGroupsReturns400ForInvalidQuery(): void
    {
        $serviceMock = $this->createServiceMock();
        $controller = $this->createController($serviceMock);
        $controller->request->query = [
            'group' => 'invalid',
        ];

        $response = $controller->getBlueprintRulesGroups();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid parameters provided for blueprint rules.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests successful sharing group retrieval.
     *
     * @return void
     */
    public function testGetSharingGroupsReturns200(): void
    {
        $serviceMock = $this->createServiceMock();
        $groups = [
            ['id' => 1, 'name' => 'Group 1'],
        ];

        $serviceMock->expects($this->once())
            ->method('getAvailableSharingGroups')
            ->with($this->isInstanceOf(MsgdUserDTO::class), false)
            ->willReturn($groups);

        $controller = $this->createController($serviceMock);

        $response = $controller->getSharingGroups();

        $this->assertSame(200, $response->statusCode());

        $payload = $this->decodeResponse($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame($groups, $payload['groups']);
    }

    /**
     * Tests unauthorized access for sharing group retrieval.
     *
     * @return void
     */
    public function testGetSharingGroupsReturns403WhenUnauthorized(): void
    {
        $serviceMock = $this->createServiceMock();
        $controller = $this->createController($serviceMock, 'GET', false);

        $response = $controller->getSharingGroups();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Unauthorized access.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests invalid sharing group parameters.
     *
     * @return void
     */
    public function testGetSharingGroupsReturns400ForInvalidQuery(): void
    {
        $serviceMock = $this->createServiceMock();
        $controller = $this->createController($serviceMock);
        $controller->request->query = [
            'all' => 'invalid',
        ];

        $response = $controller->getSharingGroups();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid parameters provided for sharing groups.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests internal errors during sharing group retrieval.
     *
     * @return void
     */
    public function testGetSharingGroupsReturns500OnThrowable(): void
    {
        $serviceMock = $this->createServiceMock();
        $serviceMock->expects($this->once())
            ->method('getAvailableSharingGroups')
            ->with($this->isInstanceOf(MsgdUserDTO::class), false)
            ->willThrowException(new RuntimeException('Service failure.'));

        $controller = $this->createController($serviceMock);

        $response = $controller->getSharingGroups();

        $this->assertSame(500, $response->statusCode());
        $this->assertSame(
            'Failed to retrieve sharing groups data.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Provides identifier modes for blueprint checks.
     *
     * @return array<string, array{bool, array<int, int|string>}>
     */
    public function blueprintIdentifierProvider(): array
    {
        return [
            'UUIDs' => [
                false,
                [self::UUID_1, self::UUID_2],
            ],
            'IDs' => [
                true,
                [self::GROUP_ID_1, self::GROUP_ID_2],
            ],
        ];
    }

    /**
     * Tests successful blueprint verification.
     *
     * @param bool $useIds Identifier mode.
     * @param array<int, int|string> $groups Groups to verify.
     *
     * @return void
     *
     * @dataProvider blueprintIdentifierProvider
     */
    public function testCheckBlueprintReturns200(
        bool $useIds,
        array $groups
    ): void {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('isUsingIds')
            ->willReturn($useIds);

        $serviceMock->expects($this->once())
            ->method('checkBlueprint')
            ->with(
                $this->isInstanceOf(MsgdUserDTO::class),
                $this->isInstanceOf(MsgdCheckBlueprintDTO::class)
            )
            ->willReturn(true);

        $controller = $this->createController(
            $serviceMock,
            'POST',
            true,
            $useIds
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => $groups,
            ],
        ];

        $controller->request->params['_Token']['key'] = 'next-token';

        $response = $controller->checkBlueprint();

        $this->assertSame(200, $response->statusCode());

        $payload = $this->decodeResponse($response);

        $this->assertSame('success', $payload['status']);
        $this->assertTrue($payload['exists']);
        $this->assertSame('next-token', $payload['nextToken']);
    }

    /**
     * Tests invalid blueprint payload handling.
     *
     * @return void
     */
    public function testCheckBlueprintReturns400ForInvalidPayload(): void
    {
        $serviceMock = $this->createServiceMock();

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => 'invalid',
            ],
        ];

        $response = $controller->checkBlueprint();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid payload format for blueprint verification.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests rejection of numeric identifiers in UUID mode.
     *
     * @return void
     */
    public function testCheckBlueprintRejectsIdInUuidMode(): void
    {
        $serviceMock = $this->createServiceMock();

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::GROUP_ID_1],
            ],
        ];

        $response = $controller->checkBlueprint();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid payload format for blueprint verification.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests rejection of UUID identifiers in ID mode.
     *
     * @return void
     */
    public function testCheckBlueprintRejectsUuidInIdMode(): void
    {
        $serviceMock = $this->createServiceMock();

        $controller = $this->createController(
            $serviceMock,
            'POST',
            true,
            true
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::UUID_1],
            ],
        ];

        $response = $controller->checkBlueprint();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid payload format for blueprint verification.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests forbidden blueprint verification.
     *
     * @return void
     */
    public function testCheckBlueprintReturns403OnForbiddenException(): void
    {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('checkBlueprint')
            ->with(
                $this->isInstanceOf(MsgdUserDTO::class),
                $this->isInstanceOf(MsgdCheckBlueprintDTO::class)
            )
            ->willThrowException(new ForbiddenException('Access denied.'));

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::UUID_1],
            ],
        ];

        $response = $controller->checkBlueprint();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Access denied.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests internal errors during blueprint verification.
     *
     * @return void
     */
    public function testCheckBlueprintReturns500OnThrowable(): void
    {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('isUsingIds')
            ->willThrowException(new RuntimeException('Service failure.'));

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::UUID_1],
            ],
        ];

        $response = $controller->checkBlueprint();

        $this->assertSame(500, $response->statusCode());
        $this->assertSame(
            'Failed to verify blueprint existence.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests unauthorized access for group processing.
     *
     * @return void
     */
    public function testProcessGroupsReturns403WhenUnauthorized(): void
    {
        $serviceMock = $this->createServiceMock();

        $controller = $this->createController(
            $serviceMock,
            'POST',
            false
        );

        $response = $controller->processGroups();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Unauthorized access.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests invalid group processing payload handling.
     *
     * @return void
     */
    public function testProcessGroupsReturns400ForInvalidPayload(): void
    {
        $serviceMock = $this->createServiceMock();

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => 'invalid',
            ],
        ];

        $response = $controller->processGroups();

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(
            'Invalid payload format for processing groups.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests the missing result for single group processing.
     *
     * @param bool $useIds Identifier mode.
     * @param array<int, int|string> $groups Groups to process.
     *
     * @return void
     *
     * @dataProvider blueprintIdentifierProvider
     */
    public function testProcessGroupsSingleReturns404(
        bool $useIds,
        array $groups
    ): void {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('processSingleGroup')
            ->with(
                $this->isInstanceOf(MsgdUserDTO::class),
                $groups[0]
            )
            ->willReturn(null);

        $controller = $this->createController(
            $serviceMock,
            'POST',
            true,
            $useIds
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [$groups[0]],
            ],
        ];

        $response = $controller->processGroups();

        $this->assertSame(404, $response->statusCode());
        $this->assertSame(
            'Target sharing group could not be found.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests forbidden group processing.
     *
     * @return void
     */
    public function testProcessGroupsReturns403OnForbiddenException(): void
    {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('processSingleGroup')
            ->with(
                $this->isInstanceOf(MsgdUserDTO::class),
                self::UUID_1
            )
            ->willThrowException(new ForbiddenException('Access denied.'));

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::UUID_1],
            ],
        ];

        $response = $controller->processGroups();

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(
            'Access denied.',
            $this->decodeResponse($response)['message']
        );
    }

    /**
     * Tests internal errors during group processing.
     *
     * @return void
     */
    public function testProcessGroupsReturns500OnThrowable(): void
    {
        $serviceMock = $this->createServiceMock();

        $serviceMock->expects($this->once())
            ->method('isUsingIds')
            ->willThrowException(new RuntimeException('Service failure.'));

        $controller = $this->createController(
            $serviceMock,
            'POST'
        );

        $controller->request->data = [
            'MsgdPlug' => [
                'groups' => [self::UUID_1],
            ],
        ];

        $response = $controller->processGroups();

        $this->assertSame(500, $response->statusCode());
        $this->assertSame(
            'System error while processing blueprint.',
            $this->decodeResponse($response)['message']
        );
    }
}
