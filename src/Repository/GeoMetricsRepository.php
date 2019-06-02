<?php

namespace App\Repository;

use App\Entity\GeoMetrics;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @method bool upsertGeoMetrics($geo_data)
 */
class GeoMetricsRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager, ClassMetadata $classMetadata)
    {
        parent::__construct($entityManager, $classMetadata);
    }

//
//    public function upsertGeoMetrics($geo_data)
//    {
//        $qb = $this->createQueryBuilder('p');
//
//        return $qb->select('p')
//            ->where('p.user IN (:following)')
//            ->setParameter('following', $users)
//            ->orderBy('p.time', 'DESC')
//            ->getQuery()
//            ->getResult()
//        ;
//    }

}
