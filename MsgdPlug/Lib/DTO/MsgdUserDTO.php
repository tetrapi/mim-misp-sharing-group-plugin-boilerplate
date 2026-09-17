<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents a normalized MISP User entity.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
readonly final class MsgdUserDTO
{
    /**
     * @param int $id
     * @param int $orgId
     * @param string $email
     * @param string|null $orgName
     * @param string|null $orgUuid
     * @param bool $isSiteAdmin
     * @param bool $canUseSharingGroups
     * @param bool $canSync
     * @param bool $disabled
     */
    public function __construct(
        public int $id,
        public int $orgId,
        public string $email,
        public ?string $orgName,
        public ?string $orgUuid,
        public bool $isSiteAdmin,
        public bool $canUseSharingGroups,
        public bool $canSync,
        public bool $disabled
    ) {
    }

    /**
     * Creates a normalized user DTO from the authenticated MISP user.
     *
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $role = is_array($data['Role'] ?? null)
            ? $data['Role']
            : [];

        $organisation = is_array($data['Organisation'] ?? null)
            ? $data['Organisation']
            : [];

        return new self(
            id: (int)($data['id'] ?? 0),
            orgId: (int)($data['org_id'] ?? 0),
            email: MsgdSanitizerUtility::sanitizeString((string)($data['email'] ?? '')),
            orgName: MsgdSanitizerUtility::sanitizeString((string)($organisation['name'] ?? '')),
            orgUuid: MsgdSanitizerUtility::sanitizeString((string)($organisation['uuid'] ?? '')),
            isSiteAdmin: (bool)($role['perm_site_admin'] ?? false),
            canUseSharingGroups: (bool)($role['perm_sharing_group'] ?? false),
            canSync: (bool)($role['perm_sync'] ?? false),
            disabled: (bool)($data['disabled'] ?? false)
        );
    }

    /**
     * Converts the DTO into the normalized user structure expected
     * by plugin services and native MISP model methods.
     *
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        return [
            'id' => $this->id,
            'org_id' => $this->orgId,
            'email' => $this->email,
            'disabled' => $this->disabled,
            'Role' => [
                'perm_site_admin' => $this->isSiteAdmin,
                'perm_sharing_group' => $this->canUseSharingGroups,
                'perm_sync' => $this->canSync,
            ],
            'Organisation' => [
                'id' => $this->orgId,
                'name' => $this->orgName,
                'uuid' => $this->orgUuid,
            ],
        ];
    }
}
