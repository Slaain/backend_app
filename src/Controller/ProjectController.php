<?php

namespace App\Controller;

// Importation des entités et des classes nécessaires
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// Route de base pour les endpoints liés aux projets
#[Route('/projects')]
class ProjectController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    // Injection de dépendance pour l'EntityManager
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // Vérifie si l'utilisateur peut gérer les projets (admin ou manager)
    private function canManageProject(): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_MANAGER');
    }

    // Endpoint pour récupérer tous les projets
    #[Route('', name: 'api_projects_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): JsonResponse
    {
        // Récupération de tous les projets via le repository
        $projects = $projectRepository->findAll();

        // Transformation des projets en tableau de données pour l'API
        $data = array_map(function (Project $project) {
            return [
                'id' => $project->getId(),
                'project' => $project->getProject(),
                'description' => $project->getDescription(),
                'users' => $project->getUsers()->map(fn(User $user) => $user->getUsername())->toArray(),
                'notes' => $project->getProjectNotes()->map(function ($note) {
                    return [
                        'id' => $note->getId(),
                        'content' => $note->getContent(),
                        'users' => $note->getUsers(),
                        'createdAt' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                })->toArray(),
            ];
        }, $projects);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    // Endpoint pour créer un nouveau projet
    #[Route('/new', name: 'api_projects_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Vérifie si l'utilisateur a le rôle ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les administrateurs peuvent créer des projets.');
        }

        $data = json_decode($request->getContent(), true);

        // Validation des données reçues
        if (!isset($data['project']) || empty($data['project'])) {
            return new JsonResponse(['error' => 'Le champ "project" est obligatoire'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Vérifie si le projet existe déjà
        try {
            $existingProject = $this->entityManager->getRepository(Project::class)->findOneBy(['Project' => $data['project']]);
            if ($existingProject) {
                return new JsonResponse(['error' => 'Un projet existe déjà'], 400);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la vérification du projet'], 500);
        }

        // Création du nouveau projet
        $project = new Project();
        $project->setProject($data['project']);
        $project->setDescription($data['description'] ?? '');

        // Persistance et sauvegarde du projet en base
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Projet créé avec succès',
            'project_id' => $project->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    // Endpoint pour afficher un projet spécifique
    #[Route('/{id}', name: 'api_projects_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        // Transformation du projet en tableau pour l'API
        $data = [
            'id' => $project->getId(),
            'project' => $project->getProject(),
            'description' => $project->getDescription(),
            'users' => $project->getUsers()->map(fn(User $user) => $user->getUsername())->toArray(),
            'notes' => $project->getProjectNotes()->map(function ($note) {
                return [
                    'id' => $note->getId(),
                    'content' => $note->getContent(),
                    'users' => $note->getUsers(),
                    'createdAt' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            })->toArray(),
        ];

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    // Endpoint pour retirer un utilisateur d'un projet
    #[Route('/{id}/remove-user/{userId}', name: 'api_projects_remove_user', methods: ['POST'])]
    public function removeUser(Project $project, int $userId): JsonResponse
    {
        // Vérifie si l'utilisateur a les droits nécessaires
        if (!$this->canManageProject()) {
            throw new AccessDeniedException('Accès refusé.');
        }

        // Recherche de l'utilisateur
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Suppression de l'utilisateur du projet
        $project->removeUser($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Utilisateur retiré du projet avec succès',
        ], JsonResponse::HTTP_OK);
    }

    // Endpoint pour ajouter un utilisateur à un projet
    #[Route('/{id}/add-user', name: 'api_projects_add_user', methods: ['POST'])]
    public function addUser(Request $request, Project $project): JsonResponse
    {
        // Vérifie si l'utilisateur peut gérer le projet
        if (!$this->canManageProject()) {
            throw new AccessDeniedException('Accès refusé.');
        }

        $data = json_decode($request->getContent(), true);

        // Validation des données reçues
        if (!isset($data['userId'])) {
            return new JsonResponse(['error' => 'UserId est requis'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            // Recherche de l'utilisateur
            $user = $this->entityManager->getRepository(User::class)->find($data['userId']);

            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
            }

            if ($project->getUsers()->contains($user)) {
                return new JsonResponse(['error' => 'L\'utilisateur est déjà dans le projet'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Ajout de l'utilisateur au projet
            $project->addUser($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'Utilisateur ajouté au projet avec succès',
                'user' => $user->getUsername()
            ], JsonResponse::HTTP_OK);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'ajout de l\'utilisateur'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Endpoint pour mettre à jour un projet
    #[Route('/{id}/edit', name: 'api_projects_update', methods: ['PUT'])]
    public function update(Request $request, Project $project): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs du projet si les données sont fournies
        if (isset($data['project'])) {
            $project->setProject($data['project']);
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Projet mis à jour avec succès',
        ], JsonResponse::HTTP_OK);
    }

    // Endpoint pour supprimer un projet
    #[Route('/{id}/delete', name: 'api_projects_delete', methods: ['DELETE'])]
    public function delete(Project $project): JsonResponse
    {
        // Suppression du projet de la base de données
        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Projet supprimé avec succès',
        ], JsonResponse::HTTP_OK);
    }
}
