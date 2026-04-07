<?php

use App\Http\Controllers\Api\V1\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\Messaging\MessageTemplateController;
use App\Http\Controllers\Api\V1\Messaging\MessageCampaignController;
use App\Http\Controllers\Api\V1\Messaging\MessagingConfigurationController;
use App\Http\Controllers\Api\V1\Messaging\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Messaging API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/messaging
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Conversations (Chat)
    |--------------------------------------------------------------------------
    */
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index'])
            ->middleware('check.permission:messaging.conversations.view')
            ->name('messaging.conversations.index');
        Route::post('/', [ConversationController::class, 'store'])
            ->middleware('check.permission:messaging.conversations.create')
            ->name('messaging.conversations.store');
        Route::get('/{conversation}', [ConversationController::class, 'show'])
            ->middleware('check.permission:messaging.conversations.view')
            ->name('messaging.conversations.show');
        Route::get('/{conversation}/messages', [ConversationController::class, 'messages'])
            ->middleware('check.permission:messaging.conversations.view')
            ->name('messaging.conversations.messages');
        Route::post('/{conversation}/messages', [ConversationController::class, 'sendMessage'])
            ->middleware('check.permission:messaging.messages.create')
            ->name('messaging.conversations.send-message');
        Route::post('/{conversation}/read', [ConversationController::class, 'markAsRead'])
            ->middleware('check.permission:messaging.conversations.view')
            ->name('messaging.conversations.read');
    });

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('templates')->group(function () {
        Route::get('/', [MessageTemplateController::class, 'index'])->name('messaging.templates.index');
        Route::post('/', [MessageTemplateController::class, 'store'])->name('messaging.templates.store');
        Route::get('/{messageTemplate}', [MessageTemplateController::class, 'show'])->name('messaging.templates.show');
        Route::put('/{messageTemplate}', [MessageTemplateController::class, 'update'])->name('messaging.templates.update');
        Route::delete('/{messageTemplate}', [MessageTemplateController::class, 'destroy'])->name('messaging.templates.destroy');
        Route::post('/{messageTemplate}/preview', [MessageTemplateController::class, 'preview'])->name('messaging.templates.preview');
        Route::post('/{messageTemplate}/render', [MessageTemplateController::class, 'render'])->name('messaging.templates.render');
    });

    /*
    |--------------------------------------------------------------------------
    | Message Campaigns (Messaging Automations)
    |--------------------------------------------------------------------------
    */
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [MessageCampaignController::class, 'index'])->name('messaging.campaigns.index');
        Route::post('/', [MessageCampaignController::class, 'store'])->name('messaging.campaigns.store');
        Route::get('/{messageCampaign}', [MessageCampaignController::class, 'show'])->name('messaging.campaigns.show');
        Route::put('/{messageCampaign}', [MessageCampaignController::class, 'update'])->name('messaging.campaigns.update');
        Route::delete('/{messageCampaign}', [MessageCampaignController::class, 'destroy'])->name('messaging.campaigns.destroy');
        Route::post('/{messageCampaign}/launch', [MessageCampaignController::class, 'launch'])->name('messaging.campaigns.launch');
        Route::patch('/{messageCampaign}/state', [MessageCampaignController::class, 'setState'])->name('messaging.campaigns.state');
        Route::post('/{messageCampaign}/cancel', [MessageCampaignController::class, 'cancel'])->name('messaging.campaigns.cancel');
        Route::get('/{messageCampaign}/stats', [MessageCampaignController::class, 'stats'])->name('messaging.campaigns.stats');
        Route::get('/{messageCampaign}/recipients', [MessageCampaignController::class, 'recipients'])->name('messaging.campaigns.recipients');
        Route::post('/{messageCampaign}/recipients', [MessageCampaignController::class, 'addRecipients'])->name('messaging.campaigns.add-recipients');
    });

    /*
    |--------------------------------------------------------------------------
    | Messaging Configurations (Channels)
    |--------------------------------------------------------------------------
    */
    Route::prefix('configurations')->group(function () {
        Route::get('/', [MessagingConfigurationController::class, 'index'])->name('messaging.configurations.index');
        Route::post('/', [MessagingConfigurationController::class, 'store'])->name('messaging.configurations.store');
        Route::get('/{messagingConfiguration}', [MessagingConfigurationController::class, 'show'])->name('messaging.configurations.show');
        Route::put('/{messagingConfiguration}', [MessagingConfigurationController::class, 'update'])->name('messaging.configurations.update');
        Route::delete('/{messagingConfiguration}', [MessagingConfigurationController::class, 'destroy'])->name('messaging.configurations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    */
    Route::prefix('preferences')->group(function () {
        Route::get('/contacts/{contactId}', [NotificationPreferenceController::class, 'show'])->name('messaging.preferences.show');
        Route::put('/contacts/{contactId}', [NotificationPreferenceController::class, 'update'])->name('messaging.preferences.update');
        Route::post('/contacts/{contactId}/unsubscribe', [NotificationPreferenceController::class, 'unsubscribe'])->name('messaging.preferences.unsubscribe');
        Route::post('/contacts/{contactId}/resubscribe', [NotificationPreferenceController::class, 'resubscribe'])->name('messaging.preferences.resubscribe');
    });
});
