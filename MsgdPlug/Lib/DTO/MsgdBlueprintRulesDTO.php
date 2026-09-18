<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents normalized Sharing Group Blueprint rules.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
readonly final class MsgdBlueprintRulesDTO
{
    /**
     * @var array<string, mixed>
     */
    public array $raw;

    /**
     * @var array<int, int>
     */
    public array $sharingGroupsIds;

    /**
     * @var array<int, string>
     */
    public array $sharingGroupsUuids;

    /**
     * @var array<int, int|string>
     */
    public array $allSharingGroupIdentifiers;

    /**
     * @param string|array<string, mixed> $rules
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string|array $rules)
    {
        if (is_string($rules)) {
            try {
                $parsed = json_decode($rules, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException(
                    sprintf('Blueprint rules contain invalid JSON: %s', $exception->getMessage()),
                    0,
                    $exception
                );
            }
        } else {
            $parsed = $rules;
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException('Blueprint rules must be a valid JSON object.');
        }

        /** @var array<string, mixed> $parsed */
        $this->raw = $parsed;

        $andConditions = $parsed['AND'] ?? [];
        $andConditions = is_array($andConditions) ? $andConditions : [];

        $orConditions = $andConditions['OR'] ?? [];
        $orConditions = is_array($orConditions) ? $orConditions : [];

        $ids = $orConditions['sharing_group_id'] ?? [];
        $uuids = $orConditions['sharing_group_uuid'] ?? [];

        $this->sharingGroupsIds = self::cleanIds(is_array($ids) ? $ids : [$ids]);
        $this->sharingGroupsUuids = self::cleanUuids(is_array($uuids) ? $uuids : [$uuids]);

        $this->allSharingGroupIdentifiers = array_values(
            array_unique(
                array_merge($this->sharingGroupsIds, $this->sharingGroupsUuids),
                SORT_REGULAR
            )
        );
    }

    /**
     * Creates blueprint rules from Sharing Group identifiers.
     *
     * @param array<int, mixed> $identifiers
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function fromIdentifiers(array $identifiers): self
    {
        $ids = [];
        $uuids = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier) && $identifier > 0) {
                $ids[] = $identifier;
                continue;
            }

            if (!is_string($identifier)) {
                continue;
            }

            $identifier = trim($identifier);

            if ($identifier === '') {
                continue;
            }

            if (ctype_digit($identifier) && (int)$identifier > 0) {
                $ids[] = (int)$identifier;
                continue;
            }

            if (MsgdSanitizerUtility::isValidUuid($identifier)) {
                $uuids[] = $identifier;
            }
        }

        $ids = array_values(array_unique($ids));
        $uuids = array_values(array_unique($uuids));

        $orConditions = [];

        if ($ids !== []) {
            $orConditions['sharing_group_id'] = count($ids) === 1 ? $ids[0] : $ids;
        }

        if ($uuids !== []) {
            $orConditions['sharing_group_uuid'] = count($uuids) === 1 ? $uuids[0] : $uuids;
        }

        return new self(['AND' => ['OR' => $orConditions]]);
    }

    /**
     * Returns the raw MISP rules array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * Returns rules as JSON for the MISP model.
     *
     * @return string
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->raw, JSON_THROW_ON_ERROR);
    }

    /**
     * Normalizes Sharing Group IDs.
     *
     * @param array<int, mixed> $items
     *
     * @return array<int, int>
     */
    private static function cleanIds(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_int($item) && $item > 0) {
                $result[] = $item;
                continue;
            }

            if (is_string($item) && ctype_digit($item) && (int)$item > 0) {
                $result[] = (int)$item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Normalizes and validates Sharing Group UUIDs.
     *
     * @param array<int, mixed> $items
     *
     * @return array<int, string>
     */
    private static function cleanUuids(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_string($item) && MsgdSanitizerUtility::isValidUuid(trim($item))) {
                $result[] = trim($item);
            }
        }

        return array_values(array_unique($result));
    }
}
