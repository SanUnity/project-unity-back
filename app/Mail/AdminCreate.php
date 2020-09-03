<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminCreate extends Mailable
{
    use Queueable, SerializesModels;

    public $subject = 'Usuario invitado';
    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data){
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('app.EMAIL_NO_REPLY'), config('app.name'))
                    ->view('emails.admin-created');
    }
}
