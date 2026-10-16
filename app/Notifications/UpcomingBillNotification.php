<?php

namespace App\Notifications;

use App\Models\RecurringTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Warns that a recurring template comes due shortly.
 *
 * Deliberately queued: the reminder is produced by a scheduled command, and a
 * slow mail transport must not hold the scheduler open.
 */
class UpcomingBillNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RecurringTransaction $recurring,
        public readonly int $daysAway,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = $this->recurring->next_occurrence_on;
        $amount = number_format((float) $this->recurring->amount, 2).' '.$this->recurring->currency;

        $message = (new MailMessage)
            ->subject($this->daysAway === 0
                ? 'Due today: '.$this->recurring->name
                : 'Due in '.$this->daysAway.' '.__('day', ['count' => $this->daysAway]).': '.$this->recurring->name);

        if ($this->daysAway === 0) {
            $message->line('"'.$this->recurring->name.'" is due today.');
        } else {
            $message->line('"'.$this->recurring->name.'" comes due in '
                .$this->daysAway.' '.__('day', ['count' => $this->daysAway]).'.');
        }

        $message->line('Amount: '.$amount);

        if ($this->recurring->category) {
            $message->line('Category: '.$this->recurring->category);
        }

        $message->line('Repeats: '.$this->recurring->frequency->description().'.');

        return $message->line('This is a reminder from your finance tracker.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'recurring_transaction_id' => $this->recurring->id,
            'name' => $this->recurring->name,
            'amount' => (float) $this->recurring->amount,
            'currency' => $this->recurring->currency,
            'due_on' => $this->recurring->next_occurrence_on?->toDateString(),
            'days_away' => $this->daysAway,
        ];
    }
}
