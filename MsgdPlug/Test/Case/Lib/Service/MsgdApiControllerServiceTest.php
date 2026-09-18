<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/Lib/Utility/MsgdSanitizerUtility.php';
require_once dirname(__DIR__, 4) . '/Lib/Utility/MsgdLoggerUtility.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdUserDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdSharingGroupDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdBlueprintDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdBlueprintRulesDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdMirrorGroupsDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdProcessGroupsDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdCheckBlueprintDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/DTO/MsgdProcessResultDTO.php';
require_once dirname(__DIR__, 4) . '/Lib/Enum/MsgdPluginConfigEnum.php';
require_once dirname(__DIR__, 4) . '/Lib/Service/MsgdSharingGroupService.php';
require_once dirname(__DIR__, 4) . '/Lib/Service/MsgdBlueprintService.php';
require_once dirname(__DIR__, 4) . '/Lib/Service/MsgdApiControllerService.php';

App::uses('DataSource', 'Model/Datasource');

/**
 * Tests for the API controller service.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Test.Case.Lib.Service
 */
final class MsgdApiControllerServiceTest extends TestCase
{
    private const UUID_1 = '11111111-1111-4111-8111-111111111111';
    private const UUID_2 = '22222222-2222-4222-8222-222222222222';

    /**
     * Creates a test user DTO.
     *
     * @param bool $isSiteAdmin
     * @param bool $canUseSharingGroups
     *
     * @return MsgdUserDTO
     */
    private function createUser(
        bool $isSiteAdmin = false,
        bool $canUseSharingGroups = true
    ): MsgdUserDTO {
        return MsgdUserDTO::fromArray([
            'id' => 1,
            'org_id' => 10,
            'email' => 'user@example.com',
            'Role' => [
                'perm_site_admin' => $isSiteAdmin,
                'perm_sharing_group' => $canUseSharingGroups,
            ],
            'Organisation' => [
                'id' => 10,
                'name' => 'Test Organisation',
                'uuid' => self::UUID_1,
            ],
        ]);
    }

    /**
     * Creates a sharing group DTO.
     *
     * @param int $id
     *
     * @return MsgdSharingGroupDTO
     */
    private function createSharingGroup(
        int $id
    ): MsgdSharingGroupDTO {
        return new MsgdSharingGroupDTO($id, self::UUID_1, 'Test Group');
    }

    /**
     * Creates a blueprint DTO.
     *
     * @param int $sharingGroupId
     * @param array<int, string|int> $identifiers
     *
     * @return MsgdBlueprintDTO
     */
    private function createBlueprint(
        int $sharingGroupId,
        array $identifiers = []
    ): MsgdBlueprintDTO {
        return new MsgdBlueprintDTO(
            rules: MsgdBlueprintRulesDTO::fromIdentifiers($identifiers),
            id: 5,
            uuid: self::UUID_1,
            name: 'Test Blueprint',
            userId: 1,
            orgId: 10,
            sharingGroupId: $sharingGroupId
        );
    }

    /**
     * Creates the API controller service with mocked dependencies.
     *
     * @param MsgdSharingGroupService|null $sgLib
     * @param MsgdBlueprintService|null $bpLib
     *
     * @return MsgdApiControllerService
     */
    private function createService(
        ?MsgdSharingGroupService $sgLib = null,
        ?MsgdBlueprintService $bpLib = null
    ): MsgdApiControllerService {
        return new MsgdApiControllerService(
            $sgLib ?? $this->createMock(MsgdSharingGroupService::class),
            $bpLib ?? $this->createMock(MsgdBlueprintService::class)
        );
    }

    /**
     * Tests the configured user whitelist.
     *
     * @return void
     */
    public function testIsUserWhiteList(): void
    {
        Configure::write(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value,
            'user@example.com'
        );

        $this->assertSame(
            'user@example.com',
            $this->createService()->isUserWhiteList()
        );
    }

    /**
     * Tests the configured identifier mode.
     *
     * @return void
     */
    public function testIsUsingIds(): void
    {
        Configure::write(MsgdPluginConfigEnum::USE_IDS->value, true);

        $this->assertTrue($this->createService()->isUsingIds());
    }

    /**
     * Tests access for an administrator.
     *
     * @return void
     */
    public function testHasSharingGroupAccessForSiteAdmin(): void
    {
        $this->assertTrue(
            $this->createService()->hasSharingGroupAccess(
                $this->createUser(true)
            )
        );
    }

    /**
     * Tests access for a sharing group user.
     *
     * @return void
     */
    public function testHasSharingGroupAccessForSharingGroupUser(): void
    {
        $this->assertTrue(
            $this->createService()->hasSharingGroupAccess(
                $this->createUser()
            )
        );
    }

    /**
     * Tests access through the whitelist.
     *
     * @return void
     */
    public function testHasSharingGroupAccessForWhitelistedUser(): void
    {
        $user = $this->createUser(false, false);

        Configure::write(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value,
            'allowed@example.com'
        );

        $user = new MsgdUserDTO(
            id: $user->id,
            orgId: $user->orgId,
            email: 'allowed@example.com',
            orgName: $user->orgName,
            orgUuid: $user->orgUuid,
            isSiteAdmin: false,
            canUseSharingGroups: false,
            canSync: false,
            disabled: false
        );

        $this->assertTrue(
            $this->createService()->hasSharingGroupAccess($user)
        );
    }

    /**
     * Tests wildcard whitelist access.
     *
     * @return void
     */
    public function testHasSharingGroupAccessForWildcardWhitelist(): void
    {
        $user = $this->createUser(false, false);

        Configure::write(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value,
            '*'
        );

        $this->assertTrue(
            $this->createService()->hasSharingGroupAccess($user)
        );
    }

    /**
     * Tests denied access without exception.
     *
     * @return void
     */
    public function testHasSharingGroupAccessReturnsFalse(): void
    {
        Configure::write(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value,
            ''
        );

        $this->assertFalse(
            $this->createService()->hasSharingGroupAccess(
                $this->createUser(false, false),
                false
            )
        );
    }

    /**
     * Tests denied access with exception.
     *
     * @return void
     */
    public function testHasSharingGroupAccessThrowsForbiddenException(): void
    {
        Configure::write(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value,
            ''
        );

        $this->expectException(ForbiddenException::class);

        $this->createService()->hasSharingGroupAccess(
            $this->createUser(false, false)
        );
    }

    /**
     * Tests sharing groups generated by a blueprint.
     *
     * @return void
     */
    public function testGetSharingGroupsByGeneratedBlueprintGroup(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $target = $this->createSharingGroup(10);
        $blueprint = $this->createBlueprint(10, [20, self::UUID_2]);

        $sgLib->method('findById')->willReturn($target);
        $bpLib->method('findBySharingGroupId')->willReturn($blueprint);
        $sgLib->method('getList')->willReturn([$target]);

        $result = $this->createService($sgLib, $bpLib)
            ->getSharingGroupsByGeneratedBlueprintGroup(
                $this->createUser(),
                10
            );

        $this->assertCount(1, $result);
    }

    /**
     * Tests the fallback sharing group lookup.
     *
     * @return void
     */
    public function testGetSharingGroupsByGeneratedBlueprintGroupFallback(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $target = $this->createSharingGroup(10);

        $sgLib->method('findById')->willReturn($target);
        $bpLib->method('findBySharingGroupId')->willReturn(null);
        $sgLib->method('getList')->willReturn([$target]);

        $result = $this->createService($sgLib, $bpLib)
            ->getSharingGroupsByGeneratedBlueprintGroup(
                $this->createUser(),
                10
            );

        $this->assertCount(1, $result);
    }

    /**
     * Tests available sharing groups.
     *
     * @return void
     */
    public function testGetAvailableSharingGroups(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $groups = [$this->createSharingGroup(10)];

        $bpLib->method('getGeneratedGroups')->willReturn([20]);
        $sgLib->method('getList')->willReturn($groups);

        $result = $this->createService($sgLib, $bpLib)
            ->getAvailableSharingGroups($this->createUser());

        $this->assertSame($groups, $result);
    }

    /**
     * Tests all available sharing groups.
     *
     * @return void
     */
    public function testGetAvailableSharingGroupsReturnsAll(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $groups = [$this->createSharingGroup(10)];

        $sgLib->method('getList')->willReturn($groups);

        $result = $this->createService($sgLib, $bpLib)
            ->getAvailableSharingGroups($this->createUser(), true);

        $this->assertSame($groups, $result);
    }

    /**
     * Tests processing a group by UUID.
     *
     * @return void
     */
    public function testProcessSingleGroupByUuid(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $group = $this->createSharingGroup(10);

        $sgLib->method('findByUuid')->willReturn($group);

        $result = $this->createService($sgLib)
            ->processSingleGroup($this->createUser(), self::UUID_1);

        assert($result instanceof MsgdProcessResultDTO);
        $this->assertSame(10, $result->sharingGroupId);
    }

    /**
     * Tests processing a group by ID.
     *
     * @return void
     */
    public function testProcessSingleGroupById(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $group = $this->createSharingGroup(10);

        $sgLib->method('findById')->willReturn($group);

        $result = $this->createService($sgLib)
            ->processSingleGroup($this->createUser(), 10);

        assert($result instanceof MsgdProcessResultDTO);
        $this->assertSame(10, $result->sharingGroupId);
    }

    /**
     * Tests invalid single group identifiers.
     *
     * @return void
     */
    public function testProcessSingleGroupReturnsNullForInvalidIdentifier(): void
    {
        $this->assertNull(
            $this->createService()->processSingleGroup(
                $this->createUser(),
                'invalid'
            )
        );
    }

    /**
     * Tests blueprint matching.
     *
     * @return void
     */
    public function testCheckBlueprint(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $sgLib->method('getMirrorGroups')
            ->willReturn(new MsgdMirrorGroupsDTO(ids: [10]));

        $bpLib->method('findBySharingGroupRules')
            ->willReturn($this->createBlueprint(20, [10]));

        $payload = new MsgdCheckBlueprintDTO(groups: [10]);

        $this->assertTrue(
            $this->createService($sgLib, $bpLib)
                ->checkBlueprint($this->createUser(), $payload)
        );
    }

    /**
     * Tests processMultiple without a datasource.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function testProcessMultipleRequiresDatasource(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);

        $sgLib->method('getMirrorGroups')
            ->willReturn(new MsgdMirrorGroupsDTO());

        $bpLib->method('findBySharingGroupRules')
            ->willReturn(null);

        $bpLib->method('getDataSource')
            ->willReturn(null);

        $payload = new MsgdProcessGroupsDTO(
            groups: [10],
            customName: 'Test'
        );

        $this->expectException(RuntimeException::class);

        $this->createService($sgLib, $bpLib)
            ->processMultiple($this->createUser(), $payload);
    }

    /**
     * Tests creation of a new blueprint.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function testProcessMultipleCreatesBlueprint(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);
        $dataSource = $this->createMock(DataSource::class);

        $group = $this->createSharingGroup(20);

        $sgLib->method('getMirrorGroups')
            ->willReturn(new MsgdMirrorGroupsDTO(ids: [10]));

        $sgLib->method('findById')->willReturn($group);

        $bpLib->method('findBySharingGroupRules')->willReturn(null);
        $bpLib->method('getDataSource')->willReturn($dataSource);
        $bpLib->method('create')->willReturn(5);
        $bpLib->method('execute')->willReturn(20);

        $dataSource->expects($this->once())->method('begin');
        $dataSource->expects($this->once())->method('commit');

        $payload = new MsgdProcessGroupsDTO(
            groups: [10],
            customName: 'Test'
        );

        $result = $this->createService($sgLib, $bpLib)
            ->processMultiple($this->createUser(), $payload);

        $this->assertSame(20, $result->sharingGroupId);
    }

    /**
     * Tests updating an existing blueprint.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function testProcessMultipleUpdatesExistingBlueprint(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);
        $dataSource = $this->createMock(DataSource::class);

        $blueprint = $this->createBlueprint(20, [10]);
        $group = $this->createSharingGroup(20);

        $sgLib->method('getMirrorGroups')
            ->willReturn(new MsgdMirrorGroupsDTO(ids: [10]));

        $sgLib->method('findById')->willReturn($group);

        $bpLib->method('findBySharingGroupRules')->willReturn($blueprint);
        $bpLib->method('getDataSource')->willReturn($dataSource);
        $bpLib->method('execute')->willReturn(20);

        $dataSource->expects($this->once())->method('begin');
        $dataSource->expects($this->once())->method('commit');

        $payload = new MsgdProcessGroupsDTO(
            groups: [10],
            customName: 'Test'
        );

        $result = $this->createService($sgLib, $bpLib)
            ->processMultiple($this->createUser(), $payload);

        $this->assertFalse($result->isNew);
        $this->assertSame(20, $result->sharingGroupId);
    }

    /**
     * Tests rollback when processing fails.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function testProcessMultipleRollsBackOnFailure(): void
    {
        $sgLib = $this->createMock(MsgdSharingGroupService::class);
        $bpLib = $this->createMock(MsgdBlueprintService::class);
        $dataSource = $this->createMock(DataSource::class);

        $sgLib->method('getMirrorGroups')
            ->willReturn(new MsgdMirrorGroupsDTO(ids: [10]));

        $bpLib->method('findBySharingGroupRules')->willReturn(null);
        $bpLib->method('getDataSource')->willReturn($dataSource);
        $bpLib->method('create')
            ->willThrowException(new RuntimeException('Failure'));

        $dataSource->expects($this->once())->method('begin');
        $dataSource->expects($this->once())->method('rollback');

        $payload = new MsgdProcessGroupsDTO(
            groups: [10],
            customName: 'Test'
        );

        $this->expectException(RuntimeException::class);

        $this->createService($sgLib, $bpLib)
            ->processMultiple($this->createUser(), $payload);
    }
}
