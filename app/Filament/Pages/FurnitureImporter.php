<?php

namespace App\Filament\Pages;

use App\Models\Game\Furniture\CatalogPage;
use Filament\Actions\Action as PageAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Furniture Importer (Utilities).
 *
 * Lets staff upload .swf/.nitro furniture, target an existing PixelRP
 * sub-page or mint a new one, and set per-furni behaviour. The page itself
 * does NO heavy work (the panel queue is sync, with no workers): submit()
 * only writes a spool job under storage/app/import_spool (php's open_basedir
 * forbids gamedata; the ./atomcms dir is the shared rendezvous) and returns.
 * The long-running importer-worker container converts, parses, writes the
 * tracked custom-furni/ + catalog SQL, and pushes to origin/main — which
 * triggers a production deploy that makes the furni live.
 */
class FurnitureImporter extends Page
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilities';

    protected static ?string $navigationLabel = 'Furniture Importer';

    protected string $view = 'filament.pages.furniture-importer';

    public ?array $data = [];

    /** Set after a successful submit so the view can poll job status. */
    public ?string $jobId = null;

    public static function canAccess(): bool
    {
        // This fork defines no view::admin::* Gate — gate on the same
        // rank-based housekeeping permission the panel itself uses
        // (mirrors BadgePage::canAccess()).
        return hasHousekeepingPermission('can_access_housekeeping');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Furniture Importer';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        // Live list of PixelRP sub-pages (parent_id 9001), keyed by id.
        // Falls back to a single personal-category sentinel if the table is
        // unreachable (the form still renders; the user picks "new").
        $pageOptions = [];
        try {
            $pageOptions = CatalogPage::query()
                ->where('parent_id', 9001)
                ->where('enabled', '1')
                ->orderBy('caption')
                ->pluck('caption', 'id')
                ->toArray();
        } catch (Throwable) {
            // Stay silent; the "New category" mode still works.
        }

        return $schema
            ->components([
                Section::make('Category')
                    ->description('Pick the PixelRP sub-page these furni land in, or create a new one. Hand-curated pages (Hospital, Modern Hospital, etc.) and other imported pages both show up here.')
                    ->schema([
                        ToggleButtons::make('category_mode')
                            ->label('Destination')
                            ->options([
                                'existing' => 'Existing sub-page',
                                'new' => 'New sub-page',
                            ])
                            ->default('existing')
                            ->inline()
                            ->required(),

                        Select::make('category_page_id')
                            ->label('Sub-page')
                            ->options($pageOptions)
                            ->searchable()
                            ->required()
                            ->visible(fn (callable $get) => ($get('category_mode') ?? 'existing') === 'existing'),

                        TextInput::make('category_new_caption')
                            ->label('New sub-page name')
                            ->helperText("Creates PixelRP > <name>. Re-using an existing name appends to it instead. No ampersands or em-dashes.")
                            ->maxLength(40)
                            ->required()
                            ->visible(fn (callable $get) => ($get('category_mode') ?? 'existing') === 'new'),
                    ]),

                Section::make('Furniture')
                    ->description('Imported furni are free and staff-only. Add one row per furni: .swf is converted via the Nitro converter, .nitro is used as-is, and width/length are auto-detected from the bundle.')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->addActionLabel('Add furni')
                            ->minItems(1)
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                FileUpload::make('file')
                                    ->label('File (.swf or .nitro)')
                                    ->helperText('.nitro has no MIME type, so the picker is unfiltered; only .swf/.nitro are accepted (anything else is rejected on submit).')
                                    ->disk('import_spool')
                                    ->directory('_staging')
                                    ->preserveFilenames()
                                    // No acceptedFileTypes: .nitro reports no
                                    // MIME, so a MIME allowlist makes the OS
                                    // picker + FilePond grey it out. submit()
                                    // enforces the .swf/.nitro extension.
                                    ->required()
                                    ->columnSpanFull(),

                                // Icon override: kept always-visible because
                                // a sibling visible() callback inside a
                                // Repeater doesn't reliably re-evaluate after
                                // a FileUpload state change. queueImport()
                                // ignores it for .nitro imports, so leaving
                                // it blank on .nitro rows is harmless.
                                FileUpload::make('icon')
                                    ->label('Icon override (optional, .swf only)')
                                    ->helperText('Optional 64x64 PNG. Only used for .swf imports whose bundle ships no icon frame — when set, the worker prefers this over auto-extraction. Ignored for .nitro rows.')
                                    ->disk('import_spool')
                                    ->directory('_staging')
                                    ->preserveFilenames()
                                    ->acceptedFileTypes(['image/png'])
                                    ->columnSpanFull(),

                                TextInput::make('display_name')
                                    ->label('Display name')
                                    ->required()
                                    ->maxLength(56),

                                TextInput::make('stack_height')
                                    ->label('Stack height')
                                    ->helperText('Z offset added to anything stacked on this furni. 1 = a standard 1-tile block, 0 = a floor tile or rug (stacked items sit at floor level), 0.5 = a half-height block. Defaults to 1 because most furni is meant to be built on.')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0)
                                    ->maxValue(4)
                                    ->step(0.25),

                                Toggle::make('walkable')
                                    ->label('Walkable (players can walk through it)')
                                    ->default(false),

                                ToggleButtons::make('seating')
                                    ->label('Seating')
                                    ->options([
                                        'none' => 'None',
                                        'sit' => 'Sittable',
                                        'lay' => 'Layable',
                                    ])
                                    ->default('none')
                                    ->inline()
                                    ->required(),

                                ToggleButtons::make('furni_type')
                                    ->label('Placement')
                                    ->options([
                                        's' => 'Floor',
                                        'i' => 'Wall',
                                    ])
                                    ->default('s')
                                    ->inline()
                                    ->required(),

                                Toggle::make('allow_stack')
                                    ->label('Stackable (other furni can be placed on top)')
                                    ->default(true),

                                Select::make('interaction_type')
                                    ->label('Behaviour (interaction type)')
                                    ->helperText('Leave as Default for a normal prop. Other logics need a furni built for them.')
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
                                    ])
                                    ->default('default')
                                    ->required(),

                                Section::make('Trading and economy')
                                    ->columnSpanFull()
                                    ->collapsible()
                                    ->collapsed()
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('allow_trade')
                                            ->label('Tradeable')
                                            ->default(true),
                                        Toggle::make('allow_gift')
                                            ->label('Giftable')
                                            ->default(true),
                                        Toggle::make('allow_recycle')
                                            ->label('Recyclable')
                                            ->default(false),
                                        Toggle::make('allow_marketplace_sell')
                                            ->label('Sellable on Marketplace')
                                            ->default(false),
                                    ]),

                                Section::make('Advanced (effects)')
                                    ->columnSpanFull()
                                    ->collapsible()
                                    ->collapsed()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('effect_id_male')
                                            ->label('Male avatar effect id (0 = none)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0),
                                        TextInput::make('effect_id_female')
                                            ->label('Female avatar effect id (0 = none)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0),
                                        TextInput::make('clothing_on_walk')
                                            ->label('Clothing applied on walk-on (figure string, optional)')
                                            ->maxLength(255),
                                        TextInput::make('customparams')
                                            ->label('Custom params (optional)')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PageAction::make('import')
                ->label('Import and deploy')
                ->color('primary')
                ->icon('heroicon-o-rocket-launch')
                ->requiresConfirmation()
                ->modalHeading('Import furniture and deploy?')
                ->modalDescription('This converts the furniture into the selected PixelRP sub-page, commits it to the main branch, and triggers a production deploy. The emulator restarts briefly, disconnecting online players for a moment. Continue?')
                ->modalSubmitActionLabel('Import and deploy')
                ->action(fn () => $this->submit()),
        ];
    }

    public function submit(): void
    {
        // getState() validates + dehydrates the form. In Chrome the FilePond
        // upload often hasn't finalised when the confirm-modal button is
        // clicked, so the required FileUpload fails validation and getState()
        // throws — inside a confirmation modal that error is invisible, which
        // is the "click does nothing" report. Catch it and say so plainly.
        try {
            $state = $this->form->getState();
        } catch (ValidationException $e) {
            $this->fail('Could not start the import. Make sure every row has a file attached and that each upload has finished (the progress bar completes) before clicking Import, then try again.');

            return;
        }

        $username = trim((string) (auth()->user()?->username ?? ''));
        $items = $state['items'] ?? [];

        if ($username === '') {
            $this->fail('Could not determine your username from the session.');

            return;
        }
        if (empty($items)) {
            $this->fail('Add at least one furni to import.');

            return;
        }

        $mode = $state['category_mode'] ?? 'existing';
        if (! in_array($mode, ['existing', 'new'], true)) {
            $this->fail('Pick whether to use an existing sub-page or create a new one.');

            return;
        }

        $category = ['mode' => $mode];
        if ($mode === 'existing') {
            $pageId = (int) ($state['category_page_id'] ?? 0);
            if ($pageId <= 0) {
                $this->fail('Select an existing sub-page to import into.');

                return;
            }
            $page = CatalogPage::query()->whereKey($pageId)->first();
            if (! $page) {
                $this->fail("Sub-page #{$pageId} no longer exists. Refresh and try again.");

                return;
            }
            $category['page_id'] = (int) $page->id;
            $category['caption'] = (string) $page->caption;
        } else {
            $caption = trim((string) ($state['category_new_caption'] ?? ''));
            if ($caption === '') {
                $this->fail('Name the new sub-page or switch back to "Existing".');

                return;
            }
            $category['caption'] = $caption;
        }

        // Any failure past here must surface — never silently no-op.
        try {
            $this->queueImport($username, $category, $items);
        } catch (Throwable $e) {
            report($e);
            $this->fail('Import failed to queue: ' . $e->getMessage());
        }
    }

    /**
     * @param  array{mode: string, page_id?: int, caption?: string}  $category
     * @param  array<int, array<string, mixed>>  $items
     */
    private function queueImport(string $username, array $category, array $items): void
    {
        $disk = Storage::disk('import_spool');
        $jobId = (string) Str::uuid();
        $uploadsDir = "{$jobId}/uploads";
        $disk->makeDirectory($uploadsDir);

        $manifest = [];
        foreach ($items as $row) {
            $stored = $row['file'] ?? null;
            if (is_array($stored)) {
                $stored = reset($stored);
            }
            if (! $stored || ! $disk->exists($stored)) {
                continue;
            }

            $filename = basename($stored);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (! in_array($ext, ['swf', 'nitro'], true)) {
                // Reject the whole batch: an unknown extension means the
                // worker can't classify it, and a partial import is worse
                // than a clear failure.
                $disk->deleteDirectory($jobId);
                $this->fail("Only .swf and .nitro files are allowed (got '{$filename}').");

                return;
            }

            $disk->move($stored, "{$uploadsDir}/{$filename}");

            // Optional manual icon for .swf imports whose bundle ships no icon
            // frame. Only honoured when the main file is .swf; otherwise
            // silently ignored (a .nitro bundle's icon comes from the bundle).
            $iconFilename = null;
            if ($ext === 'swf') {
                $iconStored = $row['icon'] ?? null;
                if (is_array($iconStored)) {
                    $iconStored = reset($iconStored);
                }
                if ($iconStored && $disk->exists($iconStored)) {
                    $iconBase = basename($iconStored);
                    $iconFilename = "icon-{$iconBase}";
                    $disk->move($iconStored, "{$uploadsDir}/{$iconFilename}");
                }
            }

            $manifest[] = [
                'filename' => $filename,
                'kind' => $ext,
                'icon_filename' => $iconFilename,
                'display_name' => trim($row['display_name'] ?? '') ?: pathinfo($filename, PATHINFO_FILENAME),
                'walkable' => (bool) ($row['walkable'] ?? false),
                'seating' => in_array(($row['seating'] ?? 'none'), ['none', 'sit', 'lay'], true)
                    ? $row['seating'] : 'none',
                'stack_height' => max(0.0, min(4.0, (float) ($row['stack_height'] ?? 1.0))),
                'furni_type' => ($row['furni_type'] ?? 's') === 'i' ? 'i' : 's',
                'allow_stack' => (bool) ($row['allow_stack'] ?? true),
                'allow_trade' => (bool) ($row['allow_trade'] ?? true),
                'allow_gift' => (bool) ($row['allow_gift'] ?? true),
                'allow_recycle' => (bool) ($row['allow_recycle'] ?? false),
                'allow_marketplace_sell' => (bool) ($row['allow_marketplace_sell'] ?? false),
                'interaction_type' => (string) ($row['interaction_type'] ?? 'default'),
                'effect_id_male' => max(0, (int) ($row['effect_id_male'] ?? 0)),
                'effect_id_female' => max(0, (int) ($row['effect_id_female'] ?? 0)),
                'clothing_on_walk' => trim((string) ($row['clothing_on_walk'] ?? '')),
                'customparams' => trim((string) ($row['customparams'] ?? '')),
            ];
        }

        if (empty($manifest)) {
            $disk->deleteDirectory($jobId);
            $this->fail('No valid uploads were found in the form.');

            return;
        }

        // status.json first so a poll between the two writes never 404s;
        // job.json LAST — its presence is the worker's "ready" signal.
        $disk->put("{$jobId}/status.json", json_encode([
            'state' => 'queued',
            'ts' => time(),
            'message' => 'Queued. The importer-worker will pick this up shortly.',
        ], JSON_PRETTY_PRINT));
        $disk->put("{$jobId}/job.json", json_encode([
            'jobid' => $jobId,
            'username' => $username,
            'category' => $category,
            'items' => $manifest,
        ], JSON_PRETTY_PRINT));

        $this->jobId = $jobId;
        $this->data['items'] = [];

        $destination = $category['caption'] ?? $username;

        Notification::make()
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->color('success')
            ->title('Import queued')
            ->body(count($manifest) . " furni queued into PixelRP > {$destination}. Watch the status panel below.")
            ->send();
    }

    /**
     * Read by the view via wire:poll. Returns the worker's status.json for
     * the in-flight job, or null when there is nothing to show.
     *
     * @return array<string, mixed>|null
     */
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

    /**
     * Per-row preview thumbnails for the importer form. For each staged
     * upload we drop a preview-request into the shared spool (the
     * importer-worker renders a 64x64 icon via the same extractor the real
     * import uses) and poll the result. Returns one entry per repeater row,
     * in order, so the view can pair each card with its thumbnail.
     *
     * @return array<int, array{filename:string,state:string,dataUrl:?string,reason:?string}>
     */
    public function previewStates(): array
    {
        $disk = Storage::disk('import_spool');
        $out = [];

        foreach (($this->data['items'] ?? []) as $row) {
            $stored = $row['file'] ?? null;
            if (is_array($stored)) {
                $stored = reset($stored);
            }
            if (! $stored || ! is_string($stored)) {
                continue;
            }

            $filename = basename($stored);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (! in_array($ext, ['swf', 'nitro'], true)) {
                $out[] = ['filename' => $filename, 'state' => 'error',
                    'dataUrl' => null, 'reason' => 'unsupported file'];

                continue;
            }

            // Deterministic token off the staged path so polls are stable
            // and a re-upload of the same file reuses its preview.
            $token = sha1($stored);
            $dir = "_preview/{$token}";
            if (! $disk->exists("{$dir}/request.json")) {
                $disk->put("{$dir}/request.json", json_encode([
                    'staged_file' => $filename,
                    'kind' => $ext,
                ], JSON_PRETTY_PRINT));
            }

            if (! $disk->exists("{$dir}/result.json")) {
                $out[] = ['filename' => $filename, 'state' => 'pending',
                    'dataUrl' => null, 'reason' => null];

                continue;
            }

            $result = json_decode($disk->get("{$dir}/result.json"), true) ?: [];
            if (($result['state'] ?? '') === 'ok' && $disk->exists("{$dir}/preview.png")) {
                $b64 = base64_encode($disk->get("{$dir}/preview.png"));
                $out[] = ['filename' => $filename, 'state' => 'ok',
                    'dataUrl' => "data:image/png;base64,{$b64}", 'reason' => null];
            } else {
                $out[] = ['filename' => $filename, 'state' => 'error',
                    'dataUrl' => null, 'reason' => $result['reason'] ?? 'preview failed'];
            }
        }

        return $out;
    }

    private function fail(string $message): void
    {
        Notification::make()
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->color('danger')
            ->title('Import failed')
            ->body($message)
            ->send();
    }
}
