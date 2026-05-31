<?php

namespace App\Filament\Resources\PersonalAccessTokenResource\Pages;

use App\Filament\Resources\PersonalAccessTokenResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CreatePersonalAccessToken extends CreateRecord
{
    protected static string $resource = PersonalAccessTokenResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user   = User::findOrFail($data['user_id']);
        $result = $user->createToken($data['name'], $data['abilities']);

        // Store for afterCreate — plainTextToken only available here
        session()->flash('mcp_token', $result->plainTextToken);

        return $result->accessToken;
    }

    protected function afterCreate(): void
    {
        $token = session()->pull('mcp_token');

        if (!$token) return;

        Notification::make()
            ->title('Token created — copy it NOW')
            ->body(new HtmlString(
                '<p class="text-xs text-gray-500 mb-1">Token sẽ không hiện lại sau khi đóng thông báo này.</p>'
                . '<code class="block bg-gray-100 dark:bg-gray-800 rounded p-2 text-xs break-all select-all font-mono">'
                . e($token)
                . '</code>'
            ))
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return PersonalAccessTokenResource::getUrl('index');
    }
}
