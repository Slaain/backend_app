<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Role;

class RoleController extends AbstractController
{
    #[Route('/role/create', name: 'role_create', methods: ['POST'])]
    public function createRole(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupère les données envoyés
        $data = json_decode($request->getContent(), true);

        //vérifie si le champ 'name' est présent
        if (!isset($data['name'])){
            return new JsonResponse(['error probleme' => 'Le nom du role est requis'],400);
        }
        $role = New Role();
        $role->setName($data['name']);

        //sauvegarder le role dans la badd
        $entityManager->persist($role);
        $entityManager->flush();
        return new JsonResponse(['message' => 'Role crée avec succes', 'role' => $role->getName()], 201);
    }
}
