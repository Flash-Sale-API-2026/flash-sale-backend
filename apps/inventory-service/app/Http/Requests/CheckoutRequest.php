<?php

namespace App\Http\Requests;

use App\Http\Resources\MessageResource;
use App\Services\Auth\GatewayUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GatewayUserResolver::class)->rawUserId($this) !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'authenticated_user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function userId(): int
    {
        return (int) $this->validated('authenticated_user_id');
    }

    /**
     * @return array<string, string|null>
     */
    public function validationData(): array
    {
        return [
            'authenticated_user_id' => app(GatewayUserResolver::class)->rawUserId($this),
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            (new MessageResource([
                'message' => 'Only authenticated users can reserve seats.',
            ]))
                ->response()
                ->setStatusCode(401)
        );
    }
}
