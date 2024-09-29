<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SwaggerController extends AbstractController
{
    #[Route('/swagger', name: 'app_swagger')]
    public function index(): Response
    {
        return $this->render('swagger/index.html.twig', [
            'controller_name' => 'SwaggerController',
        ]);
    }
}
