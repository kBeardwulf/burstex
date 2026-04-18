<?php

namespace App\Controller;

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
        ]);
    }

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
    public function updateBanner(Request $request, Tournament $tournament, 
        EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        /** @var UploadedFile $objUploadedFile */
        $objUploadedFile = $request->files->get('banner');

        if($objUploadedFile) {
            // Génère un nom unique et déplace le fichier dans /public/uploads/banners
            $strNewFilename = $fileUploader->upload($objUploadedFile);

            // Sauvegarde l'ancien nom pour supprimer l'ancien fichier
            $oldBanner = $tournament->getBanner();

            // Met à jour le tournoi avec le nouveau nom de fichier
            $tournament->setBanner($strNewFilename);
            $entityManager->flush();

            // Supprime l'ancienne bannière du disque si elle existait
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
        /** @var UploadedFile $objUploadedFile */
        $objUploadedFile = $request->files->get('icon');

        if($objUploadedFile) {
            // Génère un nom unique et déplace le fichier dans /public/uploads/banners
            $strNewFilename = $fileUploader->upload($objUploadedFile);

            // Sauvegarde l'ancien nom pour supprimer l'ancien fichier
            $oldIcon = $tournament->getIcon();

            // Met à jour le tournoi avec le nouveau nom de fichier
            $tournament->setIcon($strNewFilename);
            $entityManager->flush();

            // Supprime l'ancienne icone du disque si elle existait
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
        $tournament->setIsStarted(true);
        $entityManager->flush();
        $this->addFlash('success', 'Le tournoi a commencé !');
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }

    /**
     * Stops the tournament, allows the option to modify & to register again
     */
    #[Route('/tournament/{id<\d+>}/cancel', name: 'app_tour_cancel', methods: ['POST'])]
    public function cancelTournament(Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        $tournament->setIsStarted(false);
        $entityManager->flush();
        $this->addFlash('success', 'Le tournoi a été annulé.');
        return $this->redirectToRoute('app_tour_show', ['id' => $tournament->getId()]);
    }


    /**
     * Sorting function used for tournament in general
     * allows the admin or organizer to change who is going to fight who in order to balance a tournament
     * 1 fights 2, 3 fights 4 ...etc
     * @param getPayload fetches the infos of the <form>
     * @param getString fetches the token in "value"
     * @param request fetches the HTTP request
     * @param request the second one fetches the SQL request
     */
    #[Route('/tournament/{id<\d+>}/seed', name: 'app_tour_seed', methods: ['POST'])]
    public function updateSeed(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
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
}


