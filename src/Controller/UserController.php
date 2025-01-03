<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // Création d'un utilisateur, accessible uniquement aux utilisateurs ADMIN
    #[Route('/admin/create', name: 'user_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username'], $data['password'])) {
            return new JsonResponse(['error' => 'Les champs username et password sont requis'], 400);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Un utilisateur existe déjà'], 400);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur créé avec succès', 'user_id' => $user->getId()], 201);
    }

    // Ajouter un rôle à un utilisateur, accessible uniquement aux utilisateurs ADMIN
    #[Route('/{id}/assign-role/{roleId}', name: 'user_assign_role', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
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

    // Supprimer un utilisateur, accessible uniquement aux utilisateurs ADMIN
    #[Route('/remove/{id}', name: 'user_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
    public function removeUser(int $id): JsonResponse
    {
        // Récupérer l'utilisateur à partir de l'ID
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Supprimer l'utilisateur
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur supprimé !']);
    }

    // Mettre à jour un utilisateur, accessible uniquement aux utilisateurs ADMIN
    #[Route('/update/{id}', name: 'user_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
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

    // Récupérer un utilisateur par son ID, accessible uniquement aux utilisateurs AUTHENTIFIÉS
    #[Route('/{id}', name: 'user_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous devez être authentifié pour accéder à ces informations')]
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

        return new JsonResponse([
            'username' => $user->getUsername(),
            'roles' => $roles,
        ], 200);
    }

    // Connexion - Ne nécessite pas de rôle, tout utilisateur valide peut se connecter
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Vérifie les champs obligatoires
        if (!isset($data['username'], $data['password'])) {
            return new JsonResponse(['error' => 'Les champs username et password sont obligatoires'], 400);
        }

        // Récupérer l'utilisateur à partir du nom d'utilisateur
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $data['username']]);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Vérifie le mot de passe
        if (!password_verify($data['password'], $user->getPassword())) {
            return new JsonResponse(['error' => 'Mot de passe incorrect'], 401);
        }

        // Générer le token JWT
        $token = $JWTManager->create($user);

        return new JsonResponse(['token' => $token, 'username' => $user->getUsername(), 'roles' => $user->getRoles()], 200);
    }

    // Récupérer tous les utilisateurs, accessible uniquement aux utilisateurs AUTHENTIFIÉS
    #[Route('', name: 'alluser_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous devez être authentifié pour accéder à cette ressource')]
    public function getUsers(UserRepository $userRepository): JsonResponse
    {
        // Récupérer tous les utilisateurs
        $usersAll = $userRepository->findAll();

        $data = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),

            ];
        }, $usersAll);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}
