<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Tournament;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProposalSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Tapk rėmėju — nustatymai';
    protected static ?string $title           = 'Tapk rėmėju — puslapio nustatymai';
    protected static ?int    $navigationSort  = 50;
    protected static string  $view            = 'filament.pages.proposal-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $photos = Setting::get('proposal_photos', '[]');
        if (is_string($photos)) {
            $photos = json_decode($photos, true) ?? [];
        }

        $this->form->fill([
            'proposal_active'          => (bool) Setting::get('proposal_active', '1'),
            'proposal_tournament_id'   => Setting::get('proposal_tournament_id'),
            'proposal_headline'        => Setting::get('proposal_headline', 'Didžiausias padelio turnyras Lietuvoje'),
            'proposal_subheadline'     => Setting::get('proposal_subheadline', '300+ žaidėjų · 10 000+ žiūrovų · TOP vasaros sporto renginys'),
            'proposal_deadline'        => Setting::get('proposal_deadline', ''),
            'proposal_urgency_text'    => Setting::get('proposal_urgency_text', ''),
            'proposal_value_anchor'    => Setting::get('proposal_value_anchor', 'Investicijos į reklamą panašiuose renginiuose įprastai: 3 000 – 15 000 €'),
            'stat_participants'        => Setting::get('stat_participants', ''),
            'stat_viewers'             => Setting::get('stat_viewers', ''),
            'stat_social_reach'        => Setting::get('stat_social_reach', ''),
            'stat_video_views'         => Setting::get('stat_video_views', ''),
            'stat_partners'            => Setting::get('stat_partners', ''),
            'stat_media'               => Setting::get('stat_media', ''),
            'proposal_audience'        => Setting::get('proposal_audience', "✔ Aktyvūs, sportuojantys žmonės\n✔ Aukštesnių pajamų grupė\n✔ Perkantys lifestyle produktus\n✔ Verslo savininkai ir vadovai\n✔ 25–50 metų amžiaus grupė"),
            'proposal_case_study'      => Setting::get('proposal_case_study', ''),
            'proposal_contact_name'    => Setting::get('proposal_contact_name', ''),
            'proposal_contact_email'   => Setting::get('proposal_contact_email', ''),
            'proposal_contact_phone'   => Setting::get('proposal_contact_phone', ''),
            'proposal_photos'          => $photos,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Visibility ────────────────────────────────────────────
                Section::make('Puslapis')
                    ->schema([
                        Toggle::make('proposal_active')
                            ->label('Puslapis matomas viešai')
                            ->helperText('Išjunkite jei puslapis dar nepasirengęs arba turnyras jau surado rėmėjus.'),

                        Select::make('proposal_tournament_id')
                            ->label('Turnyras, kuriam ieškome rėmėjų')
                            ->options(
                                Tournament::with('translations')
                                    ->orderBy('date_start', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [
                                        $t->id => ($t->translation('lt')?->title ?? $t->slug)
                                               . ' (' . $t->date_start->format('Y') . ')',
                                    ])
                            )
                            ->searchable()
                            ->nullable()
                            ->helperText('Turnyro pavadinimas bus rodomas puslapio antraštėje.'),
                    ])
                    ->columns(2),

                // ── Hero texts ────────────────────────────────────────────
                Section::make('Pagrindiniai tekstai')
                    ->schema([
                        TextInput::make('proposal_headline')
                            ->label('Šūkis (po pavadinimu)')
                            ->placeholder('Didžiausias padelio turnyras Lietuvoje')
                            ->columnSpanFull(),

                        TextInput::make('proposal_subheadline')
                            ->label('Subheadline (statistikos juosta)')
                            ->placeholder('300+ žaidėjų · 10 000+ žiūrovų · TOP vasaros sporto renginys')
                            ->columnSpanFull(),

                        TextInput::make('proposal_deadline')
                            ->label('Registracijos terminas')
                            ->placeholder('2025 m. birželio 1 d.')
                            ->helperText('Rodomas CTA mygtuke ir urgency bloke.'),

                        TextInput::make('proposal_urgency_text')
                            ->label('Urgency tekstas')
                            ->placeholder('Likę 3 generalinio rėmėjo vietos')
                            ->helperText('Jei tuščia — urgency blokas nematomas.'),
                    ])
                    ->columns(2),

                // ── Previous year stats ───────────────────────────────────
                Section::make('Praeitų metų rezultatai (ROI)')
                    ->description('Šie skaičiai rodomi kaip stipriausias pardavimo argumentas.')
                    ->schema([
                        TextInput::make('stat_participants')
                            ->label('Dalyviai')
                            ->placeholder('290'),
                        TextInput::make('stat_viewers')
                            ->label('Unikalūs žiūrovai')
                            ->placeholder('14 000+'),
                        TextInput::make('stat_social_reach')
                            ->label('Social media reach')
                            ->placeholder('45 000+'),
                        TextInput::make('stat_video_views')
                            ->label('Video peržiūros')
                            ->placeholder('8 500+'),
                        TextInput::make('stat_partners')
                            ->label('Partnerių')
                            ->placeholder('12'),
                        TextInput::make('stat_media')
                            ->label('Media publikacijos')
                            ->placeholder('6'),
                    ])
                    ->columns(3),

                // ── Audience ──────────────────────────────────────────────
                Section::make('Tikslinė auditorija')
                    ->schema([
                        Textarea::make('proposal_audience')
                            ->label('Auditorijos aprašymas (kiekviena eilutė — atskiras punktas)')
                            ->rows(6)
                            ->placeholder("✔ Aktyvūs, sportuojantys žmonės\n✔ Aukštesnių pajamų grupė")
                            ->columnSpanFull(),
                    ]),

                // ── Case study ────────────────────────────────────────────
                Section::make('Praeitų metų partneriai (Case study)')
                    ->schema([
                        Textarea::make('proposal_case_study')
                            ->label('Tekstas apie praeitų metų partnerius')
                            ->rows(4)
                            ->placeholder("Praeitais metais renginyje dalyvavo Twinbet, Škoda ir kiti partneriai. Dauguma grįžo ir kitais metais.")
                            ->columnSpanFull(),
                    ]),

                // ── Value anchor ──────────────────────────────────────────
                Section::make('Value anchoring (prieš paketus)')
                    ->schema([
                        TextInput::make('proposal_value_anchor')
                            ->label('Palyginimo frazė')
                            ->placeholder('Investicijos į reklamą panašiuose renginiuose įprastai: 3 000 – 15 000 €')
                            ->columnSpanFull()
                            ->helperText('Rodoma virš paketų — verčia mūsų kainas atrodyti labai patraukliai.'),
                    ]),

                // ── Contact ───────────────────────────────────────────────
                Section::make('Kontaktinė informacija')
                    ->schema([
                        TextInput::make('proposal_contact_name')
                            ->label('Atsakingo asmens vardas'),
                        TextInput::make('proposal_contact_email')
                            ->label('El. paštas')
                            ->email(),
                        TextInput::make('proposal_contact_phone')
                            ->label('Telefono numeris')
                            ->placeholder('+370 600 00000'),
                    ])
                    ->columns(3),

                // ── Photos ────────────────────────────────────────────────
                Section::make('Nuotraukos')
                    ->description('Nuotraukos rodomos puslapyje. Keliamos į storage/proposal direktoriją.')
                    ->schema([
                        FileUpload::make('proposal_photos')
                            ->label('Nuotraukos')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->directory('proposal')
                            ->reorderable()
                            ->maxFiles(30)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'proposal_photos') {
                Setting::set($key, json_encode(array_values((array) ($value ?? []))));
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        Notification::make()->title('Išsaugota!')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')
                ->label('Peržiūrėti puslapį')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(route('proposal'))
                ->openUrlInNewTab(),
        ];
    }
}
