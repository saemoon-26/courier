<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $deliveryData;

    public function __construct($deliveryData)
    {
        $this->deliveryData = $deliveryData;
    }

    public function build()
    {
        return $this->subject('New Delivery Request - ' . $this->deliveryData['tracking_code'])
                    ->view('emails.delivery-request');
    }
}
