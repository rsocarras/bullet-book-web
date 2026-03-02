<?php

use yii\db\Migration;
use yii\db\Expression;
use yii\db\Query;

class m260301_055108_seed_bullet_book_system_templates extends Migration
{
    public function safeUp()
    {
        // Forzar collation de sesión (evita mezclas raras)
        $this->execute("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
        $this->execute("SET collation_connection = 'utf8mb4_general_ci'");

        $templateSchema = $this->db->schema->getTableSchema('bb_template', true);
        $bulletSchema   = $this->db->schema->getTableSchema('bb_bullet', true);
        $tbSchema       = $this->db->schema->getTableSchema('bb_template_bullet', true);

        if (!$templateSchema || !$bulletSchema || !$tbSchema) {
            throw new \RuntimeException("Faltan tablas: bb_template, bb_bullet o bb_template_bullet.");
        }

        $tCols  = $templateSchema->getColumnNames();
        $bCols  = $bulletSchema->getColumnNames();
        $tbCols = $tbSchema->getColumnNames();

        $hasT = function ($c) use ($tCols) { return in_array($c, $tCols, true); };
        $hasB = function ($c) use ($bCols) { return in_array($c, $bCols, true); };
        $hasTB = function ($c) use ($tbCols) { return in_array($c, $tbCols, true); };

        // Resolver "System User" si bb_bullet.user_id es NOT NULL
        $systemUserId = null;
        if ($hasB('user_id')) {
            $userCol = $bulletSchema->columns['user_id'];
            if ($userCol && $userCol->allowNull === false) {
                $systemUserId = (new Query())->from('user')->min('id');
                if (!$systemUserId) {
                    throw new \RuntimeException("bb_bullet.user_id es NOT NULL y no hay usuarios en tabla user. Crea un usuario primero.");
                }
            }
        }

        // Labels estándar (se guardan como JSON string si existe columna)
        $labelsGoodBad = json_encode([
            '1' => 'Muy malo',
            '2' => 'Malo',
            '3' => 'Regular',
            '4' => 'Bueno',
            '5' => 'Muy bueno',
        ], JSON_UNESCAPED_UNICODE);

        $labelsLowHigh = json_encode([
            '1' => 'Muy bajo',
            '2' => 'Bajo',
            '3' => 'Medio',
            '4' => 'Alto',
            '5' => 'Muy alto',
        ], JSON_UNESCAPED_UNICODE);

        // =========================
        // CATÁLOGO DE TEMPLATES MVP
        // =========================
        $templates = [
            [
                'name' => 'Salud integral',
                'description' => 'Track diario de sueño, agua, actividad y señales del cuerpo.',
                'bullets' => [
                    ['name'=>'Sueño (horas)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Calidad de sueño', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Hidratación (vasos)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Pasos', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Dolor corporal', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Medicación o suplementos', 'type'=>'habit', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Sueño y recuperación',
                'description' => 'Rutina y métricas para dormir mejor y recuperar energía.',
                'bullets' => [
                    ['name'=>'Hora de dormir', 'type'=>'habit', 'input'=>'text'],
                    ['name'=>'Hora de despertar', 'type'=>'habit', 'input'=>'text'],
                    ['name'=>'Sueño (horas)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Calidad de sueño', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Siesta', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Pantallas antes de dormir', 'type'=>'habit', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Energía y fatiga',
                'description' => 'Mide energía, cafeína, pausas y fatiga durante el día.',
                'bullets' => [
                    ['name'=>'Energía - mañana', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Energía - tarde', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Energía - noche', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Cafeína (tazas)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Pausas activas', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Cansancio', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                ],
            ],
            [
                'name' => 'Nutrición simple',
                'description' => 'Hábitos simples de alimentación para mantener consistencia.',
                'bullets' => [
                    ['name'=>'Desayuno saludable', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Frutas/verduras (porciones)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Ultraprocesados', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Proteína suficiente', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Comer tarde', 'type'=>'habit', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Fitness básico',
                'description' => 'Entrenamiento, intensidad y recuperación post sesión.',
                'bullets' => [
                    ['name'=>'Entrenamiento', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Tipo de entrenamiento', 'type'=>'habit', 'input'=>'text'],
                    ['name'=>'Intensidad de entrenamiento', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Estiramiento', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Dolor post-entreno', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Tiempo de entrenamiento (min)', 'type'=>'habit', 'input'=>'numeric'],
                ],
            ],
            [
                'name' => 'Mood diario',
                'description' => 'Check-in emocional simple con nota corta.',
                'bullets' => [
                    ['name'=>'Estado de ánimo', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Estrés', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Ansiedad', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Gratitud', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Nota del día', 'type'=>'feeling', 'input'=>'text'],
                ],
            ],
            [
                'name' => 'Productividad diaria',
                'description' => 'Planificación, foco, distracciones y cierre del día.',
                'bullets' => [
                    ['name'=>'Plan del día', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'MIT completada', 'type'=>'goal', 'input'=>'binary'],
                    ['name'=>'Deep work (bloques)', 'type'=>'habit', 'input'=>'numeric'],
                    ['name'=>'Distracciones', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Cierre del día', 'type'=>'habit', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Finanzas: control diario',
                'description' => 'Gasto diario, impulsos y ahorro.',
                'bullets' => [
                    ['name'=>'Gasté hoy', 'type'=>'finance', 'input'=>'binary'],
                    ['name'=>'Total gastado', 'type'=>'finance', 'input'=>'numeric'],
                    ['name'=>'Categoría principal', 'type'=>'finance', 'input'=>'text'],
                    ['name'=>'Compra impulsiva', 'type'=>'finance', 'input'=>'binary'],
                    ['name'=>'Ahorré hoy', 'type'=>'finance', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Morning Routine',
                'description' => 'Rutina de mañana para arrancar el día con intención.',
                'bullets' => [
                    ['name'=>'Me levanté a tiempo', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Agua al despertar', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Ejercicio corto', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Plan del día', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Mood - mañana', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                ],
            ],
            [
                'name' => 'Evening Routine',
                'description' => 'Rutina de noche para cerrar el día y dormir mejor.',
                'bullets' => [
                    ['name'=>'Cierre del día', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Preparé mañana', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Pantallas 30 min antes', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Lectura nocturna', 'type'=>'habit', 'input'=>'binary'],
                    ['name'=>'Sueño objetivo', 'type'=>'goal', 'input'=>'binary'],
                ],
            ],
            [
                'name' => 'Year in Pixels',
                'description' => 'Escalas 1–5 para visualizar patrones en un heatmap anual.',
                'bullets' => [
                    ['name'=>'Estado de ánimo', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Energía general', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Estrés', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsLowHigh],
                    ['name'=>'Salud general', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                    ['name'=>'Productividad general', 'type'=>'feeling', 'input'=>'scale', 'min'=>1, 'max'=>5, 'labels'=>$labelsGoodBad],
                ],
            ],
        ];

        // === Helpers ===
        $now = new Expression("UTC_TIMESTAMP()");

        $ensureTemplate = function ($name, $description) use ($hasT, $now) {
            $q = (new Query())->from('bb_template')->select(['id'])
                ->where(['name' => $name])
                ->andWhere(['deleted_at' => null]);

            if ($hasT('is_system')) {
                $q->andWhere(['is_system' => 1]);
            } elseif ($hasT('owner_user_id')) {
                $q->andWhere(['owner_user_id' => null]);
            }

            $id = $q->scalar($this->db);
            if ($id) return (int)$id;

            $row = ['name' => $name];

            if ($hasT('description')) $row['description'] = $description;
            if ($hasT('owner_user_id')) $row['owner_user_id'] = null;
            if ($hasT('is_system')) $row['is_system'] = 1;
            if ($hasT('is_public')) $row['is_public'] = 1;
            if ($hasT('created_at')) $row['created_at'] = $now;
            if ($hasT('updated_at')) $row['updated_at'] = $now;
            if ($hasT('deleted_at')) $row['deleted_at'] = null;

            $this->insert('bb_template', $row);
            return (int)$this->db->getLastInsertID();
        };

        $ensureBullet = function (array $b) use ($hasB, $now, $systemUserId) {
            $name = $b['name'];

            $q = (new Query())->from('bb_bullet')->select(['id'])
                ->where(['name' => $name]);

            if ($hasB('deleted_at')) $q->andWhere(['deleted_at' => null]);

            // Zona "system" por user_id: NULL si se puede, o systemUserId si es NOT NULL
            if ($hasB('user_id')) {
                if ($systemUserId === null) {
                    $q->andWhere(['user_id' => null]);
                } else {
                    $q->andWhere(['user_id' => (int)$systemUserId]);
                }
            }

            $id = $q->scalar($this->db);
            if ($id) return (int)$id;

            $row = [
                'name' => $name,
                'bullet_type' => $b['type'],
                'input_type'  => $b['input'],
            ];

            if ($hasB('user_id')) $row['user_id'] = $systemUserId; // null si permite null; int si no
            if ($hasB('is_active')) $row['is_active'] = 1;

            if ($hasB('scale_min')) $row['scale_min'] = isset($b['min']) ? (int)$b['min'] : null;
            if ($hasB('scale_max')) $row['scale_max'] = isset($b['max']) ? (int)$b['max'] : null;
            if ($hasB('scale_labels')) $row['scale_labels'] = isset($b['labels']) ? (string)$b['labels'] : null;

            // Defaults visuales (solo si existen columnas)
            if ($hasB('color')) $row['color'] = $this->inferColor($b['type']);
            if ($hasB('icon'))  $row['icon']  = $this->inferIcon($name, $b['type']);
            if ($hasB('weight')) $row['weight'] = $this->inferWeight($b['type']);

            if ($hasB('created_at')) $row['created_at'] = $now;
            if ($hasB('updated_at')) $row['updated_at'] = $now;
            if ($hasB('deleted_at')) $row['deleted_at'] = null;

            $this->insert('bb_bullet', $row);
            return (int)$this->db->getLastInsertID();
        };

        $ensureTemplateBullet = function ($templateId, $bulletId, $sortOrder) use ($hasTB, $now) {
            $q = (new Query())->from('bb_template_bullet')->select(['template_id'])
                ->where(['template_id' => $templateId, 'bullet_id' => $bulletId]);

            $exists = (bool)$q->scalar($this->db);

            if (!$exists) {
                $row = [
                    'template_id' => $templateId,
                    'bullet_id'   => $bulletId,
                ];
                if ($hasTB('sort_order')) $row['sort_order'] = (int)$sortOrder;
                if ($hasTB('is_default_active')) $row['is_default_active'] = 1;
                if ($hasTB('created_at')) $row['created_at'] = $now;
                if ($hasTB('updated_at')) $row['updated_at'] = $now;
                if ($hasTB('deleted_at')) $row['deleted_at'] = null;

                $this->insert('bb_template_bullet', $row);
            } else {
                $row = [];
                if ($hasTB('sort_order')) $row['sort_order'] = (int)$sortOrder;
                if ($hasTB('is_default_active')) $row['is_default_active'] = 1;
                if ($hasTB('updated_at')) $row['updated_at'] = $now;
                if ($hasTB('deleted_at')) $row['deleted_at'] = null;

                if (!empty($row)) {
                    $this->update('bb_template_bullet', $row, [
                        'template_id' => $templateId,
                        'bullet_id'   => $bulletId
                    ]);
                }
            }
        };

        // =========================
        // EJECUCIÓN: Seed completo
        // =========================
        foreach ($templates as $tpl) {
            $templateId = $ensureTemplate($tpl['name'], $tpl['description']);

            $order = 10;
            foreach ($tpl['bullets'] as $b) {
                $bulletId = $ensureBullet($b);
                $ensureTemplateBullet($templateId, $bulletId, $order);
                $order += 10;
            }
        }
    }

    public function safeDown()
    {
        // Seed normalmente no se revierte automáticamente (para no borrar data real).
        echo "m260301_055108_seed_bullet_book_system_templates cannot be reverted safely.\n";
        return false;
    }

    // ===== Helpers visuales =====
    private function inferColor($type)
    {
        switch ($type) {
            case 'habit': return '#10B981';
            case 'feeling': return '#8B5CF6';
            case 'finance': return '#F59E0B';
            case 'goal': return '#3B82F6';
            default: return '#6B7280';
        }
    }

    private function inferWeight($type)
    {
        switch ($type) {
            case 'goal': return 1.20;
            case 'finance': return 1.10;
            default: return 1.00;
        }
    }

    private function inferIcon($name, $type)
    {
        // Mapeo por nombre (si no coincide, fallback por tipo)
        $map = [
            'Sueño (horas)' => 'moon',
            'Calidad de sueño' => 'bed',
            'Hidratación (vasos)' => 'droplet',
            'Pasos' => 'footprints',
            'Dolor corporal' => 'heart-pulse',
            'Medicación o suplementos' => 'pill',
            'Hora de dormir' => 'clock',
            'Hora de despertar' => 'alarm-clock',
            'Pantallas antes de dormir' => 'smartphone',
            'Energía - mañana' => 'battery',
            'Energía - tarde' => 'battery',
            'Energía - noche' => 'battery',
            'Cafeína (tazas)' => 'coffee',
            'Pausas activas' => 'timer',
            'Cansancio' => 'battery-low',
            'Entrenamiento' => 'dumbbell',
            'Tipo de entrenamiento' => 'tag',
            'Intensidad de entrenamiento' => 'flame',
            'Estiramiento' => 'move',
            'Tiempo de entrenamiento (min)' => 'timer',
            'Estado de ánimo' => 'smile',
            'Estrés' => 'alert-triangle',
            'Ansiedad' => 'wind',
            'Gratitud' => 'heart',
            'Nota del día' => 'sticky-note',
            'Plan del día' => 'check-square',
            'MIT completada' => 'target',
            'Deep work (bloques)' => 'target',
            'Distracciones' => 'zap',
            'Cierre del día' => 'check',
            'Gasté hoy' => 'credit-card',
            'Total gastado' => 'dollar-sign',
            'Categoría principal' => 'tag',
            'Compra impulsiva' => 'shopping-cart',
            'Ahorré hoy' => 'piggy-bank',
            'Me levanté a tiempo' => 'alarm-clock',
            'Agua al despertar' => 'droplet',
            'Ejercicio corto' => 'dumbbell',
            'Mood - mañana' => 'sun',
            'Preparé mañana' => 'calendar',
            'Pantallas 30 min antes' => 'smartphone',
            'Lectura nocturna' => 'book-open',
            'Sueño objetivo' => 'moon',
            'Energía general' => 'battery',
            'Salud general' => 'heart-pulse',
            'Productividad general' => 'target',
        ];

        if (isset($map[$name])) return $map[$name];

        switch ($type) {
            case 'habit': return 'check-circle';
            case 'feeling': return 'smile';
            case 'finance': return 'wallet';
            case 'goal': return 'target';
            default: return 'dot';
        }
    }
}
