<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_users_')]
final class UserAdminController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(function ($user) {
            return [
                'id'        => $user->getId(),
                'phone'     => $user->getPhone(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'status'    => $user->getStatus(),
                'isVerified'=> $user->isVerified(),
                'roles'     => $user->getRoles(),
            ];
        }, $users);

        return $this->json([
            'status'  => 200,
            'message' => 'List of users',
            'data'    => $data,
        ]);
    }
}
