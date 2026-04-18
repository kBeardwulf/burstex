<?php
namespace App\Entity;

use App\Repository\RegisteredRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegisteredRepository::class)]
class Registered
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournament $tournament = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(nullable: true)]
    private ?int $seed = null;

    /**
     * @var Collection<int, Matches>
     */
    #[ORM\OneToMany(targetEntity: Matches::class, mappedBy: 'player1')]
    private Collection $allMatches;

    public function __construct()
    {
        $this->allMatches = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getTournament(): ?Tournament { return $this->tournament; }
    public function setTournament(Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    public function setSeed(?int $seed): static
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * @return Collection<int, Matches>
     */
    public function getAllMatches(): Collection
    {
        return $this->allMatches;
    }

    public function addAllMatch(Matches $allMatch): static
    {
        if (!$this->allMatches->contains($allMatch)) {
            $this->allMatches->add($allMatch);
            $allMatch->setPlayer1($this);
        }

        return $this;
    }

    public function removeAllMatch(Matches $allMatch): static
    {
        if ($this->allMatches->removeElement($allMatch)) {
            // set the owning side to null (unless already changed)
            if ($allMatch->getPlayer1() === $this) {
                $allMatch->setPlayer1(null);
            }
        }

        return $this;
    }
}