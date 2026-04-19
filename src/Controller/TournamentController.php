<?php

namespace App\Controller;

use App\Entity\Matches;
use App\Entity\Registered;
use App\Entity\Tournament;
use App\Form\TournamentFormType;
use App\Repository\TournamentRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class TournamentController extends AbstractController
{
    #[Route('/tournament/home', name: 'app_tournament')]
    public function index(TournamentRepository $tournamentRepository): Response
    {
        $tournaments = $tournamentRepository->findBy([], ['date' => 'ASC']);

        return $this->render('tournament/index.html.twig', [
            'controller_name' => 'TournamentController',
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * lists every tournament posted, ordered by the most recent or depending on the research made
     */
    #[Route('/tournament/list', name: 'app_tour_list', methods: ['GET'])]
    public function list(TournamentRepository $tournamentRepository, Request $request): Response
    {
        $search = $request->query->get('search');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $maxNb = $request->query->get('max_nb');
        $upcoming = $request->query->get('not_started');
        $type = $request->query->get('type'); // tournament ou user

        $tournaments = $tournamentRepository->searchTournaments($search, $dateFrom, $dateTo, $maxNb, $upcoming, $type);
        
        return $this->render('tournament/list.html.twig', [
            'tournaments' => $tournaments,
            'search' => $search,
        ]);
    }

    /**
     * leads to a form that, once filled without errors, creates a new Tournament available to people.
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/tournament/create', name: 'app_tour_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {

        $objTour = new Tournament();

        $TourForm = $this->createForm(TournamentFormType::class, $objTour);

        $TourForm->handleRequest($request);
        
        if($TourForm->isSubmitted() && $TourForm->isValid()) {
            
            $entityManager->persist($objTour);

            $objTour->setCreatedBy($this->getUser());
            $objRegistered = new Registered();
            $objRegistered->setTournament($objTour);
            $objRegistered->setUser($this->getUser());
            $objRegistered->setRole('admin');

            $entityManager->persist($objRegistered);
            $entityManager->flush();

            $this->addFlash('success', "Le tournoi a été créé");

            return $this->redirectToRoute('app_tournament');
        }

        return $this->render('tournament/form.html.twig', [
            'tournamentForm' => $TourForm,
            'players' => [],
            'isEdit' => false,
        ]);
    }

    /**
     * display the detail page of a tournament
     */
    #[Route('tournament/{id<\d+>}', name: 'app_tour_show', methods: ['GET'])]
    public function show(Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        $admins = $entityManager->getRepository(Registered::class)->findBy([
            'tournament' => $tournament,
            'role' => 'admin'
        ]);

        // Finds if the current user is an administrator of the tournament
        // by checking if his role is set to "admin", 
        // tied to the tournament in the DB, inside the "Registered" table
        $isAdmin = false;
        foreach($admins as $admin) {
            if($admin->getUser() === $this->getUser()) {
                $isAdmin = true;
                break;
            }
        }

        $staff = $entityManager->getRepository(Registered::class)->findStaffByTournament($tournament);

        // Finds if the current user is registered to the tournament or not by checking if his role is set to "player", 
        // tied to the tournament in the DB, inside the "Registered" table
        $isPlayer = $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'player'
        ]);

        // table of all the registered player to a tournament that has the role "player"
        // ordered by seed (use CTRL+F & type updateSeed to find more info about what the seed is)
        $players = $entityManager->getRepository(Registered::class)->findBy(
            ['tournament' => $tournament, 'role' => 'player'],
            ['seed' => 'ASC']
            );

        return $this->render('tournament/show.html.twig', [
            'tournament' => $tournament,
            'isAdmin' => $isAdmin,
            'isPlayer' => $isPlayer,
            'players' => $players,
            'staff' => $staff,
        ]);
    }

    /** 
     * uses the creation form as an edit form to modify information of a tournament
    */
    #[Route('/tournament/{id<\d+>}/edit', name: 'app_tour_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }


        $form = $this->createForm(TournamentFormType::class, $tournament, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Tournoi modifié avec succès');
            return $this->redirectToRoute('app_tour_edit', ['id' => $tournament->getId()]);
        }

        // table of all the registered player to a tournament that has the role "player"
        // ordered by seed (use CTRL+F & type updateSeed to find more info about what the seed is)
        $players = $entityManager->getRepository(Registered::class)->findBy(
            ['tournament' => $tournament, 'role' => 'player'],
            ['seed' => 'ASC']
        );

        return $this->render('tournament/form.html.twig', [
            'tournamentForm' => $form,
            'tournament' => $tournament,
            'players' => $players,
            'isEdit' => true,
        ]);
    }

    /**
     * Dashboard of every tournament a user has created.
     */
    #[Route('/tournament/dashboard', name: 'app_tour_dashboard', methods: ['GET'])]
    public function myTournaments(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        $registrations = $entityManager->getRepository(Registered::class)->findBy([
            'user' => $user,
            'role' => 'admin'
        ]);
        
        $registrations = $entityManager->getRepository(Registered::class)->findBy([
            'user' => $user,
            'role' => 'admin'
        ]);

        $tournaments = [];
        foreach($registrations as $registration) {
            $tournaments[] = $registration->getTournament();
        }
        
        return $this->render('tournament/dashboard.html.twig', [
            'tournaments' => $tournaments,
        ]);
    }


    /**
     * Personnalized delete function to correctly erase my tournament informations on every concerned table
     * @return bool the deleted data
     * @param getPayload fetches the info of the "form" to delete
     * @param getString fetches the token in "value"
     */
    #[Route('/tournament/{id<\d+>}', name: 'app_tour_delete', methods: ['POST'])]
    public function delete(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            // Supprimer d'abord les registered liés
            $registrations = $entityManager->getRepository(Registered::class)->findBy(['tournament' => $tournament]);
            foreach($registrations as $registration) {
                $entityManager->remove($registration);
            }
            // Puis supprimer le tournoi
            $entityManager->remove($tournament);
            $entityManager->flush();
            $this->addFlash('success', 'Tournoi supprimé');
        }
        return $this->redirectToRoute('app_tournament');
    }

    /**
     * Uploads (add or edit) an image as banner for the tournament and make sure the old file is deleted
     * @return string the uploaded image
     */
    #[Route('/tournament/{id<\d+>}/banner', name: 'app_tour_banner', methods: ['POST'])]
    public function updateBanner(Request $request, Tournament $tournament, EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        /** @var UploadedFile $objUploadedFile */
        $objUploadedFile = $request->files->get('banner');

        if($objUploadedFile) {
            // Generates a unique name & moves the file in /public/uploads/banners
            $strNewFilename = $fileUploader->upload($objUploadedFile);

            // Saves the old name to delete the old file
            $oldBanner = $tournament->getBanner();

            // Updates the tournament with the new file name
            $tournament->setBanner($strNewFilename);
            $entityManager->flush();

            // Deletes the old banner from the files if it existed
            if($oldBanner) {
                $fileUploader->remove($oldBanner);
            }

            $this->addFlash('success', 'Bannière mise à jour !');
        }

        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * personnalized delete if the user wants to completely remove the banner.
     * @return true the deleted banner
     * @param getPayload fetches the infos of the <form>
     * @param getString fetches the token in "value"
     * we get the current banner, then remove it with fileUploader then we delete it from the DB.
     */
    #[Route('/tournament/{id<\d+>}/banner/remove', name: 'app_tour_banner_remove', methods: ['POST'])]
    public function removeBanner(Request $request, Tournament $tournament, 
        EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('banner_remove' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            $currentBanner = $tournament->getBanner();
            if($currentBanner) {
                $fileUploader->remove($currentBanner);
                $tournament->setBanner(null);
                $entityManager->flush();
                $this->addFlash('success', 'Bannière supprimée');
            }
        }
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }


    /**
     * Adds or updates an icon image to the tournament
     * @return string the icon
     */
    #[Route('/tournament/{id<\d+>}/icon', name: 'app_tour_icon', methods: ['POST'])]
    public function updateIcon(Request $request, Tournament $tournament, 
        EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        /** @var UploadedFile $objUploadedFile */
        $objUploadedFile = $request->files->get('icon');

        if($objUploadedFile) {
            // Generates a unique name & moves the file into /public/uploads/banners
            $strNewFilename = $fileUploader->upload($objUploadedFile);

            // Save the old name to delete the old file
            $oldIcon = $tournament->getIcon();

            // Updates the tournament with the new file name
            $tournament->setIcon($strNewFilename);
            $entityManager->flush();

            // Deletes the old icon from the files if it existed
            if($oldIcon) {
                $fileUploader->remove($oldIcon);
            }

            $this->addFlash('success', 'Icone mise à jour !');
        }

        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * Add/update the tournament description.
     * @return true the new description content
     * @param getPayload fetches the infos of the <form>
     * @param getString fetches the token in "value"
     * @param request fetches the HTTP request
     * @param request the second one fetches the SQL request
     */
    #[Route('/tournament/{id<\d+>}/description', name: 'app_tour_description', methods: ['POST'])]
    public function updateDescription(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('description' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            $description = $request->request->get('description');
            $tournament->setDescription($description);
            $entityManager->flush();
            $this->addFlash('success', 'Description mise à jour !');
        }
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * Add a user to the Registered DB, making him registered to a specific tournament.
     * @return true the user and the tournament linked & added to the registered table
     * @param getPayload fetches the infos of the <form>
     * @param getString fetches the token in "value"
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/tournament/{id<\d+>}/register', name: 'app_tour_register', methods: ['POST'])]
    public function registerToTournament(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('register' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            $objRegistered = new Registered();
            $objRegistered->setTournament($tournament);
            $objRegistered->setUser($this->getUser());
            $objRegistered->setRole('player');
            
            // Fetches the number of player already registered to assign the seeding
            // use Ctrl+F & type "updateSeed" for more information on the seeding function
            $playersCount = $entityManager->getRepository(Registered::class)->count([
                'tournament' => $tournament,
                'role' => 'player'
            ]);
            $objRegistered->setSeed($playersCount + 1);

            $entityManager->persist($objRegistered);
            $entityManager->flush();
            $this->addFlash('success', 'Votre inscription à bien été prise en compte !');
        }
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * Unregister a user by deleting the line his line in the DB
     * @return true the deleted line in which the user is from the registered table
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/tournament/{id<\d+>}/unregister', name: 'app_tour_unregister', methods: ['POST'])]
    public function unregisterFromTournament(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('unregister' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            $playerIsRegistered = $entityManager->getRepository(Registered::class)->findOneBy([
                'tournament' => $tournament,
                'user' => $this->getUser(),
                'role' => 'player'
            ]);

            if($playerIsRegistered) {
                $entityManager->remove($playerIsRegistered);
                $entityManager->flush();
                $this->addFlash('success', 'Vous avez bien été désinscrit de ce tournoi.');
            }
        }
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * Starts the tournament and prevents people from registering
     */
    #[Route('/tournament/{id<\d+>}/start', name: 'app_tour_start', methods: ['POST'])]
    public function startTournament(Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        $tournament->setIsStarted(true);

        // Fetches the players ordered by their seed
        $players = $entityManager->getRepository(Registered::class)->findBy(
            ['tournament' => $tournament, 'role' => 'player'],
            ['seed' => 'ASC']
        );

        // We create the last rounds so we can tie them later with the right spots
        // this creates the finals
        $finals = new Matches();
        $finals->setTournament($tournament)->setRound(3);
        $entityManager->persist($finals);

        // this creates the semi finals
        $semis = [];
        for ($i = 0; $i < 2; $i++) {
            $semi = new Matches();
            $semi->setTournament($tournament)->setRound(2)->setNextMatch($finals);
            $entityManager->persist($semi);
            $semis[] = $semi;
        }

        // Generates the first round matches
        // seed 1 vs seed 8, seed 2 vs seed 7...
        // the for loop calculates the number of matches it needs to set.
        // if we have 8 players, it divides $total by 2, meaning it has to set 4 matches up.
        // if we have 7 players, player2 can be null, and it triggers the auto win (called a "bye" or a "DQ" for disqualification)

        // Total will be the total of players we have registered to the tournament
        $total = count($players);
        for ($i = 0; $i < $total / 2; $i++) {
            $quart = new Matches();
            $quart->setTournament($tournament)->setRound(1);

            // Sets the best seeded player to face the worst seeded player
            // as per convention in tournament with a seeding system
            $quart->setPlayer1($players[$i] ?? null);
            $quart->setPlayer2($players[$total - 1 - $i] ?? null);
        
            // sets where the winner will be placed for their next matches by dividing the index
            // we force the int so that the division is rounded to the lower number.
            // It makes it so their next match will automatically be the $semi[this match here]
            // We created it beforehand so we could tie them together.
            $quart->setNextMatch($semis[(int)($i / 2)]);
            if (!isset($players[$total - 1 - $i])) {
                $quart->setWinner($players[$i]);
            }
            $entityManager->persist($quart);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Le tournoi a commencé !');
        return $this->redirectToRoute('app_tour_bracket', ['id' => $tournament->getId()]);
    }

    /**
     * Stops the tournament, allows the option to modify & to register again
     */
    #[Route('/tournament/{id<\d+>}/cancel', name: 'app_tour_cancel', methods: ['POST'])]
    public function cancelTournament(Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        $tournament->setIsStarted(false);

        $matches = $entityManager->getRepository(Matches::class)->findBy([
            'tournament' => $tournament
        ]);

        foreach($matches as $match) {
            $entityManager->remove($match);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Le tournoi a été annulé.');
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }


    /**
     * Sorting function used for tournament in general
     * allows the admin or organizer to change who is going to fight who in order to balance a tournament
     * seed 1 fights seed 8, seed 2 fights seed 7 ...etc
     * @param getPayload fetches the infos of the <form>
     * @param getString fetches the token in "value"
     * @param request fetches the HTTP request
     * @param request the second one fetches the SQL request
     */
    #[Route('/tournament/{id<\d+>}/seed', name: 'app_tour_seed', methods: ['POST'])]
    public function updateSeed(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('seed' . $tournament->getId(), $request->getPayload()->getString('_token'))) {
            $order = $request->request->get('order');
            $ids = explode(',', $order);

            foreach ($ids as $position => $registeredId) {
                $registered = $entityManager->getRepository(Registered::class)->find($registeredId);
                if ($registered) {
                    $registered->setSeed($position + 1); // starts at 1
                    }
            }
            $entityManager->flush();
            $this->addFlash('success', 'Ordre sauvegardé !');
        }
        return $this->redirectToRoute('app_tour_edit', ['id' => $tournament->getId()]);
    }

    /**
     * Display the bracket, and manages the different rounds.
     */
    #[Route('/tournament/{id<\d+>}/bracket', name: 'app_tour_bracket', methods: ['GET'])]
    public function bracket(Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        // Fetches every matches but per rounds
        $matches = $entityManager->getRepository(Matches::class)->findBy(
            ['tournament' => $tournament],
            ['round' => 'ASC', 'id' => 'ASC']
        );

        $players = $entityManager->getRepository(Registered::class)->findBy(
            ['tournament' => $tournament, 'role' => 'player'],
            ['seed' => 'ASC']
        );

        $admins = $entityManager->getRepository(Registered::class)->findBy([
            'tournament' => $tournament,
            'role' => 'admin'
        ]);

        $isAdmin = false;
        foreach($admins as $admin) {
            if($admin->getUser() === $this->getUser()) {
                $isAdmin = true;
                break;
            }
        }

        $matchPreview = [];
        $total = count($players);
        for ($i = 0; $i < $total / 2; $i++) {
            $matchPreview[] = [
                'player1' => $players[$i] ?? null,
                'player2' => $players[$total - 1 - $i] ?? null,
            ];
        }

        // create a multi dimensional table. example of how it builds :
        // round[1][all the matches of round 1] to create an object $match
        $rounds = [];
        foreach($matches as $match) {
            $rounds[$match->getRound()][] = $match;
        }

        return $this->render('tournament/bracket.html.twig', [
            'tournament' => $tournament,
            'rounds' => $rounds,
            'players' => $players,
            'matchPreview' => $matchPreview,
            'isAdmin' => $isAdmin
        ]);
    }

    #[Route('/tournament/{id<\d+>}/match/{matchId}/score', name: 'app_tour_score', methods: ['POST'])]
    public function updateScore(Request $request, Tournament $tournament, EntityManagerInterface $entityManager, int $matchId): Response 
    {
        // Admin verification, throws back an error if the user is not admin
        $isAdmin = (bool) $entityManager->getRepository(Registered::class)->findOneBy([
            'tournament' => $tournament,
            'user' => $this->getUser(),
            'role' => 'admin'
        ]);

        if (!$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('score' . $matchId, $request->getPayload()->getString('_token'))) {
            
            $match = $entityManager->getRepository(Matches::class)->find($matchId);
            
            if (!$match) {
                $this->addFlash('danger', 'Match introuvable');
                return $this->redirectToRoute('app_tour_bracket', ['id' => $tournament->getId()]);
            }

            $score1 = (int) $request->request->get('score1');
            $score2 = (int) $request->request->get('score2');

            // Saves the old winner BEFORE changing the scores
            // In case we modify who won
            $oldWinner = $match->getWinner();

            $match->setScore1($score1);
            $match->setScore2($score2);

            // compares score to determine who won
            if ($score1 > $score2) {
                $match->setWinner($match->getPlayer1());
            } elseif ($score2 > $score1) {
                $match->setWinner($match->getPlayer2());
            }

            // sets the winner
            if ($match->getWinner()) {
                $nextMatch = $match->getNextMatch();
                
                if ($nextMatch) {
                    // Removes the old winner if there was one already
                    // In case we modify who won the match before
                    if ($nextMatch->getPlayer1() === $oldWinner) {
                        $nextMatch->setPlayer1(null);
                    } elseif ($nextMatch->getPlayer2() === $oldWinner) {
                        $nextMatch->setPlayer2(null);
                    }
                    
                    // Adds the new winner
                    if ($nextMatch->getPlayer1() === null) {
                        $nextMatch->setPlayer1($match->getWinner());
                    } else {
                        $nextMatch->setPlayer2($match->getWinner());
                    }
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Score mis à jour !');
        }
        return $this->redirectToRoute('app_tour_bracket', ['id' => $tournament->getId()]);
    }
}


