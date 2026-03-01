<?php

namespace app\controllers;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use app\models\BbBullet;
use app\models\BbBulletEntry;
use app\models\BbReminder;
use app\models\BbTask;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Renders a public landing page for guests and a private dashboard for authenticated users.
     */
    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) {
            return $this->render('landing');
        }

        $userId = (int) Yii::$app->user->id;
        $today = (new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone ?: 'UTC')))->format('Y-m-d');

        $activeBullets = BbBullet::findActiveByUser($userId);
        $todayEntries = BbBulletEntry::findEntriesByDate($userId, $today);
        $hasTodayEntries = BbBulletEntry::hasEntriesForDate($userId, $today);
        $taskStatusCounts = BbTask::countByStatus($userId);
        $upcomingTasks = BbTask::findUpcoming($userId, 7);
        $upcomingReminders = BbReminder::findUpcoming($userId, 5);

        return $this->render('dashboard', [
            'today' => $today,
            'activeBullets' => $activeBullets,
            'todayEntries' => $todayEntries,
            'hasTodayEntries' => $hasTodayEntries,
            'taskStatusCounts' => $taskStatusCounts,
            'upcomingTasks' => $upcomingTasks,
            'upcomingReminders' => $upcomingReminders,
            'heatmap' => $this->buildHeatmapPreview($userId, $activeBullets),
        ]);
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Builds a simple month heatmap matrix for the first active bullet.
     */
    private function buildHeatmapPreview(int $userId, array $activeBullets): array
    {
        if (empty($activeBullets)) {
            return [
                'primaryBullet' => null,
                'monthLabel' => (new DateTimeImmutable('first day of this month'))->format('F Y'),
                'weeks' => [],
            ];
        }

        /** @var BbBullet $primaryBullet */
        $primaryBullet = reset($activeBullets);
        $monthStart = new DateTimeImmutable('first day of this month');
        $monthEnd = new DateTimeImmutable('last day of this month');
        $entries = BbBulletEntry::findMonthEntries(
            $userId,
            (int) $primaryBullet->id,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );

        $indexed = [];
        $numericValues = [];
        foreach ($entries as $entry) {
            $indexed[$entry->entry_date] = $entry;
            if ($entry->value_decimal !== null) {
                $numericValues[] = (float) $entry->value_decimal;
            } elseif ($entry->value_int !== null) {
                $numericValues[] = (float) $entry->value_int;
            }
        }

        $numericMin = !empty($numericValues) ? min($numericValues) : 0.0;
        $numericMax = !empty($numericValues) ? max($numericValues) : 1.0;

        $weeks = [];
        $week = [];
        $leadingBlankDays = (int) $monthStart->format('N') - 1;
        for ($i = 0; $i < $leadingBlankDays; $i++) {
            $week[] = null;
        }

        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $date = $cursor->format('Y-m-d');
            $entry = $indexed[$date] ?? null;

            $week[] = [
                'date' => $date,
                'day' => (int) $cursor->format('j'),
                'hasEntry' => $entry !== null,
                'intensity' => $entry === null ? 0.0 : $this->entryIntensity($primaryBullet, $entry, $numericMin, $numericMax),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->modify('+1 day');
        }

        if (!empty($week)) {
            while (count($week) < 7) {
                $week[] = null;
            }
            $weeks[] = $week;
        }

        return [
            'primaryBullet' => $primaryBullet,
            'monthLabel' => $monthStart->format('F Y'),
            'weeks' => $weeks,
        ];
    }

    private function entryIntensity(BbBullet $bullet, BbBulletEntry $entry, float $numericMin, float $numericMax): float
    {
        switch ($bullet->input_type) {
            case BbBullet::INPUT_BINARY:
                return ((int) $entry->value_int) === 1 ? 1.0 : 0.25;

            case BbBullet::INPUT_SCALE:
            case BbBullet::INPUT_STARS:
                $min = (float) ($bullet->scale_min ?? 1);
                $max = (float) ($bullet->scale_max ?? max(1, $min));
                $value = (float) ($entry->value_int ?? $min);
                if ($max <= $min) {
                    return 1.0;
                }
                return max(0.0, min(1.0, ($value - $min) / ($max - $min)));

            case BbBullet::INPUT_NUMERIC:
                $value = $entry->value_decimal !== null ? (float) $entry->value_decimal : (float) ($entry->value_int ?? 0);
                if ($numericMax <= $numericMin) {
                    return 1.0;
                }
                return max(0.0, min(1.0, ($value - $numericMin) / ($numericMax - $numericMin)));

            case BbBullet::INPUT_TEXT:
                return 0.65;

            default:
                return 0.5;
        }
    }
}
