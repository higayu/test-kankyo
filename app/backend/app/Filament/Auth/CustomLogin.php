<?php

namespace App\Filament\Auth;

// use Filament\Http\Livewire\Auth\Login as BaseLogin;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Auth;
use Filament\Navigation\NavigationItem;
use Filament\Support\Contracts\TranslatableContent;
use Filament\Actions\Action;
use Filament\Support\Contracts\HasBreadcrumbs;
use Filament\Widgets\Widget;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class CustomLogin extends BaseLogin
{
    protected static string $view = 'filament.auth.custom-login';

    public function getHeading(): string
    {
        return '';  // ヘッダーテキストを空にする
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('login_code')
                    ->label('ログインID')
                    ->required()
                    ->autofocus(),

                TextInput::make('password')
                    ->label('パスワード')
                    ->password()
                    ->required()
                    ->revealable(),
            ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'login_code' => $data['login_code'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $this->dispatch('loading');

        $user = User::where('login_code', $data['login_code'])->first();

        if (!$user || !Auth::attempt(['login_code' => $data['login_code'], 'password' => $data['password']])) {
            $this->dispatch('loading-finished');
            
            $this->addError('login_code', 'ログインIDまたはパスワードが正しくありません。');
            
            Notification::make()
                ->title('ログインに失敗しました')
                ->body('ログインIDまたはパスワードが正しくありません。')
                ->danger()
                ->send();

            return null;
        }

        if (!$user->is_admin) {
            $this->dispatch('loading-finished');
            
            Notification::make()
                ->title('アクセス権限がありません')
                ->body('管理者権限が必要です。')
                ->danger()
                ->send();

            Auth::logout();
            return null;
        }

        $this->dispatch('loading-finished');
        return parent::authenticate();
    }

    protected function getCachedSubNavigation(): array
    {
        return [];
    }

    protected function getSubNavigationPosition(): ?string
    {
        return null;
    }

    protected function getWidgetData(): array
    {
        return [];
    }

    protected function getHeader(): ?string
    {
        return null;
    }

    protected function getCachedHeaderActions(): array
    {
        return [];
    }

    protected function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getVisibleHeaderWidgets(): array
    {
        return [];
    }

    protected function getVisibleFooterWidgets(): array
    {
        return [];
    }

    protected function getFooter(): ?string
    {
        return null;
    }
}
