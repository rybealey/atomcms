<?php

namespace App\Filament\Pages;

use App\Models\Game\Furniture\CatalogPage;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Furniture Manager (Utilities).
 *
 * Lists every furni produced by the Furniture Importer and lets staff edit
 * its behaviour, move its catalog page, rename it, or delete it - each of
 * which flows through the SAME importer-worker / git / deploy pipeline as a
 * fresh import (the panel has no DB or repo access; php open_basedir forbids
 * both). The worker publishes a read-only catalog index into the shared
 * spool (import_spool/_catalog/{index.json, icons/}) which this page reads.
 */
class FurnitureManager extends Page
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilities';

    protected static ?string $navigationLabel = 'Furniture Manager';

    protected string $view = 'filament.pages.furniture-manager';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 24;

    /** Set after an edit/delete is queued so the view can poll job status. */
    public ?string $jobId = null;

    public static function canAccess(): bool
    {
        return hasHousekeepingPermission('can_access_housekeeping');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Furniture Manager';
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /** Whole index (array of sidecar entries), newest worker publish. */
    private function allFurni(): array
    {
        $disk = Storage::disk('import_spool');
        if (! $disk->exists('_catalog/index.json')) {
            return [];
        }

        return json_decode($disk->get('_catalog/index.json'), true) ?: [];
    }

    private function furniByClassname(string $classname): ?array
    {
        foreach ($this->allFurni() as $f) {
            if (($f['classname'] ?? null) === $classname) {
                return $f;
            }
        }

        return null;
    }

    /**
     * One filtered + paginated page of furni, each with its icon inlined as
     * a data URL (only the shown slice is read, so the 1600+ icon set never
     * loads at once).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function furniPage(): array
    {
        $disk = Storage::disk('import_spool');
        $needle = trim(Str::lower($this->search));

        $all = array_values(array_filter($this->allFurni(), function ($f) use ($needle) {
            if ($needle === '') {
                return true;
            }
            $hay = Str::lower(($f['classname'] ?? '') . ' ' . ($f['display_name'] ?? '') . ' ' . ($f['caption'] ?? ''));

            return str_contains($hay, $needle);
        }));

        $total = count($all);
        $pages = max(1, (int) ceil($total / $this->perPage));
        $page = max(1, min($this->page, $pages));
        $slice = array_slice($all, ($page - 1) * $this->perPage, $this->perPage);

        foreach ($slice as &$f) {
            $f['dataUrl'] = null;
            $cn = $f['classname'] ?? null;
            if ($cn && ($f['has_icon'] ?? false) && $disk->exists("_catalog/icons/{$cn}.png")) {
                $f['dataUrl'] = 'data:image/png;base64,' . base64_encode($disk->get("_catalog/icons/{$cn}.png"));
            }
        }
        unset($f);

        return ['items' => $slice, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function prevPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    /** PixelRP sub-pages, id => caption, for the move-category select. */
    private function pageOptions(): array
    {
        try {
            return CatalogPage::query()
                ->where('parent_id', 9001)
                ->where('enabled', '1')
                ->orderBy('caption')
                ->pluck('caption', 'id')
                ->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    /** Shared edit form, reused for fill + submit. */
    private function editFormSchema(): array
    {
        return [
            TextInput::make('display_name')
                ->label('Display name')
                ->required()
                ->maxLength(56),

            Select::make('page_id')
                ->label('Catalog sub-page')
                ->options($this->pageOptions())
                ->searchable(),

            ToggleButtons::make('seating')
                ->label('Seating')
                ->options(['none' => 'None', 'sit' => 'Sittable', 'lay' => 'Layable'])
                ->inline(),

            ToggleButtons::make('type')
                ->label('Placement')
                ->options(['s' => 'Floor', 'i' => 'Wall'])
                ->inline(),

            Toggle::make('allow_walk')->label('Walkable'),

            Toggle::make('allow_stack')->label('Stackable'),

            TextInput::make('stack_height')
                ->label('Stack height')
                ->numeric()
                ->minValue(0)
                ->maxValue(40)
                ->step(0.25),

            Select::make('interaction_type')
                ->label('Behaviour (interaction type)')
                ->options([
                    'default' => 'Default (static prop)',
                    'gate' => 'Gate',
                    'vendingmachine' => 'Vending machine',
                    'roller' => 'Roller',
                    'bed' => 'Bed',
                    'teleport' => 'Teleport',
                    'dice' => 'Dice',
                    'habbowheel' => 'Wheel of fortune',
                    'bottle' => 'Spin the bottle',
                ]),

            Section::make('Trading and economy')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('allow_trade')->label('Tradeable'),
                    Toggle::make('allow_gift')->label('Giftable'),
                    Toggle::make('allow_recycle')->label('Recyclable'),
                    Toggle::make('allow_marketplace_sell')->label('Sellable on Marketplace'),
                ]),

            Section::make('Advanced (effects)')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('effect_id_male')->label('Male effect id')->numeric()->minValue(0),
                    TextInput::make('effect_id_female')->label('Female effect id')->numeric()->minValue(0),
                    TextInput::make('clothing_on_walk')->label('Clothing on walk-on')->maxLength(255),
                    TextInput::make('customparams')->label('Custom params')->maxLength(255),
                ]),
        ];
    }

    public function editFurniAction(): Action
    {
        return Action::make('editFurni')
            ->modalHeading(fn (array $arguments): string => 'Edit: ' . ($arguments['classname'] ?? ''))
            ->modalSubmitActionLabel('Save and deploy')
            ->modalWidth('2xl')
            ->form($this->editFormSchema())
            ->fillForm(function (array $arguments): array {
                $f = $this->furniByClassname($arguments['classname'] ?? '') ?? [];
                $seating = ($f['allow_lay'] ?? 0) ? 'lay' : (($f['allow_sit'] ?? 0) ? 'sit' : 'none');

                return [
                    'display_name' => $f['display_name'] ?? ($f['classname'] ?? ''),
                    'page_id' => $f['page_id'] ?? null,
                    'seating' => $seating,
                    'type' => $f['type'] ?? 's',
                    'allow_walk' => (bool) ($f['allow_walk'] ?? false),
                    'allow_stack' => (bool) ($f['allow_stack'] ?? true),
                    'stack_height' => $f['stack_height'] ?? 1.0,
                    'interaction_type' => $f['interaction_type'] ?? 'default',
                    'allow_trade' => (bool) ($f['allow_trade'] ?? true),
                    'allow_gift' => (bool) ($f['allow_gift'] ?? true),
                    'allow_recycle' => (bool) ($f['allow_recycle'] ?? false),
                    'allow_marketplace_sell' => (bool) ($f['allow_marketplace_sell'] ?? false),
                    'effect_id_male' => $f['effect_id_male'] ?? 0,
                    'effect_id_female' => $f['effect_id_female'] ?? 0,
                    'clothing_on_walk' => $f['clothing_on_walk'] ?? '',
                    'customparams' => $f['customparams'] ?? '',
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $this->queueEdit($arguments['classname'] ?? '', $data);
            });
    }

    public function deleteFurniAction(): Action
    {
        return Action::make('deleteFurni')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Delete: ' . ($arguments['classname'] ?? ''))
            ->modalDescription('This removes the furni from the catalog and the database, commits to main, and triggers a production deploy. Players lose any placed copies. Continue?')
            ->modalSubmitActionLabel('Delete and deploy')
            ->action(function (array $arguments): void {
                $this->queueDelete($arguments['classname'] ?? '');
            });
    }

    private function queueEdit(string $classname, array $data): void
    {
        $f = $this->furniByClassname($classname);
        if (! $f) {
            $this->fail("Unknown furni '{$classname}'. Refresh and try again.");

            return;
        }

        $pageId = (int) ($data['page_id'] ?? 0) ?: ($f['page_id'] ?? null);
        $caption = '';
        if ($pageId) {
            $caption = (string) (CatalogPage::query()->whereKey($pageId)->value('caption') ?? '');
        }

        $seating = in_array(($data['seating'] ?? 'none'), ['none', 'sit', 'lay'], true) ? $data['seating'] : 'none';

        $item = [
            'id' => (int) ($f['id'] ?? 0),
            'classname' => $classname,
            'display_name' => trim((string) ($data['display_name'] ?? $classname)),
            'page_id' => $pageId,
            'caption' => $caption,
            'type' => ($data['type'] ?? 's') === 'i' ? 'i' : 's',
            'allow_sit' => $seating === 'sit' ? 1 : 0,
            'allow_lay' => $seating === 'lay' ? 1 : 0,
            'allow_walk' => (bool) ($data['allow_walk'] ?? false) ? 1 : 0,
            'allow_stack' => (bool) ($data['allow_stack'] ?? true) ? 1 : 0,
            'stack_height' => max(0.0, min(40.0, (float) ($data['stack_height'] ?? 1.0))),
            'allow_trade' => (bool) ($data['allow_trade'] ?? true) ? 1 : 0,
            'allow_gift' => (bool) ($data['allow_gift'] ?? true) ? 1 : 0,
            'allow_recycle' => (bool) ($data['allow_recycle'] ?? false) ? 1 : 0,
            'allow_marketplace_sell' => (bool) ($data['allow_marketplace_sell'] ?? false) ? 1 : 0,
            'interaction_type' => (string) ($data['interaction_type'] ?? 'default'),
            'effect_id_male' => max(0, (int) ($data['effect_id_male'] ?? 0)),
            'effect_id_female' => max(0, (int) ($data['effect_id_female'] ?? 0)),
            'clothing_on_walk' => trim((string) ($data['clothing_on_walk'] ?? '')),
            'customparams' => trim((string) ($data['customparams'] ?? '')),
        ];

        $this->queueJob(['action' => 'update', 'item' => $item],
            "Editing {$classname}. Watch the status panel below.");
    }

    private function queueDelete(string $classname): void
    {
        $f = $this->furniByClassname($classname);
        if (! $f) {
            $this->fail("Unknown furni '{$classname}'. Refresh and try again.");

            return;
        }

        $this->queueJob([
            'action' => 'delete',
            'item' => ['id' => (int) ($f['id'] ?? 0), 'classname' => $classname],
        ], "Deleting {$classname}. Watch the status panel below.");
    }

    /** Write an edit/delete job to the shared spool, exactly like the importer. */
    private function queueJob(array $job, string $notice): void
    {
        try {
            $disk = Storage::disk('import_spool');
            $jobId = (string) Str::uuid();
            $disk->put("{$jobId}/status.json", json_encode([
                'state' => 'queued',
                'ts' => time(),
                'message' => 'Queued. The importer-worker will pick this up shortly.',
            ], JSON_PRETTY_PRINT));
            $disk->put("{$jobId}/job.json", json_encode(array_merge(
                ['jobid' => $jobId, 'username' => trim((string) (auth()->user()?->username ?? ''))],
                $job,
            ), JSON_PRETTY_PRINT));

            $this->jobId = $jobId;

            Notification::make()
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->color('success')
                ->title('Queued')
                ->body($notice)
                ->send();
        } catch (Throwable $e) {
            report($e);
            $this->fail('Failed to queue: ' . $e->getMessage());
        }
    }

    /** Polled by the view for the in-flight edit/delete job. */
    public function getJobStatus(): ?array
    {
        if (! $this->jobId) {
            return null;
        }

        $disk = Storage::disk('import_spool');
        $path = "{$this->jobId}/status.json";
        if (! $disk->exists($path)) {
            return ['state' => 'queued', 'message' => 'Waiting for the importer-worker...'];
        }

        return json_decode($disk->get($path), true) ?: null;
    }

    private function fail(string $message): void
    {
        Notification::make()
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->color('danger')
            ->title('Action failed')
            ->body($message)
            ->send();
    }
}
