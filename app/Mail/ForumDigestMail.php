<?php

namespace App\Mail;

use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Weekly forum activity digest for members who opted in on their profile.
 */
class ForumDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ForumThread>  $threads
     */
    public function __construct(
        public User $user,
        public Collection $threads,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumo do fórum — '.config('app.site_title', config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.forum-digest');
    }
}
