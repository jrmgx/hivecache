<?php

namespace App\Service;

use App\Entity\Bookmark;
use App\Entity\InstanceTag;
use App\Repository\InstanceTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

// TODO add tests
final readonly class InstanceTagService
{
    public function __construct(
        private InstanceTagRepository $instanceTagRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreate(string $name): InstanceTag
    {
        $slug = self::slugger($name);
        $instanceTag = $this->instanceTagRepository->findBySlug($slug);
        if (!$instanceTag) {
            $instanceTag = new InstanceTag();
            $instanceTag->name = $name;
            $this->entityManager->persist($instanceTag);
        }

        return $instanceTag;
    }

    public function synchronize(Bookmark $bookmark): void
    {
        $bookmark->instanceTags->clear();
        foreach ($bookmark->userTags as $userTag) {
            if (!$userTag->isPublic) {
                continue;
            }
            $bookmark->instanceTags->add($this->findOrCreate($userTag->name));
        }
    }

    public static function slugger(string $name): string
    {
        $slugger = new AsciiSlugger('en');

        return mb_strtolower($slugger->slug($name));
    }
}
