<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user')]
class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/create', name: 'user_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        // Récupérer les données de la requête
        $data = json_decode($request->getContent(), true);

        // Vérifier les champs obligatoires
        if (!isset($data['username'], $data['password'])) {
            return new JsonResponse(['error' => 'Les champs username et password sont requis'], 400);
        }

        // Créer un nouvel utilisateur
        $user = new User();
        $user->setUsername($data['username']);
        $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT)); // Hashage du mot de passe
        $user->setRoles([]); // Rôles par défaut (vide)

        // Sauvegarder l'utilisateur dans la base de données
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur créé avec succès', 'user_id' => $user->getId()], 201);
    }

    #[Route('/{id}/assign-role/{roleId}', name: 'user_assign_role', methods: ['POST'])]
    public function assignRoleToUser(int $id, int $roleId): JsonResponse
    {
        // Récupérer l'utilisateur
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer le rôle
        $role = $this->entityManager->getRepository(Role::class)->find($roleId);
        if (!$role) {
            return new JsonResponse(['error' => 'Rôle non trouvé'], 404);
        }

        // Associer le rôle à l'utilisateur
        $user->addUserRole($role);

        // Sauvegarder les modifications
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Rôle assigné à l\'utilisateur avec succès'], 200);
    }
}
