<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            'admin_email'      => Setting::get('admin_email', env('ADMIN_EMAIL', '')),
            'mail_mailer'      => Setting::get('mail_mailer', env('MAIL_MAILER', 'log')),
            'mail_host'        => Setting::get('mail_host', env('MAIL_HOST', 'smtp.gmail.com')),
            'mail_port'        => Setting::get('mail_port', env('MAIL_PORT', '587')),
            'mail_username'    => Setting::get('mail_username', env('MAIL_USERNAME', '')),
            'mail_password'    => Setting::get('mail_password', ''),
            'mail_encryption'  => Setting::get('mail_encryption', env('MAIL_ENCRYPTION', 'tls')),
            'mail_from_address'=> Setting::get('mail_from_address', env('MAIL_FROM_ADDRESS', '')),
            'mail_from_name'   => Setting::get('mail_from_name', env('MAIL_FROM_NAME', 'Padelio Turnyrai')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Gavėjas')
                    ->description('Kur siųsti pranešimus apie naujas žinutes iš kontaktų formos.')
                    ->schema([
                        TextInput::make('admin_email')
                            ->label('Administratoriaus el. paštas')
                            ->email()
                            ->required()
                            ->placeholder('tavo@gmail.com')
                            ->helperText('Čia gausite pranešimus kai kas nors parašys per kontaktų formą.'),
                    ]),

                Section::make('Siuntimo metodas')
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('Siuntimo būdas')
                            ->options([
                                'sendmail' => 'Sendmail (hostingo serveris)',
                                'smtp'     => 'SMTP (Gmail ar kitas)',
                                'log'      => 'Log failas (testavimui)',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Sendmail — paprasčiausias, veikia automatiškai per hostingą. SMTP — patikimesnis, reikia nustatyti žemiau.'),
                    ]),

                Section::make('SMTP nustatymai')
                    ->description('Pildykite tik jei pasirinkote SMTP siuntimo būdą.')
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('SMTP serveris')
                            ->placeholder('smtp.gmail.com'),

                        TextInput::make('mail_port')
                            ->label('Prievadas (Port)')
                            ->placeholder('587'),

                        TextInput::make('mail_username')
                            ->label('Vartotojo vardas / el. paštas')
                            ->placeholder('tavo@gmail.com'),

                        TextInput::make('mail_password')
                            ->label('Slaptažodis / App Password')
                            ->password()
                            ->revealable()
                            ->placeholder('xxxx xxxx xxxx xxxx'),

                        Select::make('mail_encryption')
                            ->label('Šifravimas')
                            ->options([
                                'tls'  => 'TLS (rekomenduojama)',
                                'ssl'  => 'SSL',
                                'none' => 'Nė vienas',
                            ]),
                    ])
                    ->columns(2),

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

        // Re-apply config so test email uses new settings immediately
        Setting::applyMailConfig();

        Notification::make()
            ->title('Nustatymai išsaugoti!')
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        $adminEmail = $this->data['admin_email'] ?? Setting::get('admin_email');

        if (! $adminEmail) {
            Notification::make()
                ->title('Įveskite administratoriaus el. paštą!')
                ->danger()
                ->send();
            return;
        }

        try {
            Mail::html(
                '<h2 style="color:#C9A84C;">Bandomasis el. laiškas</h2>
                 <p>Jei gavote šį laišką — el. pašto nustatymai veikia teisingai! 🎾</p>
                 <p style="color:#999;font-size:12px;">padelioturnyrai.lt administravimo sistema</p>',
                function ($message) use ($adminEmail) {
                    $message->to($adminEmail)
                            ->subject('✅ Bandomasis laiškas — padelioturnyrai.lt');
                }
            );

            Notification::make()
                ->title('Bandomasis laiškas išsiųstas!')
                ->body('Patikrinkite ' . $adminEmail . ' pašto dėžutę.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Klaida siunčiant laišką')
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
                ->action('sendTestEmail'),
        ];
    }
}
