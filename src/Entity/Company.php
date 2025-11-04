<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $ruzId = null;

    #[ORM\Column(length: 20)]
    private ?string $ico = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dic = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $sid = null;

    #[ORM\Column(length: 500)]
    private ?string $nazovUj = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $mesto = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $ulica = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $psc = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $datumZalozenia = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $datumZrusenia = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pravnaForma = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $skNace = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $velkostOrganizacie = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $druhVlastnictva = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $kraj = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $okres = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sidlo = null;

    #[ORM\Column(nullable: true)]
    private ?bool $konsolidovana = null;

    #[ORM\Column(nullable: true)]
    private ?array $idUctovnychZavierok = null;

    #[ORM\Column(nullable: true)]
    private ?array $idVyrocnychSprav = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $zdrojDat = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $datumPoslednejUpravy = null;

    #[ORM\OneToOne(inversedBy: 'company', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuzId(): ?string
    {
        return $this->ruzId;
    }

    public function setRuzId(?string $ruzId): static
    {
        $this->ruzId = $ruzId;

        return $this;
    }

    public function getIco(): ?string
    {
        return $this->ico;
    }

    public function setIco(string $ico): static
    {
        $this->ico = $ico;

        return $this;
    }

    public function getDic(): ?string
    {
        return $this->dic;
    }

    public function setDic(?string $dic): static
    {
        $this->dic = $dic;

        return $this;
    }

    public function getSid(): ?string
    {
        return $this->sid;
    }

    public function setSid(?string $sid): static
    {
        $this->sid = $sid;

        return $this;
    }

    public function getNazovUj(): ?string
    {
        return $this->nazovUj;
    }

    public function setNazovUj(string $nazovUj): static
    {
        $this->nazovUj = $nazovUj;

        return $this;
    }

    public function getMesto(): ?string
    {
        return $this->mesto;
    }

    public function setMesto(?string $mesto): static
    {
        $this->mesto = $mesto;

        return $this;
    }

    public function getUlica(): ?string
    {
        return $this->ulica;
    }

    public function setUlica(?string $ulica): static
    {
        $this->ulica = $ulica;

        return $this;
    }

    public function getPsc(): ?string
    {
        return $this->psc;
    }

    public function setPsc(?string $psc): static
    {
        $this->psc = $psc;

        return $this;
    }

    public function getDatumZalozenia(): ?\DateTime
    {
        return $this->datumZalozenia;
    }

    public function setDatumZalozenia(?\DateTime $datumZalozenia): static
    {
        $this->datumZalozenia = $datumZalozenia;

        return $this;
    }

    public function getDatumZrusenia(): ?\DateTime
    {
        return $this->datumZrusenia;
    }

    public function setDatumZrusenia(?\DateTime $datumZrusenia): static
    {
        $this->datumZrusenia = $datumZrusenia;

        return $this;
    }

    public function getPravnaForma(): ?string
    {
        return $this->pravnaForma;
    }

    public function setPravnaForma(?string $pravnaForma): static
    {
        $this->pravnaForma = $pravnaForma;

        return $this;
    }

    public function getSkNace(): ?string
    {
        return $this->skNace;
    }

    public function setSkNace(?string $skNace): static
    {
        $this->skNace = $skNace;

        return $this;
    }

    public function getVelkostOrganizacie(): ?string
    {
        return $this->velkostOrganizacie;
    }

    public function setVelkostOrganizacie(?string $velkostOrganizacie): static
    {
        $this->velkostOrganizacie = $velkostOrganizacie;

        return $this;
    }

    public function getDruhVlastnictva(): ?string
    {
        return $this->druhVlastnictva;
    }

    public function setDruhVlastnictva(?string $druhVlastnictva): static
    {
        $this->druhVlastnictva = $druhVlastnictva;

        return $this;
    }

    public function getKraj(): ?string
    {
        return $this->kraj;
    }

    public function setKraj(?string $kraj): static
    {
        $this->kraj = $kraj;

        return $this;
    }

    public function getOkres(): ?string
    {
        return $this->okres;
    }

    public function setOkres(?string $okres): static
    {
        $this->okres = $okres;

        return $this;
    }

    public function getSidlo(): ?string
    {
        return $this->sidlo;
    }

    public function setSidlo(?string $sidlo): static
    {
        $this->sidlo = $sidlo;

        return $this;
    }

    public function isKonsolidovana(): ?bool
    {
        return $this->konsolidovana;
    }

    public function setKonsolidovana(?bool $konsolidovana): static
    {
        $this->konsolidovana = $konsolidovana;

        return $this;
    }

    public function getIdUctovnychZavierok(): ?array
    {
        return $this->idUctovnychZavierok;
    }

    public function setIdUctovnychZavierok(?array $idUctovnychZavierok): static
    {
        $this->idUctovnychZavierok = $idUctovnychZavierok;

        return $this;
    }

    public function getIdVyrocnychSprav(): ?array
    {
        return $this->idVyrocnychSprav;
    }

    public function setIdVyrocnychSprav(?array $idVyrocnychSprav): static
    {
        $this->idVyrocnychSprav = $idVyrocnychSprav;

        return $this;
    }

    public function getZdrojDat(): ?string
    {
        return $this->zdrojDat;
    }

    public function setZdrojDat(?string $zdrojDat): static
    {
        $this->zdrojDat = $zdrojDat;

        return $this;
    }

    public function getDatumPoslednejUpravy(): ?\DateTime
    {
        return $this->datumPoslednejUpravy;
    }

    public function setDatumPoslednejUpravy(?\DateTime $datumPoslednejUpravy): static
    {
        $this->datumPoslednejUpravy = $datumPoslednejUpravy;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
