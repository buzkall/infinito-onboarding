<?php

namespace Arzcode\InfinitoOnboarding\Livewire;

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Record mode: the visual step author. Rendered instead of the overlay when
 * an authorised user visits a page with `?onboarding-record=<tour-key>`.
 */
class TourRecorder extends Component
{
    #[Locked]
    public int $tourId;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function getTour(): Tour
    {
        return Tour::query()->with('steps')->findOrFail($this->tourId);
    }

    /**
     * Replace the tour's steps with the given list (in order). Existing steps
     * keep their ids when the payload references them; everything else is
     * recreated.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>> The saved steps as payloads
     */
    public function saveSteps(array $steps): array
    {
        $this->authorizeAccess();

        $validated = Validator::make(['steps' => $steps], [
            'steps' => ['array'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.body' => ['nullable', 'string'],
            'steps.*.placement' => ['nullable', 'in:' . implode(',', array_column(Placement::cases(), 'value'))],
            'steps.*.target_type' => ['required', 'in:' . implode(',', array_column(TargetType::cases(), 'value'))],
            'steps.*.target' => ['nullable', 'string', 'max:255', 'required_unless:steps.*.target_type,' . TargetType::None->value],
            'steps.*.extra' => ['nullable', 'array'],
        ])->validate()['steps'] ?? [];

        $tour = $this->getTour();

        DB::transaction(function () use ($tour, $validated): void {
            $keptIds = [];

            foreach (array_values($validated) as $order => $data) {
                $targetType = TargetType::from($data['target_type']);

                $attributes = [
                    'order' => $order + 1,
                    'title' => $data['title'],
                    'body' => $data['body'] ?? null,
                    'placement' => Placement::tryFrom($data['placement'] ?? 'auto') ?? Placement::Auto,
                    'target_type' => $targetType,
                    'target' => $targetType === TargetType::None ? null : ($data['target'] ?? null),
                    'extra' => $data['extra'] ?? null,
                ];

                $step = null;

                if (! empty($data['id'])) {
                    $step = $tour->steps()->whereKey($data['id'])->first();
                }

                if ($step instanceof TourStep) {
                    $step->update($attributes);
                } else {
                    $step = $tour->steps()->create($attributes);
                }

                $keptIds[] = $step->getKey();
            }

            $tour->steps()->whereKeyNot($keptIds)->delete();
        });

        return $this->getTour()->steps->map(fn (TourStep $step): array => $this->stepPayload($step))->values()->all();
    }

    public function render(): View
    {
        $tour = $this->getTour();

        /** @var view-string $view */
        $view = 'infinito-onboarding::livewire.tour-recorder';

        return view($view, [
            'tour' => $tour,
            'tourPayload' => TourOverlay::payloadFor($tour)['tour'],
            'steps' => $tour->steps->map(fn (TourStep $step): array => $this->stepPayload($step))->values()->all(),
            'labels' => static::labels(),
            'exitUrl' => static::exitUrl(request()),
            'scriptSrc' => FilamentAsset::getScriptSrc('infinito-onboarding-recorder', InfinitoOnboardingServiceProvider::$assetPackage),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers shared with the render hook
    |--------------------------------------------------------------------------
    */

    public static function isRecordRequest(InfinitoOnboardingPlugin $plugin, Request $request): bool
    {
        if (! $request->filled(static::recordParameter())) {
            return false;
        }

        return $plugin->isAuthorized();
    }

    /**
     * The tour to record, created as an unpublished draft (scoped to the
     * current path) when the key is unknown so authors can start clicking
     * straight away.
     */
    public static function resolveTourForRequest(Request $request): ?Tour
    {
        $key = $request->query(static::recordParameter());

        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_\-.]+$/', $key)) {
            return null;
        }

        return Tour::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => Str::headline($key),
                'route_pattern' => trim($request->path(), '/'),
                'published_at' => null,
                'is_active' => true,
            ],
        );
    }

    public static function recordParameter(): string
    {
        return (string) config('infinito-onboarding.query_parameters.record', 'onboarding-record');
    }

    public static function exitUrl(Request $request): string
    {
        $query = $request->query();
        unset($query[static::recordParameter()]);

        return $request->url() . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function labels(): array
    {
        return [
            'title' => __('infinito-onboarding::onboarding.recorder.title'),
            'pick' => __('infinito-onboarding::onboarding.recorder.pick'),
            'picking' => __('infinito-onboarding::onboarding.recorder.picking'),
            'centred' => __('infinito-onboarding::onboarding.recorder.centred'),
            'empty' => __('infinito-onboarding::onboarding.recorder.empty'),
            'preview' => __('infinito-onboarding::onboarding.recorder.preview'),
            'save' => __('infinito-onboarding::onboarding.recorder.save'),
            'saving' => __('infinito-onboarding::onboarding.recorder.saving'),
            'saved' => __('infinito-onboarding::onboarding.recorder.saved'),
            'save_failed' => __('infinito-onboarding::onboarding.recorder.save_failed'),
            'exit' => __('infinito-onboarding::onboarding.recorder.exit'),
            'confirm_exit' => __('infinito-onboarding::onboarding.recorder.confirm_exit'),
            'step_title' => __('infinito-onboarding::onboarding.recorder.step_title'),
            'step_body' => __('infinito-onboarding::onboarding.recorder.step_body'),
            'step_placement' => __('infinito-onboarding::onboarding.recorder.step_placement'),
            'target' => __('infinito-onboarding::onboarding.recorder.target'),
            'no_target' => __('infinito-onboarding::onboarding.recorder.no_target'),
            'retarget' => __('infinito-onboarding::onboarding.recorder.retarget'),
            'add_step' => __('infinito-onboarding::onboarding.recorder.add_step'),
            'update_step' => __('infinito-onboarding::onboarding.recorder.update_step'),
            'cancel' => __('infinito-onboarding::onboarding.recorder.cancel'),
            'edit' => __('infinito-onboarding::onboarding.recorder.edit'),
            'remove' => __('infinito-onboarding::onboarding.recorder.remove'),
            'unsaved' => __('infinito-onboarding::onboarding.recorder.unsaved'),
            'before' => __('infinito-onboarding::onboarding.recorder.before'),
            'before_pick' => __('infinito-onboarding::onboarding.recorder.before_pick'),
            'before_help' => __('infinito-onboarding::onboarding.recorder.before_help'),
            'advance_on_click' => __('infinito-onboarding::onboarding.recorder.advance_on_click'),
            'placements' => collect(Placement::cases())->mapWithKeys(fn (Placement $placement): array => [$placement->value => $placement->getLabel()])->all(),
            'tour' => TourOverlay::labels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function stepPayload(TourStep $step): array
    {
        $extra = $step->extra ?? [];

        return [
            ...$step->toPayload(),
            'score' => $extra['score'] ?? ($step->target_type === TargetType::DataTour ? 'green' : ($step->target_type === TargetType::None ? null : 'amber')),
            'strategy' => $extra['strategy'] ?? null,
            'hint' => null,
        ];
    }

    protected function authorizeAccess(): void
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('infinito-onboarding')) {
            throw new HttpException(403, 'Record mode is not available in this panel.');
        }

        /** @var InfinitoOnboardingPlugin $plugin */
        $plugin = $panel->getPlugin('infinito-onboarding');

        if (! $plugin->isAuthorized()) {
            throw new HttpException(403, 'You are not allowed to record tours.');
        }
    }
}
