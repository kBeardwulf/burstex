<?php

namespace App\Repository;

use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }


    public function searchTournaments(
        ?string $value = null, ?string $dateFrom = null, ?string $dateTo = null,
        ?string $maxNb = null, ?string $upcoming = null, ?string $type = null 
        ): array {
        
        $queryBuilder = $this->createQueryBuilder('t')->orderBy('t.date', 'DESC');

        if($type === 'user' && $value) {
            $queryBuilder
                ->leftJoin('t.createdBy', 'u')
                ->andWhere('LOWER(u.pseudo) LIKE LOWER(:value)')
                ->setParameter('value', '%' . $value . '%');

        } elseif($value) {
            $queryBuilder
                ->leftJoin('t.Location', 'town')
                ->andWhere('LOWER(t.name) LIKE LOWER(:value) 
                    OR LOWER(t.discipline) LIKE LOWER(:value) 
                    OR LOWER(town.name) LIKE LOWER(:value)
                    OR LOWER(town.postalcode) LIKE LOWER(:value)')
                ->setParameter('value', '%' . $value . '%');
        } 

        if($dateFrom) {
            $queryBuilder
                ->andWhere('t.date >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if($dateTo) {
            $queryBuilder
                ->andWhere('t.date <= :dateTo')
                ->setParameter('dateTo', new \DateTime($dateTo));
        }

        if($upcoming) {
            $queryBuilder
                ->andWhere('t.date >= :now')
                ->setParameter('now', new \DateTime('now'));
        }

        if($maxNb) {
            $queryBuilder
                ->andWhere('t.maxNb = :maxNb')
                ->setParameter('maxNb', $maxNb);
        }

        return $queryBuilder->getQuery()->getResult();

    }
    //    /**
    //     * @return Tournament[] Returns an array of Tournament objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tournament
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
