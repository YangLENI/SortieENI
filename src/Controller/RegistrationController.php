<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ImportCSVFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\CsvImportCommand;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('admin/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $user = new User();
        $user->setAdmin(false);
        $user->setActive(true);
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('Image')->getData();
            if ($imageFile){
                $user->setImage($fileUploader->upload($imageFile, $this->getParameter('app.images_user_directory')));
            }

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            $em->persist($user);
            $em->flush();
            // do anything else you need here, like send an email

            return $this->redirectToRoute('profiler', ['id'=>$user->getId()]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('profil/{id}', name: 'profiler', requirements:['id'=>'\d+'],methods:['GET', 'POST'])]
    public function show(int $id, Request $request, UserRepository $userRepository, CsvImportCommand $csvImportCommand):Response{
        $user = $userRepository->find($id);

        if ($this->isGranted("ROLE_ADMIN")){
            $form = $this->createForm(ImportCSVFormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid() && $this->isGranted("ROLE_ADMIN")) {
                $imageFile = $form->get('upload_file')->getData();
                $csvImportCommand->uploadCSV($imageFile);
                $message = $csvImportCommand->createUsersFromCSV();
                $this->addFlash($message[0],$message[1]);

                return $this->render('registration/profiler.html.twig', ['user' => $user,'CSVForm' => $form]);
            }
        } else {
            $form = null;
        }

        if (!$user){
            throw $this->createNotFoundException('Utilisateur inconnu');
        }

        return $this->render('registration/profiler.html.twig', ['user' => $user,'CSVForm' => $form]);
    }

    #[Route(path:'user/profil/edit/{id}', name: 'editProfil',requirements:['id'=>'\d+'], methods: ['GET', 'POST'])]
    public function editProfil(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, FileUploader $fileUploader):Response
    {
        $user = $userRepository->find($id);
        if (!$user){
            throw $this->createNotFoundException('L\'utilisateur n\'existe pas !');
        }
        if ($id !== $this->getUser()->getId()){
            throw $this->createNotFoundException('Vous n\'êtes pas habilité a modifier ce profil !');
        }

        $registrationForm=$this->createForm(RegistrationFormType::class, $user);
        $registrationForm->handleRequest($request);

        if ($registrationForm->isSubmitted() && $registrationForm->isValid()){
            if ($request->request->has('saveProfil')){
                // On supprime l'image ou on insere une nouvelle
                $imageFile = $registrationForm->get('image')->getData();
                if (($registrationForm->has('deleteImage') && $registrationForm['deleteImage']->getData()) || $imageFile) {

                    $fileUploader->delete($user->getImage(), $this->getParameter('app.images_user_directory'));

                    if ($imageFile) {
                        $user->setImage($fileUploader->upload($imageFile,$this->getParameter('app.images_user_directory')));
                    } else {
                        $user->setImage(null);
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Votre profil a été mise à jour avec succès ! ');
                return $this->redirectToRoute('profiler', ['id' => $user->getId()]);
            }
            elseif ($request->request->has('deleteProfil')){
                $user->setActive(false);
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('app_logout');
            }
            else{
                return $this->redirectToRoute('profiler', ['id' => $user->getId()]);
            }
        }
        return $this->render('registration/editProfil.html.twig', ['registrationForm' => $registrationForm, 'user'=>$user]);
    }
}
