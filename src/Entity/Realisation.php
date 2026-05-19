<?php

namespace App\Entity;

use App\Repository\RealisationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RealisationRepository::class)]
class Realisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subCategory = null;

    #[ORM\Column(length: 255)]
    private ?string $imageAfter = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageBefore = null;

    #[ORM\Column]
    private ?bool $isVideo = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getSubCategory(): ?string
    {
        return $this->subCategory;
    }

    public function setSubCategory(?string $subCategory): static
    {
        $this->subCategory = $subCategory;
        return $this;
    }

    public function getImageAfter(): ?string
    {
        return $this->imageAfter;
    }

    public function setImageAfter(string $imageAfter): static
    {
        $this->imageAfter = $imageAfter;
        return $this;
    }

    public function getImageBefore(): ?string
    {
        return $this->imageBefore;
    }

    public function setImageBefore(?string $imageBefore): static
    {
        $this->imageBefore = $imageBefore;
        return $this;
    }

    public function isVideo(): ?bool
    {
        return $this->isVideo;
    }

    public function setIsVideo(bool $isVideo): static
    {
        $this->isVideo = $isVideo;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
