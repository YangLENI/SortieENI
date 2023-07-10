<?php

namespace App\Controller;

use App\Entity\City;
use App\Form\UpdateCityType;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UpdateCityController extends AbstractController
{
    #[Route('/admin/city/update/{id}', name: 'admin_update_city', requirements:['id'=>'\d+'], methods: ['GET','POST'])]
    public function index(City $city, Request $request, EntityManagerInterface $em): Response
    {
        $updateCityForm = $this->createForm(UpdateCityType::class, $city);
        $updateCityForm->handleRequest($request);

        if($updateCityForm->isSubmitted() && $updateCityForm->isValid()){
            $em->flush();
            $this->addFlash('success','La ville est modifiée !');
        }

        return $this->render('city/updateCity.html.twig', [
            'updateCityForm' => $updateCityForm,
            'city'=> $city
        ]);
    }
}
