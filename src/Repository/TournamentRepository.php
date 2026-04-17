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


    public function findbyTheSearchedValue(?string $value) {
        
        $queryBuilder = $this->createQueryBuilder('t')->orderBy('t.date', 'DESC')
            ->andWhere('t.date >= :now')
            ->setParameter('now', new \DateTime('now'));

        if($value) {
        $queryBuilder
            ->andWhere('LOWER(t.name) LIKE LOWER(:value) 
                OR LOWER(t.discipline) LIKE LOWER(:value) 
                OR LOWER(t.address) LIKE LOWER(:value)')
            ->setParameter('value', '%' . $value . '%');
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
