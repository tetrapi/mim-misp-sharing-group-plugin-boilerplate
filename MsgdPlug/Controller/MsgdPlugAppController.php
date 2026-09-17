<?php

declare(strict_types=1);

App::uses('CakeResponse', 'Network');
App::uses('AppController', 'Controller');

/**
 * Base controller for MsgdPlug. Sets up security headers and JSON response helpers.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Controller
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
class MsgdPlugAppController extends AppController
{
    public $RequestHandler = null;
    public $Auth = null;
    public $Security = null;

    /**
     * Framework components used across plugin endpoints.
     *
     * @var array<int, string>
     */
    public $components = [
        'RequestHandler',
        'Auth',
        'Security',
    ];

    /**
     * Sets default HTTP security headers and restricts unauthenticated access.
     *
     * @return void
     */
    public function beforeFilter(): void
    {
        parent::beforeFilter();

        if ($this->response !== null) {
            $this->response->header('X-Content-Type-Options', 'nosniff');
            $this->response->header('X-Frame-Options', 'SAMEORIGIN');
            $this->response->header('X-XSS-Protection', '1; mode=block');
            $this->response->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $this->response->header('Pragma', 'no-cache');
        }

        $this->Auth?->deny('*');

        if ($this->Security !== null) {
            $this->Security->csrfCheck = true;
            $this->Security->validatePost = false; # false because we don't use backend forms
        }
    }

    /**
     * Returns the currently authenticated user as a DTO.
     *
     * @return MsgdUserDTO|null
     */
    protected function getCurrentUser(): ?MsgdUserDTO
    {
        if ($this->Auth === null) {
            return null;
        }

        $user = $this->Auth->user();

        return is_array($user)
            ? MsgdUserDTO::fromArray($user)
            : null;
    }

    /**
     * Checks if the logged-in user has an active and valid account.
     *
     * @return bool
     */
    protected function isAuthenticatedUserValid(): bool
    {
        $user = $this->getCurrentUser();

        return $user !== null
            && $user->id > 0
            && $user->orgId > 0
            && !$user->disabled;
    }

    /**
     * Confirms the request is an authenticated AJAX call.
     *
     * @return bool
     */
    protected function validateRequest(): bool
    {
        return $this->isAuthenticatedUserValid() && $this->request->is('ajax');
    }

    /**
     * Appends the CSRF token to the response payload if available.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function appendNextToken(array $payload): array
    {
        $nextToken = $this->request->params['_Token']['key'] ?? null;

        if ($nextToken !== null) {
            $payload['nextToken'] = (string)$nextToken;
        }

        return $payload;
    }

    /**
     * Builds a standardized JSON response.
     *
     * @param array<string, mixed> $responsePayload
     * @param int $httpStatusCode
     *
     * @return CakeResponse
     */
    protected function buildJsonResponse(
        array $responsePayload,
        int $httpStatusCode = 200
    ): CakeResponse {
        $this->autoRender = false;
        $this->response->type('json');
        $this->response->statusCode($httpStatusCode);

        try {
            $encodedJsonPayload = json_encode(
                $responsePayload,
                JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
            );

            $this->response->body($encodedJsonPayload);
        } catch (JsonException $jsonException) {
            MsgdLoggerUtility::logException(
                $jsonException,
                '[MsgdPlug AppController: buildJsonResponse] JSON Encoding Failure'
            );

            $this->response->statusCode(500);
            $this->response->body(
                '{"status":"error","message":"Internal serialization error."}'
            );
        }

        return $this->response;
    }
}
