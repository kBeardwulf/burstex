<?php

namespace App\Entity;

use App\Repository\MatchesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchesRepository::class)]
class Matches
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'allMatches')]
    private ?Registered $player1 = null;

    #[ORM\ManyToOne]
    private ?registered $player2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $score1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $score2 = null;

    #[ORM\Column]
    private ?int $round = null;

    #[ORM\ManyToOne(inversedBy: 'AllTourMatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournament $tournament = null;

    #[ORM\ManyToOne]
    private ?Registered $winner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer1(): ?Registered
    {
        return $this->player1;
    }

    public function setPlayer1(?Registered $player1): static
    {
        $this->player1 = $player1;

        return $this;
    }

    public function getPlayer2(): ?registered
    {
        return $this->player2;
    }

    public function setPlayer2(?registered $player2): static
    {
        $this->player2 = $player2;

        return $this;
    }

    public function getScore1(): ?int
    {
        return $this->score1;
    }

    public function setScore1(?int $score1): static
    {
        $this->score1 = $score1;

        return $this;
    }

    public function getScore2(): ?int
    {
        return $this->score2;
    }

    public function setScore2(?int $score2): static
    {
        $this->score2 = $score2;

        return $this;
    }

    public function getRound(): ?int
    {
        return $this->round;
    }

    public function setRound(int $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): static
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getWinner(): ?Registered
    {
        return $this->winner;
    }

    public function setWinner(?Registered $winner): static
    {
        $this->winner = $winner;

        return $this;
    }
}
