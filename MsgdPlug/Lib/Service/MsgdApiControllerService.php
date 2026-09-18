<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Service for sharing group and blueprint operations.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Service
 */
class MsgdApiControllerService
{
    /**
     * @var MsgdSharingGroupService
     */
    private MsgdSharingGroupService $sgLib;

    /**
     * @var MsgdBlueprintService
     */
    private MsgdBlueprintService $bpLib;

    /**
     * Initializes service dependencies.
     *
     * @param MsgdSharingGroupService|null $sgLib
     * @param MsgdBlueprintService|null $bpLib
     */
    public function __construct(
        ?MsgdSharingGroupService $sgLib = null,
        ?MsgdBlueprintService $bpLib = null
    ) {
        $this->sgLib = $sgLib ?? new MsgdSharingGroupService();
        $this->bpLib = $bpLib ?? new MsgdBlueprintService();
    }

    /**
     * Returns whether the plugin user white list is configured.
     *
     * @return string
     */
    public function isUserWhiteList(): string
    {
        $rawWhitelist = Configure::read(
            MsgdPluginConfigEnum::USER_PERMISSIONS_WHITELIST->value
        );

        return is_scalar($rawWhitelist) ? (string) $rawWhitelist : '';
    }

    /**
     * Returns whether the plugin is configured to use numeric IDs.
     *
     * @return bool True if numeric IDs are configured, false if UUIDs are used.
     */
    public function isUsingIds(): bool
    {
        return (bool)Configure::read(MsgdPluginConfigEnum::USE_IDS->value);
    }

    /**
     * Checks if the current user has permission to use Sharing Group Blueprints.
     *
     * @param MsgdUserDTO $user
     * @param bool $exception
     *
     * @return bool
     *
     * @throws ForbiddenException
     */
    public function hasSharingGroupAccess(MsgdUserDTO $user, bool $exception = true): bool
    {
        if ($user->isSiteAdmin || $user->canUseSharingGroups) {
            return true;
        }

        $userEmail = strtolower(trim($user->email));
        $whitelistConfig = $this->isUserWhiteList();

        if ($whitelistConfig !== '') {
            $allowedList = array_filter(
                array_map(
                    static fn(string $value): string => strtolower(trim($value)),
                    explode(',', $whitelistConfig)
                ),
                static fn(string $item): bool => $item !== ''
            );

            if (in_array('*', $allowedList, true) || ($userEmail !== '' && in_array($userEmail, $allowedList, true))) {
                return true;
            }
        }

        if ($exception) {
            throw new ForbiddenException('You do not have permission to use this functionality.');
        }

        return false;
    }

    /**
     * Resolves sharing groups linked to a blueprint associated with a target group.
     *
     * @param MsgdUserDTO $user
     * @param int $sharingGroupId
     *
     * @return array<int, MsgdSharingGroupDTO>
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function getSharingGroupsByGeneratedBlueprintGroup(
        MsgdUserDTO $user,
        int $sharingGroupId
    ): array {
        $targetSharingGroup = $this->sgLib->findById($user, $sharingGroupId);

        if (!$targetSharingGroup instanceof MsgdSharingGroupDTO) {
            return [];
        }

        $matchedBlueprint = $this->bpLib->findBySharingGroupId($sharingGroupId);

        if ($matchedBlueprint instanceof MsgdBlueprintDTO) {
            if (empty($matchedBlueprint->rules->allSharingGroupIdentifiers)) {
                return [];
            }

            $uuids = $matchedBlueprint->rules->sharingGroupsUuids;
            $ids = $matchedBlueprint->rules->sharingGroupsIds;
        } else {
            $ids = [$targetSharingGroup->id];
            $uuids = [];
        }

        $queryConditions = [];

        if (!empty($uuids) && !empty($ids)) {
            $queryConditions['OR'] = [
                'SharingGroup.uuid' => $uuids,
                'SharingGroup.id' => $ids,
            ];
        } elseif (!empty($uuids)) {
            $queryConditions['SharingGroup.uuid'] = $uuids;
        } else {
            $queryConditions['SharingGroup.id'] = $ids;
        }

        return $this->sgLib->getList($user, $queryConditions);
    }

    /**
     * Retrieves available sharing groups.
     *
     * @param MsgdUserDTO $user
     * @param bool $all If false, ignore blueprint generated groups.
     *
     * @return array<int, MsgdSharingGroupDTO>
     *
     * @throws RuntimeException
     */
    public function getAvailableSharingGroups(MsgdUserDTO $user, bool $all = false): array
    {
        $queryConditions = [];

        if (!$all) {
            $excludedSharingGroupIds = $this->bpLib->getGeneratedGroups();

            if (!empty($excludedSharingGroupIds)) {
                $queryConditions['NOT'] = ['SharingGroup.id' => $excludedSharingGroupIds];
            }
        }

        return $this->sgLib->getList($user, $queryConditions);
    }

    /**
     * Resolves a single sharing group details by UUID or ID.
     *
     * @param MsgdUserDTO $user
     * @param string|int $identifier
     *
     * @return MsgdProcessResultDTO|null
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function processSingleGroup(MsgdUserDTO $user, string|int $identifier): ?MsgdProcessResultDTO
    {
        if (MsgdSanitizerUtility::isValidUuid((string)$identifier)) {
            $targetSharingGroup = $this->sgLib->findByUuid($user, (string)$identifier);
        } elseif (is_numeric($identifier) && (int)$identifier > 0) {
            $targetSharingGroup = $this->sgLib->findById($user, (int)$identifier);
        } else {
            return null;
        }

        if ($targetSharingGroup instanceof MsgdSharingGroupDTO) {
            return new MsgdProcessResultDTO(
                isNew: false,
                hasBlueprint: false,
                sharingGroupId: $targetSharingGroup->id,
                sharingGroupName: MsgdSanitizerUtility::sanitizeString($targetSharingGroup->name),
            );
        }

        return null;
    }

    /**
     * Checks if a blueprint already exists matching the given UUID/ID set.
     *
     * @param MsgdUserDTO $user
     * @param MsgdCheckBlueprintDTO $payload
     *
     * @return bool
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function checkBlueprint(MsgdUserDTO $user, MsgdCheckBlueprintDTO $payload): bool
    {
        $mirrors = $this->sgLib->getMirrorGroups($user, $payload->groups);

        if (empty($mirrors)) {
            return false;
        }

        return $this->bpLib->findBySharingGroupRules($user, $mirrors) !== null;
    }

    /**
     * Orchestrates blueprint creation, repair, and execution.
     *
     * @param MsgdUserDTO $user
     * @param MsgdProcessGroupsDTO $payload
     *
     * @return MsgdProcessResultDTO
     *
     * @throws RuntimeException|InvalidArgumentException|Throwable|ForbiddenException
     */
    public function processMultiple(
        MsgdUserDTO $user,
        MsgdProcessGroupsDTO $payload
    ): MsgdProcessResultDTO {
        $hasPermission = $this->hasSharingGroupAccess($user, false);
        $mirrors = $this->sgLib->getMirrorGroups($user, $payload->groups);
        $matchedExistingBlueprint = $mirrors !== null
            ? $this->bpLib->findBySharingGroupRules($user, $mirrors)
            : null;

        $isNew = true;
        $associatedSharingGroupId = 0;

        $databaseTransaction = $this->bpLib->getDataSource();

        $databaseTransaction->begin();

        try {
            if ($matchedExistingBlueprint !== null) {
                $targetBlueprintId = $matchedExistingBlueprint->id;
                $associatedSharingGroupId = $matchedExistingBlueprint->sharingGroupId;
                $associatedSharingGroupRecord = $this->sgLib->findById($user, $associatedSharingGroupId);

                if (!$associatedSharingGroupRecord instanceof MsgdSharingGroupDTO) {
                    if (!$hasPermission) {
                        throw new ForbiddenException('You do not have permission to use this functionality.');
                    }

                    $this->bpLib->resetSharingGroupRef($user, $matchedExistingBlueprint, $payload->customName);
                } else {
                    $isNew = false;
                }
            } else {
                if (!$hasPermission) {
                    throw new ForbiddenException('You do not have permission to use this functionality.');
                }

                $targetBlueprintId = $this->bpLib->create($user, $payload);
            }

            if ($hasPermission) {
                $executedSharingGroupId = $this->bpLib->execute($targetBlueprintId);
            }

            $targetGroupIdToFetch = $executedSharingGroupId ?? $associatedSharingGroupId;
            $updatedSharingGroupRecord = $targetGroupIdToFetch > 0
                ? $this->sgLib->findById($user, $targetGroupIdToFetch)
                : null;

            if (!$updatedSharingGroupRecord instanceof MsgdSharingGroupDTO) {
                throw new RuntimeException(
                    'Blueprint execution or lookup returned an invalid ID, Sharing Group record not found.'
                );
            }

            $databaseTransaction->commit();

            return new MsgdProcessResultDTO(
                isNew: $isNew,
                hasBlueprint: true,
                sharingGroupId: $updatedSharingGroupRecord->id,
                sharingGroupName: $updatedSharingGroupRecord->name,
            );
        } catch (Throwable $exception) {
            $databaseTransaction->rollback();
            MsgdLoggerUtility::logException(
                $exception,
                '[MsgdPlug Service: processMultiple] Transaction failed'
            );
            throw $exception;
        }
    }
}
