<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Role;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RoleController extends AbstractController
{
    #[Route('/role/create', name: 'role_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
    public function createRole(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupère les données envoyées
        $data = json_decode($request->getContent(), true);

        // Vérifie si le champ 'name' est présent
        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'Le nom du rôle est requis'], 400);
        }

        $role = new Role();
        $role->setName($data['name']);

        // Sauvegarder le rôle dans la base de données
        $entityManager->persist($role);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Rôle créé avec succès', 'role' => $role->getName()], 201);
    }

    // Suppression d'un rôle par ID, accessible uniquement aux utilisateurs ADMIN
    #[Route('/role/remove/{id}', name: 'role_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
    public function deleteRole(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupérer le rôle à partir de l'ID
        $role = $entityManager->getRepository(Role::class)->find($id); // Utilisation de $entityManager ici

        if (!$role) {
            return new JsonResponse(['error' => 'Rôle non trouvé'], 404);
        }

        // Supprimer le rôle
        $entityManager->remove($role);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Rôle supprimé avec succès'], 200);
    }
}
