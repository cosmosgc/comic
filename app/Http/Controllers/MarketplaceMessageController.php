<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMarketplaceMessageRequest;
use App\Models\MarketplaceConversation;
use App\Notifications\MarketplaceNotification;
use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contact-only inbox: buyers and sellers talk about one listing.
 * No attachments, payments or order state in this version.
 */
class MarketplaceMessageController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureEnabled();
        $user = $request->user();

        $conversations = MarketplaceConversation::query()
            ->where('buyer_id', $user->id)
            ->orWhere('seller_id', $user->id)
            ->with([
                'listing:id,title,slug,user_id',
                'listing.images',
                'buyer:id,name,handle,avatar_image_path',
                'seller:id,name,handle,avatar_image_path',
                'lastMessage',
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        $unread = $conversations->getCollection()->sum(fn (MarketplaceConversation $conversation) => $conversation->unreadFor($user));

        return view('marketplace.messages.index', [
            'conversations' => $conversations,
            'unread' => $unread,
        ]);
    }

    public function show(Request $request, MarketplaceConversation $conversation): View
    {
        $this->ensureEnabled();
        $this->authorize('view', $conversation);

        $user = $request->user();
        $conversation->load([
            'listing.images',
            'listing.comics',
            'listing.category',
            'buyer:id,name,handle,avatar_image_path',
            'seller:id,name,handle,avatar_image_path',
            'messages.sender:id,name,handle,avatar_image_path',
        ]);

        // Opening the thread marks the other side's messages read.
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conversation->markReadFor($user);

        return view('marketplace.messages.show', [
            'conversation' => $conversation,
            'other' => $conversation->otherParty($user),
        ]);
    }

    public function store(StoreMarketplaceMessageRequest $request, MarketplaceConversation $conversation): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('reply', $conversation);

        $sender = $request->user();
        $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $request->validated()['body'],
        ]);

        $conversation->last_message_at = now();
        if ((int) $sender->id === (int) $conversation->buyer_id) {
            $conversation->seller_unread_count = (int) $conversation->seller_unread_count + 1;
        } else {
            $conversation->buyer_unread_count = (int) $conversation->buyer_unread_count + 1;
        }
        $conversation->save();

        $other = $conversation->otherParty($sender);
        if ($other !== null) {
            $other->notify(new MarketplaceNotification(
                'marketplace.message',
                'Nova mensagem no Mercado',
                $sender->name.' respondeu sobre "'.$conversation->listing?->title.'".',
                route('marketplace.messages.show', ['conversation' => $conversation]),
            ));
        }

        return redirect()->route('marketplace.messages.show', ['conversation' => $conversation])
            ->with('success', 'Mensagem enviada.');
    }

    protected function ensureEnabled(): void
    {
        abort_unless(Marketplace::enabled(), 404);
    }
}
