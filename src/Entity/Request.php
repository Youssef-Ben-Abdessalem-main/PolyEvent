<?php

namespace App\Entity;

use App\Repository\RequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $idevent = null;

    #[ORM\Column(length: 255)]
    private ?string $title_request = null;

    #[ORM\Column(length: 255)]
    private ?string $description_request = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdevent(): ?int
    {
        return $this->idevent;
    }

    public function setIdevent(int $idevent): self
    {
        $this->idevent = $idevent;

        return $this;
    }

    public function getTitleRequest(): ?string
    {
        return $this->title_request;
    }

    public function setTitleRequest(string $title_request): self
    {
        $this->title_request = $title_request;

        return $this;
    }

    public function getDescriptionRequest(): ?string
    {
        return $this->description_request;
    }

    public function setDescriptionRequest(string $description_request): self
    {
        $this->description_request = $description_request;

        return $this;
    }
}
