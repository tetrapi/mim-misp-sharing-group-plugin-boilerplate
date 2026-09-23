<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents a normalized Sharing Group Blueprint.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
class MsgdBlueprintDTO
{
    /**
     * @param MsgdBlueprintRulesDTO $rules
     * @param int $id
     * @param string $uuid
     * @param string $name
     * @param int $userId
     * @param int $orgId
     * @param int $sharingGroupId
     */
    public function __construct(
        public readonly MsgdBlueprintRulesDTO $rules,
        public readonly int $id = 0,
        public readonly string $uuid = '',
        public string $name = '',
        public readonly int $userId = 0,
        public readonly int $orgId = 0,
        public int $sharingGroupId = 0,
    ) {
    }

    /**
     * Creates a DTO from a MISP SharingGroupBlueprint record.
     *
     * @param array<string, mixed> $data
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $groupData = $data['SharingGroupBlueprint'] ?? $data;

        if (!is_array($groupData)) {
            throw new InvalidArgumentException('Sharing Group Blueprint data must be an array.');
        }

        $rawUuid = $groupData['uuid'] ?? '';
        $uuid = is_scalar($rawUuid) ? (string)$rawUuid : '';

        if ($uuid !== '' && !MsgdSanitizerUtility::isValidUuid($uuid)) {
            throw new InvalidArgumentException('Sharing Group Blueprint contains an invalid UUID.');
        }

        $rawName = $groupData['name'] ?? '';
        $name = MsgdSanitizerUtility::sanitizeString(is_scalar($rawName) ? (string)$rawName : '');

        $rawId = $groupData['id'] ?? 0;
        $id = is_numeric($rawId) ? (int)$rawId : 0;

        $rawUserId = $groupData['user_id'] ?? 0;
        $userId = is_numeric($rawUserId) ? (int)$rawUserId : 0;

        $rawOrgId = $groupData['org_id'] ?? 0;
        $orgId = is_numeric($rawOrgId) ? (int)$rawOrgId : 0;

        $rawSharingGroupId = $groupData['sharing_group_id'] ?? 0;
        $sharingGroupId = is_numeric($rawSharingGroupId) ? (int)$rawSharingGroupId : 0;

        $rawRules = $groupData['rules'] ?? [];
        if (!is_array($rawRules) && !is_string($rawRules)) {
            $rawRules = [];
        }

        /** @var array<string, mixed>|string $rules */
        $rules = $rawRules;

        return new self(
            rules: MsgdBlueprintRulesDTO::fromArray($rules),
            id: $id,
            uuid: $uuid,
            name: $name,
            userId: $userId,
            orgId: $orgId,
            sharingGroupId: $sharingGroupId,
        );
    }

    /**
     * Converts this DTO into the array expected by MISP.
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws JsonException
     */
    public function toModelArray(): array
    {
        return [
            'SharingGroupBlueprint' => [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'name' => $this->name,
                'user_id' => $this->userId,
                'org_id' => $this->orgId,
                'sharing_group_id' => $this->sharingGroupId,
                'rules' => $this->rules->toJson(),
            ],
        ];
    }
}
