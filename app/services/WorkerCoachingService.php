<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\LeadingIndicatorRepository;
use WorkEddy\Repositories\ScanRepository;

final class WorkerCoachingService
{
    /** @var array<string,array<string,mixed>> */
    private const I18N = [
        'en' => [
            'label' => 'English',
            'tips' => [
                'start_checkin' => ['Build your baseline', 'Submit a pre-shift check-in to personalize coaching for today.'],
                'high_discomfort' => ['Escalate discomfort early', 'Your discomfort trend is high. Report symptoms early and request task adjustment.'],
                'high_fatigue' => ['Use recovery windows', 'Fatigue is elevated. Add a short recovery break before high-force work blocks.'],
                'micro_breaks_low' => ['Increase micro-breaks', 'Take at least one 60-90 second recovery pause every 30-45 minutes.'],
                'rotation_poor' => ['Improve rotation quality', 'Ask your supervisor for a rotation that alternates high and low load tasks.'],
                'psychosocial_high' => ['Flag workload pressure', 'Psychosocial load is high. Raise staffing or pacing concerns during huddle.'],
                'high_risk_scan' => ['Follow top control now', 'Your latest scan is high risk. Start the highest ranked control and verify with a repeat scan.'],
                'assigned_action' => ['Close assigned actions', 'You have assigned control actions pending. Completing them improves verification evidence.'],
                'lift_tip' => ['Lift tip', 'Keep loads close, avoid trunk twist at pickup, and pivot your feet instead of twisting your back.'],
                'workstation_tip' => ['Workstation tip', 'Set work height near elbow level and keep frequent reach items in the shoulder-safe zone.'],
            ],
            'pre_shift_checks' => [
                'Do I feel discomfort above 4/10 before starting?',
                'Will I have micro-breaks scheduled this shift?',
                'Do I know today’s top control action for my task?',
            ],
        ],
        'es' => [
            'label' => 'Español',
            'tips' => [
                'start_checkin' => ['Crea tu línea base', 'Envía un chequeo previo al turno para personalizar la guía de hoy.'],
                'high_discomfort' => ['Escala la molestia temprano', 'Tu tendencia de molestia es alta. Reporta síntomas y solicita ajuste de tarea.'],
                'high_fatigue' => ['Usa pausas de recuperación', 'La fatiga está elevada. Agrega una pausa corta antes de tareas de alta fuerza.'],
                'micro_breaks_low' => ['Aumenta micro-pausas', 'Toma una pausa de 60-90 segundos cada 30-45 minutos.'],
                'rotation_poor' => ['Mejora la rotación', 'Pide una rotación que alterne tareas de alta y baja carga.'],
                'psychosocial_high' => ['Reporta presión de trabajo', 'La carga psicosocial es alta. Comenta necesidades de ritmo o personal en la reunión.'],
                'high_risk_scan' => ['Aplica el control principal', 'Tu último escaneo es de alto riesgo. Implementa el control mejor clasificado y verifica con un nuevo escaneo.'],
                'assigned_action' => ['Cierra acciones asignadas', 'Tienes acciones de control pendientes. Completarlas mejora la evidencia de verificación.'],
                'lift_tip' => ['Consejo de levantamiento', 'Mantén la carga cerca del cuerpo, evita girar el tronco y pivota con los pies.'],
                'workstation_tip' => ['Consejo de estación', 'Ajusta la altura cerca del codo y mantén objetos frecuentes en zona segura de hombro.'],
            ],
            'pre_shift_checks' => [
                '¿Siento molestia por encima de 4/10 antes de empezar?',
                '¿Tengo micro-pausas planificadas para este turno?',
                '¿Conozco la acción de control principal para mi tarea de hoy?',
            ],
        ],
        'zh' => [
            'label' => '中文',
            'tips' => [
                'start_checkin' => ['建立基线', '请先提交班前自检，以便系统生成个性化建议。'],
                'high_discomfort' => ['尽早上报不适', '你的不适趋势偏高。请尽早反馈症状并申请任务调整。'],
                'high_fatigue' => ['安排恢复窗口', '疲劳水平偏高。高强度任务前请先进行短暂恢复。'],
                'micro_breaks_low' => ['增加微休息', '建议每30-45分钟安排一次60-90秒微休息。'],
                'rotation_poor' => ['优化岗位轮换', '请与主管沟通，采用高负荷与低负荷任务交替轮换。'],
                'psychosocial_high' => ['反馈工作压力', '心理社会负荷较高。请在班前会反馈节奏或人手压力。'],
                'high_risk_scan' => ['优先执行首要控制', '你最近一次扫描为高风险。请优先执行最高等级控制并用复扫验证。'],
                'assigned_action' => ['完成已分配行动', '你有待完成的控制行动。完成后可增强验证证据链。'],
                'lift_tip' => ['搬运提示', '搬运时让负荷贴近身体，避免躯干扭转，用脚步转向代替腰部扭转。'],
                'workstation_tip' => ['工位提示', '将作业高度调整到肘部附近，常用物品放在肩部安全区。'],
            ],
            'pre_shift_checks' => [
                '开工前我的不适是否超过4/10？',
                '本班次是否已安排微休息？',
                '我是否清楚今天任务的首要控制行动？',
            ],
        ],
        'ar' => [
            'label' => 'العربية',
            'tips' => [
                'start_checkin' => ['ابنِ خط الأساس', 'أرسل فحص ما قبل الوردية للحصول على إرشادات مخصصة لليوم.'],
                'high_discomfort' => ['صعّد الإحساس بالألم مبكرًا', 'مؤشر الانزعاج لديك مرتفع. أبلغ مبكرًا واطلب تعديل المهمة.'],
                'high_fatigue' => ['استخدم فترات الاستشفاء', 'مستوى الإرهاق مرتفع. أضف استراحة قصيرة قبل المهام عالية الجهد.'],
                'micro_breaks_low' => ['زد فترات الراحة القصيرة', 'خذ راحة 60-90 ثانية كل 30-45 دقيقة.'],
                'rotation_poor' => ['حسّن تدوير المهام', 'اطلب تدويرًا يتناوب بين المهام عالية ومنخفضة الحمل.'],
                'psychosocial_high' => ['أبلغ عن ضغط العمل', 'العبء النفسي مرتفع. ناقش ضغط الوتيرة أو نقص الطاقم في الاجتماع.'],
                'high_risk_scan' => ['نفّذ أعلى إجراء وقائي', 'آخر فحص لديك عالي الخطورة. ابدأ بأعلى إجراء وقائي وحقّق بفحص متابعة.'],
                'assigned_action' => ['أكمل الإجراءات المعيّنة', 'لديك إجراءات وقائية معلّقة. إكمالها يقوّي سلسلة التحقق.'],
                'lift_tip' => ['نصيحة الرفع', 'قرّب الحمل من الجسم، تجنّب لف الجذع، ودوّر بالقدمين بدل الظهر.'],
                'workstation_tip' => ['نصيحة محطة العمل', 'اضبط ارتفاع العمل قرب مستوى المرفق وضع العناصر المتكررة ضمن نطاق كتف آمن.'],
            ],
            'pre_shift_checks' => [
                'هل أشعر بانزعاج أعلى من 4/10 قبل البدء؟',
                'هل تم التخطيط لاستراحات قصيرة خلال الوردية؟',
                'هل أعرف الإجراء الوقائي الأعلى أولوية لمهمتي اليوم؟',
            ],
        ],
    ];

    public function __construct(
        private readonly LeadingIndicatorRepository $indicators,
        private readonly ScanRepository $scans,
        private readonly ControlActionRepository $actions,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function coaching(int $organizationId, int $userId, ?string $language = null): array
    {
        $lang = $this->normalizeLanguage($language);
        $dictionary = self::I18N[$lang];

        $latestIndicator = $this->indicators->latestByUser($organizationId, $userId);
        $latestScan = $this->scans->latestByUser($organizationId, $userId, 'completed');

        $assignedActions = $this->actions->listByOrganization($organizationId, null, null, $userId, 25);
        $openAssignedActions = array_values(array_filter(
            $assignedActions,
            static fn (array $a): bool => in_array((string) ($a['status'] ?? ''), ['planned', 'in_progress', 'implemented'], true)
        ));

        $tipCodes = $this->deriveTipCodes($latestIndicator, $latestScan, $openAssignedActions);
        $tips = [];
        foreach ($tipCodes as $code) {
            $tips[] = $this->buildTip($dictionary, $code);
        }

        return [
            'language' => $lang,
            'language_label' => $dictionary['label'],
            'personalized_tips' => $tips,
            'pre_shift_self_checks' => $dictionary['pre_shift_checks'],
            'evidence' => [
                'latest_indicator_id' => isset($latestIndicator['id']) ? (int) $latestIndicator['id'] : null,
                'latest_indicator_checkin_type' => $latestIndicator['checkin_type'] ?? null,
                'latest_scan_id' => isset($latestScan['id']) ? (int) $latestScan['id'] : null,
                'latest_scan_risk_category' => $latestScan['risk_category'] ?? null,
                'open_assigned_actions' => count($openAssignedActions),
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $indicator
     * @param array<string,mixed>|null $scan
     * @param list<array<string,mixed>> $openActions
     * @return list<string>
     */
    private function deriveTipCodes(?array $indicator, ?array $scan, array $openActions): array
    {
        $codes = [];

        if ($indicator === null) {
            $codes[] = 'start_checkin';
        } else {
            if ((int) ($indicator['discomfort_level'] ?? 0) >= 7) {
                $codes[] = 'high_discomfort';
            }
            if ((int) ($indicator['fatigue_level'] ?? 0) >= 7) {
                $codes[] = 'high_fatigue';
            }
            if ((int) ($indicator['micro_breaks_taken'] ?? 0) <= 1) {
                $codes[] = 'micro_breaks_low';
            }
            if ((string) ($indicator['task_rotation_quality'] ?? '') === 'poor') {
                $codes[] = 'rotation_poor';
            }
            if ((string) ($indicator['psychosocial_load'] ?? '') === 'high') {
                $codes[] = 'psychosocial_high';
            }
        }

        if (is_array($scan) && (string) ($scan['risk_category'] ?? '') === 'high') {
            $codes[] = 'high_risk_scan';
        }

        if ($openActions !== []) {
            $codes[] = 'assigned_action';
        }

        $codes[] = 'lift_tip';
        $codes[] = 'workstation_tip';

        return array_values(array_unique($codes));
    }

    /**
     * @param array<string,mixed> $dictionary
     * @return array<string,mixed>
     */
    private function buildTip(array $dictionary, string $code): array
    {
        $tipMap = is_array($dictionary['tips'] ?? null) ? $dictionary['tips'] : [];
        $tip = is_array($tipMap[$code] ?? null) ? $tipMap[$code] : [$code, $code];

        return [
            'code' => $code,
            'title' => (string) ($tip[0] ?? $code),
            'message' => (string) ($tip[1] ?? $code),
        ];
    }

    private function normalizeLanguage(?string $language): string
    {
        $lang = strtolower(trim((string) $language));
        if (!array_key_exists($lang, self::I18N)) {
            return 'en';
        }
        return $lang;
    }
}
