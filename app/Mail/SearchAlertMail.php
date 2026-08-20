<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SearchAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{id: int, name: string, applications: list<array<string, mixed>>, total: int, omitted: int}>  $searches
     */
    public function __construct(
        public User $notifiable,
        public array $searches,
        public int $total,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->total === 1
            ? '1 new application in your IMBY searches'
            : $this->total.' new applications in your IMBY searches';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.search-alert',
            with: [
                'user' => $this->notifiable,
                'searches' => $this->searches,
                'total' => $this->total,
            ],
        );
    }
}
