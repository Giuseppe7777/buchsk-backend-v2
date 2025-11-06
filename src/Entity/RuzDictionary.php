<?php

namespace App\Entity;

use App\Repository\RuzDictionaryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RuzDictionaryRepository::class)]
#[ORM\Table(name: 'ruz_dictionary')]
#[ORM\UniqueConstraint(name: 'uniq_type_code', columns: ['type', 'code'])]
#[ORM\Index(name: 'idx_type', columns: ['type'])]
#[ORM\Index(name: 'idx_code', columns: ['code'])]
class RuzDictionary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $nameSk = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameEn = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getNameSk(): ?string
    {
        return $this->nameSk;
    }

    public function setNameSk(string $nameSk): static
    {
        $this->nameSk = $nameSk;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }
}
