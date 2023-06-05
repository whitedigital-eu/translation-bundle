<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use WhiteDigital\Translation\Entity\Translation;

use function array_column;
use function array_merge;

class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    public function findByLocale(string $locale)
    {
        return $this->createQueryBuilder('t', 't.key')
            ->andWhere('t.locale = :locale')
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    public function getDomains(): array
    {
        $result = $this->createQueryBuilder('t')
            ->distinct()
            ->select('t.domain')
            ->where('t.isActive = true')
            ->getQuery()
            ->getResult();

        return array_merge(array_column($result, 'domain'));
    }
}
