<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ParcelCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $clientName;
    public $trackingCode;

    /**
     * Create a new message instance.
     */
    public function __construct($code, $clientName, $trackingCode)
    {
        $this->code = $code;
        $this->clientName = $clientName;
        $this->trackingCode = $trackingCode;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Parcel Delivery Code - ' . $this->trackingCode)
                    ->view('emails.parcel-code');
    }
}
