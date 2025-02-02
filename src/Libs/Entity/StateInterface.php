<?php

declare(strict_types=1);

namespace App\Libs\Entity;

interface StateInterface
{
    public const TYPE_MOVIE = 'movie';
    public const TYPE_EPISODE = 'episode';

    public const ENTITY_KEYS = [
        'id',
        'type',
        'updated',
        'watched',
        'meta',
        'guid_plex',
        'guid_imdb',
        'guid_tvdb',
        'guid_tmdb',
        'guid_tvmaze',
        'guid_tvrage',
        'guid_anidb',
    ];

    public const ENTITY_GUIDS = [
        'guid_plex',
        'guid_imdb',
        'guid_tvdb',
        'guid_tmdb',
        'guid_tvmaze',
        'guid_tvrage',
        'guid_anidb',
    ];

    /**
     * Make new instance.
     *
     * @param array $data
     *
     * @return StateInterface
     */
    public static function fromArray(array $data): self;

    /**
     * Return An array of changed items.
     *
     * @return array
     */
    public function diff(): array;

    /**
     * Get All Entity keys.
     *
     * @return array
     */
    public function getAll(): array;

    /**
     * Has the entity changed?
     *
     * @return bool
     */
    public function isChanged(): bool;

    /**
     * Does the entity have GUIDs?
     *
     * @return bool
     */
    public function hasGuids(): bool;

    /**
     * Get GUID Pointers.
     *
     * @return array
     */
    public function getPointers(): array;

    /**
     * Apply changes to entity.
     *
     * @param StateInterface $entity
     * @param bool $guidOnly
     *
     * @return StateInterface
     */
    public function apply(StateInterface $entity, bool $guidOnly = false): StateInterface;

    public function updateOriginal(): StateInterface;

    /**
     * The Tainted flag control whether we will change state or not.
     * If the entity is not already stored in the database, then this flag is not used.
     * However, if the entity already exists and the flag is set to **true**, then
     * we will be checking  **GUIDs** only, and if those differ then meta will be updated as well.
     * otherwise, nothing will be changed, This flag serve to update GUIDs via webhook unhelpful events like
     * media.play/stop/resume,
     *
     * @param bool $isTainted
     *
     * @return StateInterface
     */
    public function setIsTainted(bool $isTainted): StateInterface;

    /**
     * Whether the play state is tainted.
     *
     * @return bool
     */
    public function isTainted(): bool;
}
