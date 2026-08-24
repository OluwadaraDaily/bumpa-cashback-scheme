<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AchievementStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AchievementStatusController extends Controller
{
    public function __construct(private readonly AchievementStatusService $status) {}

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless(
            $request->user()->id === $user->id || $request->user()->is_admin,
            Response::HTTP_FORBIDDEN,
        );

        return response()->json($this->status->getFor($user));
    }
}
