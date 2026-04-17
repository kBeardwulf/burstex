<?php

namespace App\Twig\Components;

use App\Entity\Tournament;
use App\Entity\Registered;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FeaturedTournament
{
    public Tournament $tournament;
    
}
