<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\GeoMetricsRepository")
 * @ORM\Table(name="geo_metrics", uniqueConstraints={@ORM\UniqueConstraint(name="geo_unique", columns={"date", "geo", "zone"})})
 */
class GeoMetrics
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank()
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=2)
     * @Assert\NotBlank()
     */
    private $geo;

    /**
     * @ORM\Column(type="string", length=7)
     * @Assert\NotBlank()
     */
    private $zone;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     */
    private $impressions;

    /**
     * @ORM\Column(type="float")
     * @Assert\NotBlank()
     */
    private $revenue;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date): void
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getGeo()
    {
        return $this->geo;
    }

    /**
     * @param mixed $geo
     */
    public function setGeo($geo): void
    {
        $this->geo = $geo;
    }

    /**
     * @return mixed
     */
    public function getZone()
    {
        return $this->zone;
    }

    /**
     * @param mixed $zone
     */
    public function setZone($zone): void
    {
        $this->zone = $zone;
    }

    /**
     * @return mixed
     */
    public function getImpressions()
    {
        return $this->impressions;
    }

    /**
     * @param mixed $impressions
     */
    public function setImpressions($impressions): void
    {
        $this->impressions = $impressions;
    }

    /**
     * @return mixed
     */
    public function getRevenue()
    {
        return $this->revenue;
    }

    /**
     * @param mixed $revenue
     */
    public function setRevenue($revenue): void
    {
        $this->revenue = $revenue;
    }


}
