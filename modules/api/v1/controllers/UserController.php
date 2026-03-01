<?php

namespace app\modules\api\v1\controllers;

use DateTimeZone;
use Yii;
use yii\web\ServerErrorHttpException;
use app\services\OnboardingService;

class UserController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'setup' => ['POST'],
        ];
    }

    public function actionSetup(): array
    {
        $payload = $this->bodyParams();
        $templateIds = $payload['template_ids'] ?? null;
        $timezone = isset($payload['timezone']) ? trim((string) $payload['timezone']) : null;

        $errors = [];

        if (!is_array($templateIds) || empty($templateIds)) {
            $errors['template_ids'][] = 'template_ids is required and must contain at least one template id.';
        }

        if (is_array($templateIds)) {
            $validIds = array_filter(array_map(static fn($id) => (int) $id, $templateIds), static fn($id) => $id > 0);
            if (empty($validIds)) {
                $errors['template_ids'][] = 'template_ids must contain valid numeric ids.';
            }
        }

        if ($timezone !== null && $timezone !== '') {
            try {
                new DateTimeZone($timezone);
            } catch (\Throwable $e) {
                $errors['timezone'][] = 'Invalid timezone identifier.';
            }
        }

        if (!empty($errors)) {
            return $this->failValidation($errors, 422);
        }

        try {
            $service = new OnboardingService();
            $data = $service->runSetup($this->userId(), (array) $templateIds, $timezone);

            Yii::$app->response->statusCode = 200;
            return [
                'success' => true,
                'message' => 'Onboarding completed',
                'data' => $data,
            ];
        } catch (\yii\web\NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Yii::error('User setup failed: ' . $e->getMessage(), __METHOD__);
            throw new ServerErrorHttpException('Could not complete onboarding setup.');
        }
    }
}
