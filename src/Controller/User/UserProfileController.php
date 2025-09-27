<?php

namespace App\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user', name: 'user_')]
final class UserProfileController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {

      /** @var \App\Entity\User $user */

        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'status' => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $this->json([
            'status' => 200,
            'message' => 'User profile',
            'data' => [
                'id'        => $user->getId(),
                'phone'     => $user->getPhone(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'status'    => $user->getStatus(),
                'isVerified'=> $user->isVerified(),
                'roles'     => $user->getRoles(),
            ],
        ]);
    }
}
