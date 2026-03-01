<?php

namespace app\modules\api\v1\controllers;

use app\services\SyncService;

class SyncController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'pull' => ['GET'],
            'push' => ['POST'],
        ];
    }

    public function actionPull(?string $since = null): array
    {
        $service = new SyncService();
        $payload = $service->pull($this->userId(), $since);

        return $this->success($payload);
    }

    public function actionPush(): array
    {
        $payload = $this->bodyParams();
        $changes = (array) ($payload['changes'] ?? []);
        $deletions = (array) ($payload['deletions'] ?? []);
        $since = isset($payload['since']) ? (string) $payload['since'] : null;

        $service = new SyncService();
        $result = $service->push($this->userId(), $changes, $deletions, $since);

        return $this->success($result);
    }
}
