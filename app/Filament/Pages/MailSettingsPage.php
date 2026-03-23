<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class MailSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'El. pašto nustatymai';
    protected static ?string $title           = 'El. pašto nustatymai';
    protected static ?int    $navigationSort  = 99;
    protected static string  $view            = 'filament.pages.mail-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'admin_email'       => Setting::get('admin_email', env('ADMIN_EMAIL', '')),
            'mail_mailer'       => Setting::get('mail_mailer', 'phpmail'),
            'mail_host'         => Setting::get('mail_host', 'smtp.gmail.com'),
            'mail_port'         => Setting::get('mail_port', '587'),
            'mail_username'     => Setting::get('mail_username', ''),
            'mail_password'     => Setting::get('mail_password', ''),
            'mail_encryption'   => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address', env('MAIL_FROM_ADDRESS', '')),
            'mail_from_name'    => Setting::get('mail_from_name', 'Padelio Turnyrai'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Recipient ────────────────────────────────────────────
                Section::make('Gavėjas')
                    ->description('Kur gauti pranešimus kai kas nors parašo per kontaktų formą.')
                    ->schema([
                        TextInput::make('admin_email')
                            ->label('Administratoriaus el. paštas')
                            ->email()
                            ->required()
                            ->placeholder('tavo@gmail.com'),
                    ]),

                // ── Method ───────────────────────────────────────────────
                Section::make('Siuntimo metodas')
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('Siuntimo būdas')
                            ->options([
                                'phpmail' => '📬  PHP mail()  —  rekomenduojama šiam hostingui',
                                'smtp'    => '📡  SMTP  —  Gmail, Brevo ar kitas',
                                'log'     => '📄  Log failas  —  tik testavimui (laiškai nesiunčiami)',
                            ])
                            ->required()
                            ->live()
                            ->helperText(
                                'PHP mail() — veikia automatiškai per hostingo serverį, nereikia jokių papildomų nustatymų. ' .
                                'SMTP — jei PHP mail() neveikia, naudokite Gmail su App Password arba Brevo.'
                            ),
                    ]),

                // ── SMTP fields ───────────────────────────────────────────
                Section::make('SMTP nustatymai')
                    ->description('Pildykite tik jei pasirinkote SMTP.')
                    ->schema([
                        View::make('filament.components.smtp-hint'),

                        TextInput::make('mail_host')
                            ->label('SMTP serveris')
                            ->placeholder('smtp.gmail.com')
                            ->columnSpan(2),

                        TextInput::make('mail_port')
                            ->label('Prievadas (Port)')
                            ->placeholder('587'),

                        Select::make('mail_encryption')
                            ->label('Šifravimas')
                            ->options([
                                'tls'  => 'TLS  (587 portui)',
                                'ssl'  => 'SSL  (465 portui)',
                                'none' => 'Nė vienas',
                            ]),

                        TextInput::make('mail_username')
                            ->label('Vartotojo vardas / el. paštas')
                            ->placeholder('tavo@gmail.com'),

                        TextInput::make('mail_password')
                            ->label('Slaptažodis / App Password')
                            ->password()
                            ->revealable()
                            ->placeholder('xxxx xxxx xxxx xxxx')
                            ->helperText('Gmail: naudokite "App Password", ne įprastą slaptažodį.'),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),

                // ── Sender info ───────────────────────────────────────────
                Section::make('Siuntėjo informacija')
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label('Siuntėjo el. paštas')
                            ->email()
                            ->placeholder('info@padelioturnyrai.lt'),

                        TextInput::make('mail_from_name')
                            ->label('Siuntėjo vardas')
                            ->placeholder('Padelioturnyrai.lt'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        Setting::applyMailConfig();

        Notification::make()
            ->title('Nustatymai išsaugoti!')
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        // Apply latest form state (not yet saved) before sending
        $data    = $this->data;
        $mailer  = $data['mail_mailer'] ?? Setting::get('mail_mailer', 'phpmail');
        $toEmail = $data['admin_email'] ?? Setting::get('admin_email');

        if (! $toEmail) {
            Notification::make()
                ->title('Įveskite administratoriaus el. paštą!')
                ->danger()
                ->send();
            return;
        }

        // Temporarily configure mail from form data before test
        config(['mail.default' => $mailer]);
        if ($mailer === 'smtp') {
            config([
                'mail.mailers.smtp.host'       => $data['mail_host'] ?? '',
                'mail.mailers.smtp.port'       => (int) ($data['mail_port'] ?? 587),
                'mail.mailers.smtp.username'   => $data['mail_username'] ?? '',
                'mail.mailers.smtp.password'   => $data['mail_password'] ?? '',
                'mail.mailers.smtp.encryption' => $data['mail_encryption'] ?? 'tls',
            ]);
        }
        if ($from = ($data['mail_from_address'] ?? null)) {
            config(['mail.from.address' => $from]);
            config(['mail.from.name'    => $data['mail_from_name'] ?? 'Padelio Turnyrai']);
        }

        try {
            Mail::html(
                '<div style="font-family:sans-serif;max-width:520px;margin:auto;padding:32px">
                    <h2 style="color:#C9A84C;margin-top:0">🎾 Bandomasis laiškas</h2>
                    <p>Jei gavote šį laišką — el. pašto nustatymai veikia teisingai!</p>
                    <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
                    <p style="color:#999;font-size:12px">padelioturnyrai.lt administravimo sistema</p>
                </div>',
                function ($msg) use ($toEmail) {
                    $msg->to($toEmail)->subject('✅ Bandomasis laiškas — padelioturnyrai.lt');
                }
            );

            Notification::make()
                ->title('Laiškas išsiųstas!')
                ->body('Patikrinkite ' . $toEmail . ' pašto dėžutę.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Nepavyko išsiųsti')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Siųsti bandomąjį laišką')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Siųsti bandomąjį laišką?')
                ->modalDescription('Bus naudojami šiuo metu užpildyti nustatymai (net jei neišsaugoti).')
                ->modalSubmitActionLabel('Siųsti')
                ->action('sendTestEmail'),
        ];
    }
}
