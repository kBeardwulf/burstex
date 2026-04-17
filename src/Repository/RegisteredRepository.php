<?php

namespace App\Repository;

use App\Entity\Registered;
use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registered>
 */
class RegisteredRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registered::class);
    }

    public function findStaffByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->andWhere('r.role IN (:roles)')
            ->setParameter('tournament', $tournament)
            ->setParameter('roles', ['admin', 'org'])
            ->getQuery()
            ->getResult();
    }
}
