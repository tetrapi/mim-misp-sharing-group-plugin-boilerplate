<?php

declare(strict_types=1);

/**
 * Represents a normalized Sharing Group Blueprint.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
class MsgdBlueprintDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly string $uuid = '',
        public string $name = '',
        public readonly int $userId = 0,
        public readonly int $orgId = 0,
        public int $sharingGroupId = 0,
        public readonly MsgdBlueprintRulesDTO $rules,
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

        $uuid = (string)($groupData['uuid'] ?? '');

        if ($uuid !== '' && !MsgdSanitizerUtility::isValidUuid($uuid)) {
            throw new InvalidArgumentException('Sharing Group Blueprint contains an invalid UUID.');
        }

        $name = MsgdSanitizerUtility::sanitizeString((string)($groupData['name'] ?? ''));

        return new self(
            id: (int)($groupData['id'] ?? 0),
            uuid: $uuid,
            name: $name,
            userId: (int)($groupData['user_id'] ?? 0),
            orgId: (int)($groupData['org_id'] ?? 0),
            sharingGroupId: (int)($groupData['sharing_group_id'] ?? 0),
            rules: new MsgdBlueprintRulesDTO($groupData['rules'] ?? []),
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
