<?php

declare(strict_types=1);

/**
 * Represents the result of a single or multiple Sharing Group processing execution.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
readonly final class MsgdProcessResultDTO
{
    /**
     * @param bool $isNew
     * @param bool $hasBlueprint
     * @param int $sharingGroupId
     * @param string $sharingGroupName
     */
    public function __construct(
        public bool $isNew,
        public bool $hasBlueprint,
        public int $sharingGroupId,
        public string $sharingGroupName
    ) {
    }

    /**
     * Creates a DTO from the service result array.
     *
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isNew: (bool)($data['new'] ?? false),
            hasBlueprint: (bool)($data['has_blueprint'] ?? false),
            sharingGroupId: (int)($data['sharing_group_id'] ?? 0),
            sharingGroupName: MsgdSanitizerUtility::sanitizeString((string)($data['sharing_group_name'] ?? ''))
        );
    }
}
