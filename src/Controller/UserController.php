<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Util\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

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
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Un utilisateru existe déjà'], 400);
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

    #[Route('/remove/{id}', name:'user_delete', methods: ['DELETE'])]
    public function removeUser(int $id): JsonResponse
    {
        // Récupérer l'utilisateur à partir de l'ID
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            // Si l'utilisateur n'existe pas, retourner une erreur 404
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Supprimer l'utilisateur
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        // Retourner une réponse confirmant la suppression
        return new JsonResponse(['message' => 'Utilisateur supprimé !']);
    }

    #[Route('/update/{id}', name: 'user_update', methods: ['PUT', 'PATCH'])]
    public function updateUser(Request $request, int $id): JsonResponse
    {
        // Récupérer l'utilisateur à partir de l'ID
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer les données de la requête
        $data = json_decode($request->getContent(), true);

        // Vérifier si le nom est présent et le mettre à jour
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        // Vérifier si un rôle est fourni et mettre à jour les rôles
        if (isset($data['roles']) && is_array($data['roles'])) {
            // Supprimer tous les rôles existants de l'utilisateur
            $user->getUserRoles()->clear();

            // Ajouter les nouveaux rôles
            foreach ($data['roles'] as $roleId) {
                $role = $this->entityManager->getRepository(Role::class)->find($roleId);
                if ($role) {
                    $user->addUserRole($role);
                } else {
                    return new JsonResponse(['error' => "Rôle avec l'ID {$roleId} non trouvé"], 404);
                }
            }
        }

        // Sauvegarder les modifications
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur mis à jour avec succès'], 200);
    }

    #[Route('/{id}', name: 'user_get', methods: ['GET'])]
    public function getUserById(int $id): JsonResponse
    {
        // Récupérer l'utilisateur à partir de l'ID
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Construire la liste des rôles
        $roles = $user->getUserRoles()->map(function ($role) {
            return $role->getName();
        })->toArray();

        // Retourner les données utilisateur
        return new JsonResponse([
            'username' => $user->getUsername(),
            'roles' => $roles,
        ], 200);
    }

}