<?php

namespace App\DataTransferObjects;

readonly class ProfileData
{
    public function __construct(
        public string $username,
        public ?string $bio,
        public ?string $profilePictureUrl,
        public int $followersCount,
        public int $followingCount,
        public int $postsCount,
    ) {}
}
