<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Util\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/projects')]
class ProjectController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    // Injection du gestionnaire d'entités dans le contrôleur
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Liste tous les projets.
     *
     * @param ProjectRepository $projectRepository
     * @return JsonResponse
     */
    #[Route('', name: 'api_projects_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): JsonResponse
    {
        // Récupérer tous les projets dans la base de données
        $projects = $projectRepository->findAll();

        // Transformer les projets en tableau associatif pour JSON
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

        // Retourner les projets sous forme de réponse JSON
        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }


    /**
     * Crée un nouveau projet.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/new', name: 'api_projects_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Décoder le contenu JSON de la requête
        $data = json_decode($request->getContent(), true);

        // Vérifier si le champ "project" est fourni
        if (!isset($data['project']) || empty($data['project'])) {
            return new JsonResponse(['error' => 'Le champ "project" est obligatoire'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // verifie si il existe
        if (!isset($data['project']) || trim($data['project']) === '') {
            return new JsonResponse(['error' => 'Le champ "project" est requis'], 400);
        }

        try {
            $existingProject = $this->entityManager->getRepository(Project::class)->findOneBy(['Project' => $data['project']]);
            if ($existingProject) {
                return new JsonResponse(['error' => 'Un projet existe déjà'], 400);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la vérification du projet'], 500);
        }


        // Créer un nouvel objet Project et y assigner les données
        $project = new Project();
        $project->setProject($data['project']);
        $project->setDescription($data['description'] ?? '');

        // Persister (sauvegarder) le projet dans la base de données
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        // Retourner une réponse JSON de succès
        return new JsonResponse([
            'message' => 'Projet créé avec succès',
            'project_id' => $project->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Affiche les détails d'un projet spécifique.
     *
     * @param Project $project
     * @return JsonResponse
     */
    #[Route('/{id}', name: 'api_projects_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        // Transformer le projet en tableau associatif
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

        // Retourner les détails du projet sous forme de JSON
        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    /**
     * Met à jour un projet.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    #[Route('/{id}/edit', name: 'api_projects_update', methods: ['PUT'])]
    public function update(Request $request, Project $project): JsonResponse
    {
        // Décoder le contenu JSON de la requête
        $data = json_decode($request->getContent(), true);

        // Mettre à jour les champs "project" et "description" si fournis
        if (isset($data['project'])) {
            $project->setProject($data['project']);
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }

        // Sauvegarder les modifications dans la base de données
        $this->entityManager->flush();

        // Retourner une réponse JSON de succès
        return new JsonResponse([
            'message' => 'Projet mis à jour avec succès',
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Supprime un projet.
     *
     * @param Project $project
     * @return JsonResponse
     */
    #[Route('/{id}/delete', name: 'api_projects_delete', methods: ['DELETE'])]
    public function delete(Project $project): JsonResponse
    {
        // Supprimer le projet de la base de données
        $this->entityManager->remove($project);
        $this->entityManager->flush();

        // Retourner une réponse JSON de succès
        return new JsonResponse([
            'message' => 'Projet supprimé avec succès',
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Assigne un utilisateur à un projet.
     *
     * @param Project $project
     * @param int $userId
     * @return JsonResponse
     */
    #[Route('/{id}/assign-user/{userId}', name: 'api_projects_assign_user', methods: ['POST'])]
    public function assignUser(Project $project, int $userId): JsonResponse
    {
        // Récupérer l'utilisateur par son ID
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Associer l'utilisateur au projet
        $project->addUser($user);
        $this->entityManager->flush();

        // Retourner une réponse JSON de succès
        return new JsonResponse([
            'message' => 'Utilisateur assigné au projet avec succès',
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Retire un utilisateur d'un projet.
     *
     * @param Project $project
     * @param int $userId
     * @return JsonResponse
     */
    #[Route('/{id}/remove-user/{userId}', name: 'api_projects_remove_user', methods: ['POST'])]
    public function removeUser(Project $project, int $userId): JsonResponse
    {
        // Récupérer l'utilisateur par son ID
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Retirer l'utilisateur du projet
        $project->removeUser($user);
        $this->entityManager->flush();

        // Retourner une réponse JSON de succès
        return new JsonResponse([
            'message' => 'Utilisateur retiré du projet avec succès',
        ], JsonResponse::HTTP_OK);
    }
}
