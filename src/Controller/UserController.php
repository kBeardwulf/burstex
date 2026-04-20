<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserInfoFormType;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
final class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'app_user_create', methods: ['GET', 'POST'])]
    public function create(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $objUser = new User();

        $userForm = $this->createForm(UserInfoFormType::class, $objUser);

        $userForm->handleRequest($request);
        
        if($userForm->isSubmitted() && $userForm->isValid()) {

            /** @var string $plainPassword */
            $plainPassword = $userForm->get('plainPassword')->getData();

            // If the password is not filled
            if(!$plainPassword) {

                $plainPassword = "default_password";
            }

            // encode the plain password
            $objUser->setPassword($userPasswordHasher->hashPassword($objUser, $plainPassword));

            // set the registration datetime
            $objUser->setRegisteredAt(new DateTimeImmutable('now'));
            $objUser->setRoles(['ROLE_USER']);
            
            $entityManager->persist($objUser);
            $entityManager->flush();

            $this->addFlash('success', "L'utilisateur a été créé");

            return $this->redirectToRoute('app_tournament');
        }

        return $this->render('user/form.html.twig', [
            'registrationForm' => $userForm
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserInfoFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
             $this->addFlash('success', "L'utilisateur a été supprimé");
        }

        return $this->redirectToRoute('app_user_index');
    }
}
