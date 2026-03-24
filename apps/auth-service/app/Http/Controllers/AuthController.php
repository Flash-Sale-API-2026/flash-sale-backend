<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RefreshSessionAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthSessionResource;
use App\Http\Resources\MessageResource;
use App\Services\Auth\InvalidCredentialsException;
use App\Services\Auth\InvalidRefreshTokenException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUserAction): Response
    {
        $session = $registerUserAction($request->validated(), $request);

        return (new AuthSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginUserAction $loginUserAction): Response
    {
        try {
            $session = $loginUserAction($request->email(), $request->password(), $request);
        } catch (InvalidCredentialsException $exception) {
            return (new MessageResource([
                'message' => $exception->getMessage(),
            ]))
                ->response()
                ->setStatusCode(Response::HTTP_UNAUTHORIZED);
        }

        return (new AuthSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function refresh(RefreshTokenRequest $request, RefreshSessionAction $refreshSessionAction): Response
    {
        try {
            $session = $refreshSessionAction($request->refreshToken(), $request);
        } catch (InvalidRefreshTokenException $exception) {
            return (new MessageResource([
                'message' => $exception->getMessage(),
            ]))
                ->response()
                ->setStatusCode(Response::HTTP_UNAUTHORIZED);
        }

        return (new AuthSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
