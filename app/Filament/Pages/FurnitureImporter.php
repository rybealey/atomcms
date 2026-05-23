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
                                    ->live()
                                    ->columnSpanFull(),

                                FileUpload::make('icon')
                                    ->label('Icon (optional)')
                                    ->helperText('Only used for .swf imports where the source has no icon frame. Pass a 64x64 PNG; the worker prefers this over auto-extraction when set.')
                                    ->disk('import_spool')
                                    ->directory('_staging')
                                    ->preserveFilenames()
                                    ->acceptedFileTypes(['image/png'])
                                    ->visible(function (callable $get) {
                                        $stored = $get('file');
                                        if (is_array($stored)) {
                                            $stored = reset($stored);
                                        }
                                        if (! $stored) {
                                            return false;
                                        }

                                        return str_ends_with(strtolower((string) $stored), '.swf');
                                    })
                                    ->columnSpanFull(),

                                TextInput::make('display_name')
                                    ->label('Display name')
                                    ->required()
                                    ->maxLength(56),

                                TextInput::make('stack_height')
                                    ->label('Sit/lay sprite height')
                                    ->helperText('Avatar Z height when sitting or laying on it (0 = floor).')
                                    ->numeric()
                                    ->default(0)
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
                'stack_height' => max(0.0, min(4.0, (float) ($row['stack_height'] ?? 0))),
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
