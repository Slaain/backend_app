<?php

namespace App\Controller;

use App\Entity\Note;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NoteController extends AbstractController
{
    #[Route('/notes/project/{id}', name: 'get_project_notes', methods: ['GET'])]
    public function getNotes(EntityManagerInterface $em, int $id): JsonResponse
    {
        // Récupérer le projet avec ses notes
        $query = $em->createQuery(
            'SELECT n.id, n.content, n.users, n.createdAt
         FROM App\Entity\Note n
         WHERE n.project = :projectId'
        )->setParameter('projectId', $id);

        $notes = $query->getResult();

        // Retour brut des notes en JSON
        return $this->json($notes);
    }



    #[Route('/notes/project/{id}', name: 'add_project_note', methods: ['POST'])]
    public function addNote(Request $request, Project $project, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], 401);
        }

        if (!isset($data['content']) || empty($data['content'])) {
            return $this->json(['error' => 'Le contenu de la note est obligatoire.'], 400);
        }

        $note = new Note();
        $note->setContent($data['content']);
        $note->setUsers($user->getUsername());
        $note->setCreatedAt(new \DateTimeImmutable());
        $note->setProject($project);

        $em->persist($note);
        $em->flush();

        return $this->json($note, 201, [], ['groups' => 'note:read']);
    }

    #[Route('/notes/{id}', name: 'delete_note', methods: ['DELETE'])]
    public function deleteNote(Note $note, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($note);
        $em->flush();

        return $this->json(['message' => 'Note supprimée avec succès'], 200);
    }

    #[Route('/notes/user/{id}', name: 'get_user_notes', methods: ['GET'])]
    public function getUserNotes(EntityManagerInterface $em, int $id): JsonResponse
    {
        // Récupérer les notes de l'utilisateur
        $query = $em->createQuery(
            'SELECT n.id, n.content, n.users, n.createdAt
         FROM App\Entity\Note n
         WHERE n.users = :username'
        )->setParameter('username', $this->getUser()->getUsername());

        $notes = $query->getResult();

        // Retour brut des notes en JSON
        return $this->json($notes);
    }
}
